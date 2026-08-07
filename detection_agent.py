#!/usr/bin/env python3
"""
On-site CCTV detection agent (leave this running once on the PC).

Always-on monitoring:
  • Keeps detect.py running continuously for 24/7 detection + uploads
  • Also runs Camera Management LAN scans / encoding probes (non-blocking)

Configure in .env (same as detect.py):
  CCTV_FRAME_UPLOAD_KEY=...
  CCTV_FRAME_UPLOAD_URL=https://surveillance.alertaraqc.com/api/cctv_frame_upload.php
"""

from __future__ import annotations

import json
import os
import subprocess
import threading
import time
from pathlib import Path
from typing import Dict, Optional
from urllib.error import URLError
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parent
LOCK_FILE = ROOT / "detect.lock"
LOG_FILE = ROOT / "detection_agent.log"

_start_cooldown_until = 0.0
_scan_lock = threading.Lock()
_scan_in_flight = False
_last_scan_poll_at = 0.0
_last_scan_error_log_at = 0.0
_last_api_error_log: Dict[str, float] = {}


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


def log_rate_limited(key: str, msg: str, interval_sec: float = 60.0) -> None:
    now = time.time()
    last = _last_api_error_log.get(key, 0.0)
    if now - last < interval_sec:
        return
    _last_api_error_log[key] = now
    log(msg)


def windows_hide_kwargs():
    """Flags so child processes never open a Windows Terminal / console window."""
    startupinfo = None
    creationflags = 0
    if os.name == "nt":
        startupinfo = subprocess.STARTUPINFO()
        startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
        startupinfo.wShowWindow = 0  # SW_HIDE
        creationflags = getattr(subprocess, "CREATE_NO_WINDOW", 0)
    return {"startupinfo": startupinfo, "creationflags": creationflags}


def resolve_pythonw() -> list:
    """Prefer windowless Python (pyw/pythonw) so Open Surveillance never pops a console."""
    if os.name != "nt":
        return ["python3"]
    for candidate in (
        ["pyw", "-3"],
        ["pythonw"],
        ["py", "-3"],
    ):
        try:
            subprocess.run(
                candidate + ["-c", "pass"],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                timeout=5,
                **windows_hide_kwargs(),
            )
            return candidate
        except (OSError, subprocess.SubprocessError):
            continue
    return ["py", "-3"]


def pid_alive(pid: int) -> bool:
    if pid <= 0:
        return False
    if os.name == "nt":
        try:
            out = subprocess.check_output(
                ["tasklist", "/FI", f"PID eq {pid}", "/NH"],
                stderr=subprocess.DEVNULL,
                text=True,
                **windows_hide_kwargs(),
            )
            return str(pid) in out and "No tasks" not in out
        except (subprocess.CalledProcessError, OSError):
            return False
    try:
        os.kill(pid, 0)
        return True
    except OSError:
        return False


def detect_process_running() -> bool:
    """True if any detect.py process is alive (even before/without lock file)."""
    if os.name != "nt":
        return read_detect_pid() is not None
    try:
        out = subprocess.check_output(
            [
                "powershell",
                "-NoProfile",
                "-WindowStyle",
                "Hidden",
                "-Command",
                "(Get-CimInstance Win32_Process | "
                "Where-Object { $_.CommandLine -match 'detect\\.py' }).Count",
            ],
            stderr=subprocess.DEVNULL,
            text=True,
            timeout=8,
            **windows_hide_kwargs(),
        )
        return int((out or "0").strip() or "0") > 0
    except (subprocess.CalledProcessError, OSError, ValueError):
        return False


def read_detect_pid() -> Optional[int]:
    if not LOCK_FILE.is_file():
        return None
    try:
        pid = int(LOCK_FILE.read_text(encoding="utf-8").strip() or "0")
    except (OSError, ValueError):
        return None
    if not pid_alive(pid):
        # Don't delete immediately — process may still be booting; only clear if
        # no detect.py process exists at all.
        if detect_process_running():
            return pid if pid > 0 else None
        try:
            if LOCK_FILE.exists():
                LOCK_FILE.unlink()
        except OSError:
            pass
        return None
    return pid


def detection_is_running() -> bool:
    return read_detect_pid() is not None or detect_process_running()


