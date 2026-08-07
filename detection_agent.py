#!/usr/bin/env python3
"""
On-site CCTV detection agent.

1) Polls viewer status — starts detect.py when Open Surveillance is open.
2) Polls camera scan jobs — runs camera_discover.py when Camera Management requests a LAN scan.

Configure in .env (same as detect.py):
  CCTV_FRAME_UPLOAD_KEY=...
  CCTV_FRAME_UPLOAD_URL=https://surveillance.alertaraqc.com/api/cctv_frame_upload.php
"""

from __future__ import annotations

import json
import os
import subprocess
import time
from pathlib import Path
from typing import Dict, Optional
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parent
LOCK_FILE = ROOT / "detect.lock"
LOG_FILE = ROOT / "detection_agent.log"


def load_env(path: Path) -> dict:
    env = {}
    if not path.is_file():
        return env
    for line in path.read_text(encoding="utf-8", errors="ignore").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        env[key.strip()] = value.strip().strip('"').strip("'")
    return env


def log(msg: str) -> None:
    stamp = time.strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{stamp}] {msg}"
    print(line, flush=True)
    try:
        with LOG_FILE.open("a", encoding="utf-8") as fh:
            fh.write(line + "\n")
    except OSError:
        pass


def pid_alive(pid: int) -> bool:
    if pid <= 0:
        return False
    if os.name == "nt":
        try:
            out = subprocess.check_output(
                ["tasklist", "/FI", f"PID eq {pid}", "/NH"],
                stderr=subprocess.DEVNULL,
                text=True,
                creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
            )
            return str(pid) in out
        except (subprocess.CalledProcessError, OSError):
            return False
    try:
        os.kill(pid, 0)
        return True
    except OSError:
        return False


def read_detect_pid() -> Optional[int]:
    if not LOCK_FILE.is_file():
        return None
    try:
        pid = int(LOCK_FILE.read_text(encoding="utf-8").strip() or "0")
    except (OSError, ValueError):
        return None
    if not pid_alive(pid):
        try:
            if LOCK_FILE.exists():
                LOCK_FILE.unlink()
        except OSError:
            pass
        return None
    return pid


def start_detect() -> None:
    if read_detect_pid() is not None:
        log("detect.py already running")
        return

    env = os.environ.copy()
    creationflags = 0
    if os.name == "nt":
        creationflags = getattr(subprocess, "CREATE_NEW_PROCESS_GROUP", 0) | getattr(
            subprocess, "DETACHED_PROCESS", 0
        )
        cmd = ["py", "detect.py"]
    else:
        cmd = ["python3", "detect.py"]

    log("Starting detect.py (Open Surveillance is open)...")
    try:
        with open(ROOT / "detection_control.log", "a", encoding="utf-8") as log_fh:
            subprocess.Popen(
                cmd,
                cwd=str(ROOT),
                env=env,
                stdout=log_fh,
                stderr=subprocess.STDOUT,
                creationflags=creationflags,
                close_fds=True,
            )
    except OSError as exc:
        log(f"Failed to start detect.py: {exc}")
        return

    for _ in range(20):
        time.sleep(0.25)
        if read_detect_pid() is not None:
            log(f"detect.py started (pid {read_detect_pid()})")
            return
    log("detect.py start requested (lock file not seen yet)")


def derive_api_url(frame_upload_url: str, filename: str) -> str:
    if not frame_upload_url:
        return ""
    if "cctv_frame_upload.php" in frame_upload_url:
        return frame_upload_url.replace("cctv_frame_upload.php", filename)
    base = frame_upload_url.rsplit("/", 1)[0]
    return base + "/" + filename


def api_request(
    url: str,
    api_key: str,
    method: str = "GET",
    payload: Optional[Dict] = None,
    timeout: float = 30.0,
) -> Optional[Dict]:
    body = None
    headers = {
        "Accept": "application/json",
        "X-CCTV-Upload-Key": api_key,
        "Authorization": "Bearer " + api_key,
        "User-Agent": "AlertaraQC-DetectionAgent/1.0",
    }
    if payload is not None:
        body = json.dumps(payload).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = Request(url, data=body, headers=headers, method=method)
    try:
        with urlopen(req, timeout=timeout) as resp:
            data = json.loads(resp.read().decode("utf-8", errors="replace"))
            return data if isinstance(data, dict) else None
    except Exception as exc:  # noqa: BLE001
        log(f"API {method} {url} failed: {exc}")
        return None


