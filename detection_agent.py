#!/usr/bin/env python3
"""
On-site CCTV detection agent (leave this running once on the PC).

Auto start/stop — no manual start_detection.bat / stop_detection.bat:
  • Opens Open Surveillance  → starts detect.py
  • Leaves Open Surveillance → stops detect.py (after a short grace)
  • Also runs Camera Management LAN scans

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
        return

    env = os.environ.copy()
    env["CCTV_AGENT_MANAGED"] = "1"
    creationflags = 0
    if os.name == "nt":
        creationflags = getattr(subprocess, "CREATE_NEW_PROCESS_GROUP", 0) | getattr(
            subprocess, "DETACHED_PROCESS", 0
        )
        # Prefer pythonw so no console window pops up for detect.py
        cmd = ["py", "-3", "detect.py"]
    else:
        cmd = ["python3", "detect.py"]

    log("Auto-start: Open Surveillance is open → starting detect.py...")
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

    for _ in range(24):
        time.sleep(0.25)
        pid = read_detect_pid()
        if pid is not None:
            log(f"detect.py started (pid {pid})")
            return
    log("detect.py start requested (lock file not seen yet)")


def stop_detect(reason: str = "Open Surveillance closed") -> None:
    pid = read_detect_pid()
    if pid is None:
        # Also clear any orphan lock
        try:
            if LOCK_FILE.exists():
                LOCK_FILE.unlink()
        except OSError:
            pass
        return

    log(f"Auto-stop: {reason} → stopping detect.py (pid {pid})...")
    try:
        if os.name == "nt":
            subprocess.run(
                ["taskkill", "/F", "/PID", str(pid), "/T"],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
                check=False,
            )
            # Sweep any leftover detect.py processes started outside the lock.
            subprocess.run(
                [
                    "powershell",
                    "-NoProfile",
                    "-Command",
                    "Get-CimInstance Win32_Process | "
                    "Where-Object { $_.CommandLine -match 'detect\\.py' } | "
                    "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }",
                ],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
                check=False,
            )
        else:
            try:
                os.kill(pid, 15)
            except OSError:
                pass
    except OSError as exc:
        log(f"Failed to stop detect.py: {exc}")

    try:
        if LOCK_FILE.exists():
            LOCK_FILE.unlink()
    except OSError:
        pass
    log("detect.py stopped")


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
        or "2"
    )
    poll_seconds = max(1.5, poll_seconds)
    # Stop almost immediately once Open Surveillance clears the viewer flag.
    stop_grace_seconds = float(
        file_env.get("CCTV_AGENT_STOP_GRACE_SECONDS")
        or os.environ.get("CCTV_AGENT_STOP_GRACE_SECONDS")
        or "8"
    )
    stop_grace_seconds = max(3.0, stop_grace_seconds)

    if not api_key or not status_url:
        log(
            "Missing CCTV_FRAME_UPLOAD_KEY or status URL. "
            "Set CCTV_FRAME_UPLOAD_URL / CCTV_VIEWER_STATUS_URL in .env"
        )
        return 1

    log("Detection agent started.")
    log(f"  viewer: {status_url}")
    log(f"  scan:   {scan_url}")
    log(f"  poll:   every {poll_seconds:.0f}s | stop grace: {stop_grace_seconds:.0f}s")
    log("detect.py starts ONLY while Open Surveillance is open; otherwise it stays stopped.")

    # Never leave a leftover always-on detect.py running when the agent boots.
    if read_detect_pid() is not None:
        stop_detect("agent startup — waiting for Open Surveillance")

    idle_since: Optional[float] = None
    last_should_run: Optional[bool] = None
    api_fail_streak = 0

    while True:
        if scan_url:
            try:
                process_scan_job(scan_url, api_key)
            except Exception as exc:  # noqa: BLE001
                log(f"Scan job handler error: {exc}")

        status = api_request(status_url, api_key, method="GET", timeout=8)
        if not status or not status.get("success"):
            api_fail_streak += 1
            # Fail closed: never start without a confirmed Open Surveillance heartbeat.
            # If we already lost contact, stop after a few failed polls.
            if api_fail_streak >= 3 and read_detect_pid() is not None:
                stop_detect("viewer status unreachable — stopping detection")
            time.sleep(poll_seconds)
            continue

        api_fail_streak = 0
        # Require an explicit true flag from the website (Open Surveillance open).
        should_run = bool(status.get("should_run") is True or status.get("viewer_active") is True)

        if last_should_run is None or should_run != last_should_run:
            if should_run:
                log("Open Surveillance is open — starting detection")
            else:
                log("Open Surveillance is closed — detection stays/stops off")
            last_should_run = should_run

        if should_run:
            idle_since = None
            if read_detect_pid() is None:
                start_detect()
        else:
            # Stop promptly when the page is not open (no always-on mode).
            if idle_since is None:
                idle_since = time.time()
            if read_detect_pid() is not None and (time.time() - idle_since) >= stop_grace_seconds:
                stop_detect("Open Surveillance is not open")
                idle_since = time.time()

        time.sleep(poll_seconds)

if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except KeyboardInterrupt:
        log("Detection agent stopped by user — stopping detect.py too...")
        stop_detect("agent exiting")
        raise SystemExit(0)