def start_detect() -> None:
    global _start_cooldown_until
    now = time.time()
    if now < _start_cooldown_until:
        return
    if detection_is_running():
        return

    env = os.environ.copy()
    env["CCTV_AGENT_MANAGED"] = "1"
    if os.name == "nt":
        cmd = resolve_pythonw() + ["detect.py"]
    else:
        cmd = ["python3", "detect.py"]

    log("Starting detect.py for continuous monitoring (no console window)...")
    # Cooldown prevents restart spam while YOLO/model boot is still writing the lock.
    _start_cooldown_until = now + 90.0
    try:
        with open(ROOT / "detection_control.log", "a", encoding="utf-8") as log_fh:
            subprocess.Popen(
                cmd,
                cwd=str(ROOT),
                env=env,
                stdout=log_fh,
                stderr=subprocess.STDOUT,
                stdin=subprocess.DEVNULL,
                close_fds=(os.name != "nt"),
                **windows_hide_kwargs(),
            )
    except OSError as exc:
        log(f"Failed to start detect.py: {exc}")
        _start_cooldown_until = now + 15.0
        return

    for _ in range(40):
        time.sleep(0.25)
        if detection_is_running():
            pid = read_detect_pid()
            log(f"detect.py started (pid {pid or 'booting'})")
            return
    log("detect.py start requested (still booting — will not spam-restart)")


def stop_detect(reason: str = "agent request") -> None:
    global _start_cooldown_until
    running = detection_is_running()
    pid = read_detect_pid()
    if not running and pid is None:
        try:
            if LOCK_FILE.exists():
                LOCK_FILE.unlink()
        except OSError:
            pass
        return

    log(f"Auto-stop: {reason} → stopping detect.py...")
    hide = windows_hide_kwargs()
    try:
        if os.name == "nt":
            if pid is not None:
                subprocess.run(
                    ["taskkill", "/F", "/PID", str(pid), "/T"],
                    stdout=subprocess.DEVNULL,
                    stderr=subprocess.DEVNULL,
                    check=False,
                    **hide,
                )
            subprocess.run(
                [
                    "powershell",
                    "-NoProfile",
                    "-WindowStyle",
                    "Hidden",
                    "-Command",
                    "Get-CimInstance Win32_Process | "
                    "Where-Object { $_.CommandLine -match 'detect\\.py' } | "
                    "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }",
                ],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                check=False,
                **hide,
            )
        elif pid is not None:
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
    _start_cooldown_until = 0.0
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
    timeout: float = 8.0,
    quiet: bool = False,
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
        if not quiet:
            # Timeouts on optional scan polls should not flood the console.
            is_timeout = isinstance(exc, TimeoutError) or "timed out" in str(exc).lower()
            if is_timeout or isinstance(exc, URLError):
                log_rate_limited(
                    f"{method}:{url}",
                    f"API {method} {url} failed: {exc}",
                    60.0,
                )
            else:
                log(f"API {method} {url} failed: {exc}")
        return None