def run_camera_discover() -> Dict:
    out_file = ROOT / "camera_scan_result_tmp.json"
    try:
        if out_file.exists():
            out_file.unlink()
    except OSError:
        pass

    py = "py" if os.name == "nt" else "python3"
    cmd = [py, "camera_discover.py", "--json", "--out", str(out_file)]
    try:
        completed = subprocess.run(
            cmd,
            cwd=str(ROOT),
            capture_output=True,
            text=True,
            timeout=180,
            creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0) if os.name == "nt" else 0,
        )
    except Exception as exc:  # noqa: BLE001
        return {"success": False, "message": "Failed to run camera_discover.py: " + str(exc)}

    if out_file.is_file():
        try:
            data = json.loads(out_file.read_text(encoding="utf-8"))
            try:
                out_file.unlink()
            except OSError:
                pass
            if isinstance(data, dict):
                return data
        except (OSError, json.JSONDecodeError):
            pass

    try:
        data = json.loads(completed.stdout or "")
        if isinstance(data, dict):
            return data
    except json.JSONDecodeError:
        pass

    err = (completed.stderr or completed.stdout or "Scan failed").strip()
    return {"success": False, "message": err[:500]}


def process_scan_job(scan_url: str, api_key: str) -> None:
    pending = api_request(scan_url + "?role=agent", api_key, method="GET", timeout=20)
    if not pending or not pending.get("success") or not pending.get("has_job"):
        return

    job = pending.get("job") or {}
    job_id = job.get("id")
    status = job.get("status")
    if not job_id or status not in ("pending", "running"):
        return

    if status == "pending":
        claimed = api_request(
            scan_url + "?role=agent",
            api_key,
            method="POST",
            payload={"action": "claim", "id": job_id, "role": "agent"},
            timeout=20,
        )
        if not claimed or not claimed.get("success"):
            return
        log(f"Claimed camera scan job {job_id}")

    log(f"Running LAN camera discovery for job {job_id}...")
    result = run_camera_discover()
    payload = {
        "action": "complete",
        "role": "agent",
        "id": job_id,
        "success": bool(result.get("success")),
        "cameras": result.get("cameras") or [],
        "scanned_subnets": result.get("scanned_subnets") or [],
        "elapsed_seconds": result.get("elapsed_seconds"),
        "note": result.get("note"),
        "message": result.get("message") or result.get("error"),
    }
    done = api_request(scan_url + "?role=agent", api_key, method="POST", payload=payload, timeout=30)
    if done and done.get("success"):
        count = len(payload["cameras"])
        log(f"Camera scan complete: {count} candidate(s)")
    else:
        log("Failed to upload camera scan results")


def main() -> int:
    file_env = load_env(ROOT / ".env")
    for key, value in file_env.items():
        os.environ.setdefault(key, value)

    api_key = (
        file_env.get("CCTV_FRAME_UPLOAD_KEY", "")
        or os.environ.get("CCTV_FRAME_UPLOAD_KEY", "")
    ).strip()
    frame_url = (
        file_env.get("CCTV_FRAME_UPLOAD_URL", "")
        or os.environ.get("CCTV_FRAME_UPLOAD_URL", "")
    ).strip()
    status_url = (
        file_env.get("CCTV_VIEWER_STATUS_URL", "")
        or os.environ.get("CCTV_VIEWER_STATUS_URL", "")
        or derive_api_url(frame_url, "cctv_viewer_status.php")
    ).strip()
    scan_url = (
        file_env.get("CCTV_CAMERA_SCAN_URL", "")
        or os.environ.get("CCTV_CAMERA_SCAN_URL", "")
        or derive_api_url(frame_url, "cctv_camera_scan.php")
    ).strip()
    poll_seconds = float(
        file_env.get("CCTV_AGENT_POLL_SECONDS")
        or os.environ.get("CCTV_AGENT_POLL_SECONDS")
        or "3"
    )
    poll_seconds = max(2.0, poll_seconds)

    if not api_key or not status_url:
        log(
            "Missing CCTV_FRAME_UPLOAD_KEY or status URL. "
            "Set CCTV_FRAME_UPLOAD_URL / CCTV_VIEWER_STATUS_URL in .env"
        )
        return 1

    log(f"Detection agent started. Polling every {poll_seconds:.0f}s")
    log(f"  viewer: {status_url}")
    log(f"  scan:   {scan_url}")
    log("Keeping detect.py running so Open Surveillance has a live feed immediately.")
    log("Leave this window open. Ctrl+C stops the agent only.")

    # Start camera detection immediately so frames are already uploading
    # before an admin opens Open Surveillance.
    if read_detect_pid() is None:
        start_detect()

    while True:
        if scan_url:
            try:
                process_scan_job(scan_url, api_key)
            except Exception as exc:  # noqa: BLE001
                log(f"Scan job handler error: {exc}")

        # Keep detect.py alive continuously (auto-restart if it exits).
        if read_detect_pid() is None:
            log("detect.py not running — restarting for live feed readiness")
            start_detect()

        # Still refresh viewer heartbeat awareness (optional logging).
        api_request(status_url, api_key, method="GET", timeout=15)
        time.sleep(poll_seconds)


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except KeyboardInterrupt:
        log("Detection agent stopped.")
        raise SystemExit(0)