def run_camera_discover() -> Dict:
    out_file = ROOT / "camera_scan_result_tmp.json"
    try:
        if out_file.exists():
            out_file.unlink()
    except OSError:
        pass

    py = "pyw" if os.name == "nt" else "python3"
    # Fall back to py if pyw is missing.
    if os.name == "nt":
        try:
            subprocess.run(
                ["pyw", "-3", "-c", "pass"],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                timeout=5,
                **windows_hide_kwargs(),
            )
            cmd = ["pyw", "-3", "camera_discover.py", "--json", "--out", str(out_file)]
        except (OSError, subprocess.SubprocessError):
            cmd = ["py", "-3", "camera_discover.py", "--json", "--out", str(out_file)]
    else:
        cmd = [py, "camera_discover.py", "--json", "--out", str(out_file)]
    try:
        completed = subprocess.run(
            cmd,
            cwd=str(ROOT),
            capture_output=True,
            text=True,
            timeout=180,
            **windows_hide_kwargs(),
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
    # Short timeout — never block the agent loop for 20–30s on Hostinger blips.
    pending = api_request(
        scan_url + "?role=agent",
        api_key,
        method="GET",
        timeout=4.0,
        quiet=False,
    )
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
            timeout=6.0,
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
    done = api_request(
        scan_url + "?role=agent",
        api_key,
        method="POST",
        payload=payload,
        timeout=15.0,
    )
    if done and done.get("success"):
        count = len(payload["cameras"])
        log(f"Camera scan complete: {count} candidate(s)")
    else:
        log("Failed to upload camera scan results")


def request_scan_poll_async(scan_url: str, api_key: str, min_interval: float = 15.0) -> None:
    """Poll scan jobs on a background thread so timeouts never stall detect start/stop."""
    global _scan_in_flight, _last_scan_poll_at
    now = time.time()
    if now - _last_scan_poll_at < min_interval:
        return
    with _scan_lock:
        if _scan_in_flight:
            return
        _scan_in_flight = True
        _last_scan_poll_at = now

    def _worker() -> None:
        global _scan_in_flight
        try:
            process_scan_job(scan_url, api_key)
        except Exception as exc:  # noqa: BLE001
            log_rate_limited("scan-handler", f"Scan job handler error: {exc}", 60.0)
        finally:
            with _scan_lock:
                _scan_in_flight = False

    threading.Thread(target=_worker, name="camera-scan", daemon=True).start()


_enc_lock = threading.Lock()
_enc_in_flight = False
_last_enc_poll_at = 0.0


def password_from_camera(camera: dict) -> str:
    """Prefer explicit password; fall back to RTSP URL userinfo."""
    pwd = str((camera or {}).get("password") or "")
    if pwd.strip():
        return pwd
    rtsp = str((camera or {}).get("rtspUrl") or "")
    if "://" in rtsp and "@" in rtsp:
        try:
            from urllib.parse import unquote, urlparse

            parsed = urlparse(rtsp)
            if parsed.password:
                return unquote(parsed.password)
        except Exception:
            pass
    return ""


def process_encoding_job(encoding_url: str, api_key: str) -> None:
    pending = api_request(
        encoding_url + "?role=agent",
        api_key,
        method="GET",
        timeout=4.0,
    )
    if not pending or not pending.get("success") or not pending.get("has_job"):
        return

    job = pending.get("job") or {}
    job_id = job.get("id")
    status = job.get("status")
    if not job_id or status not in ("pending", "running"):
        return

    if status == "pending":
        claimed = api_request(
            encoding_url + "?role=agent",
            api_key,
            method="POST",
            payload={"action": "claim", "id": job_id, "role": "agent"},
            timeout=6.0,
        )
        if not claimed or not claimed.get("success"):
            return
        log(f"Claimed Reolink encoding probe job {job_id}")

    # Prefer latest Camera Management credentials from Hostinger when available.
    file_env = load_env(ROOT / ".env")
    config_url = (
        file_env.get("CCTV_CAMERAS_CONFIG_URL", "")
        or os.environ.get("CCTV_CAMERAS_CONFIG_URL", "")
        or derive_api_url(
            file_env.get("CCTV_FRAME_UPLOAD_URL", "") or os.environ.get("CCTV_FRAME_UPLOAD_URL", ""),
            "cctv_cameras_config.php",
        )
    ).strip()
    if config_url:
        remote = api_request(config_url, api_key, method="GET", timeout=6.0, quiet=True)
        cams = remote.get("cameras") if isinstance(remote, dict) else None
        if isinstance(cams, list) and cams:
            try:
                (ROOT / "cameras.json").write_text(
                    json.dumps(cams, indent=4, ensure_ascii=False) + "\n",
                    encoding="utf-8",
                )
                log("Synced cameras.json from website before encoding probe")
            except OSError:
                pass

    cameras_path = ROOT / "cameras.json"
    target_id = job.get("cameraId")
    camera = None
    try:
        cameras = json.loads(cameras_path.read_text(encoding="utf-8"))
        if isinstance(cameras, list):
            for cam in cameras:
                if target_id and str(cam.get("id")) != str(target_id) and str(cam.get("cameraId")) != str(target_id):
                    continue
                status_txt = str(cam.get("status") or "").lower()
                if not target_id and status_txt and status_txt != "online":
                    continue
                camera = cam
                if target_id:
                    break
            if camera is None and cameras:
                camera = cameras[0]
    except (OSError, json.JSONDecodeError, TypeError):
        camera = None

    if not camera:
        api_request(
            encoding_url + "?role=agent",
            api_key,
            method="POST",
            payload={
                "action": "complete",
                "role": "agent",
                "id": job_id,
                "success": False,
                "message": "No camera credentials found in local cameras.json",
            },
            timeout=8.0,
        )
        return

    try:
        from reolink_encoding import probe_reolink_encoding
    except ImportError:
        api_request(
            encoding_url + "?role=agent",
            api_key,
            method="POST",
            payload={
                "action": "complete",
                "role": "agent",
                "id": job_id,
                "success": False,
                "message": "reolink_encoding.py missing on on-site PC",
            },
            timeout=8.0,
        )
        return

    ip = str(camera.get("ipAddress") or "").strip()
    user = str(camera.get("username") or "").strip()
    password = password_from_camera(camera)
    port = str(camera.get("port") or "554").strip() or "554"
    if not password:
        api_request(
            encoding_url + "?role=agent",
            api_key,
            method="POST",
            payload={
                "action": "complete",
                "role": "agent",
                "id": job_id,
                "success": False,
                "message": "Camera password is empty on the on-site PC. Re-save the camera password in Camera Management.",
            },
            timeout=8.0,
        )
        return

    log(f"Probing Reolink encoding at {ip}:{port} (user={user})…")
    result = probe_reolink_encoding(ip, user, password, timeout=10.0, rtsp_port=port)
    payload = {
        "action": "complete",
        "role": "agent",
        "id": job_id,
        "success": bool(result.get("success")),
        "message": result.get("message") or result.get("reason"),
        "reason": result.get("reason") or result.get("message"),
        "recommendedStream": result.get("recommendedStream"),
        "mainStream": result.get("mainStream"),
        "subStream": result.get("subStream"),
        "displayQuality": result.get("displayQuality"),
        "detectedAt": result.get("detectedAt"),
        "elapsed_seconds": result.get("elapsed_seconds"),
    }
    done = api_request(
        encoding_url + "?role=agent",
        api_key,
        method="POST",
        payload=payload,
        timeout=12.0,
    )
    if done and done.get("success"):
        log(
            f"Encoding synced: stream={result.get('recommendedStream')} "
            f"({result.get('reason')})"
        )
    else:
        log(f"Encoding probe finished with error: {payload.get('message')}")


def request_encoding_poll_async(encoding_url: str, api_key: str, min_interval: float = 8.0) -> None:
    global _enc_in_flight, _last_enc_poll_at
    now = time.time()
    if now - _last_enc_poll_at < min_interval:
        return
    with _enc_lock:
        if _enc_in_flight:
            return
        _enc_in_flight = True
        _last_enc_poll_at = now

    def _worker() -> None:
        global _enc_in_flight
        try:
            process_encoding_job(encoding_url, api_key)
        except Exception as exc:  # noqa: BLE001
            log_rate_limited("enc-handler", f"Encoding job handler error: {exc}", 60.0)
        finally:
            with _enc_lock:
                _enc_in_flight = False

    threading.Thread(target=_worker, name="encoding-probe", daemon=True).start()


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
    scan_url = (
        file_env.get("CCTV_CAMERA_SCAN_URL", "")
        or os.environ.get("CCTV_CAMERA_SCAN_URL", "")
        or derive_api_url(frame_url, "cctv_camera_scan.php")
    ).strip()
    encoding_url = (
        file_env.get("CCTV_ENCODING_SYNC_URL", "")
        or os.environ.get("CCTV_ENCODING_SYNC_URL", "")
        or derive_api_url(frame_url, "cctv_encoding_sync.php")
    ).strip()
    poll_seconds = float(
        file_env.get("CCTV_AGENT_POLL_SECONDS")
        or os.environ.get("CCTV_AGENT_POLL_SECONDS")
        or "2"
    )
    poll_seconds = max(1.5, poll_seconds)

    if not api_key:
        log("Missing CCTV_FRAME_UPLOAD_KEY in .env")
        return 1

    log("Detection agent started (always-on monitoring).")
    log(f"  scan:   {scan_url or '(none)'}")
    log(f"  encode: {encoding_url or '(none)'}")
    log(f"  poll:   every {poll_seconds:.0f}s")
    log("detect.py stays running continuously — Open Surveillance is view-only.")

    if not detection_is_running():
        start_detect()

    while True:
        if scan_url:
            request_scan_poll_async(scan_url, api_key, min_interval=15.0)
        if encoding_url:
            request_encoding_poll_async(encoding_url, api_key, min_interval=8.0)

        if not detection_is_running():
            log("detect.py not running — restarting for continuous monitoring")
            start_detect()

        time.sleep(poll_seconds)


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except KeyboardInterrupt:
        log("Detection agent stopped by user — stopping detect.py too...")
        stop_detect("agent exiting")
        raise SystemExit(0)
