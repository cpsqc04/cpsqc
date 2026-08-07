#!/usr/bin/env python3
"""
Manage go2rtc for low-latency live view (near Reolink app delay).

Detection (YOLO) keeps using OpenCV RTSP + JPEG upload.
Live view uses go2rtc WebRTC/MSE from the same camera RTSP.
"""

from __future__ import annotations

import json
import os
import socket
import subprocess
import time
import zipfile
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple
from urllib.parse import quote, urlparse, urlunparse
from urllib.request import urlretrieve

ROOT = Path(__file__).resolve().parent
TOOLS_DIR = ROOT / "tools" / "go2rtc"
CLOUDFLARED_DIR = ROOT / "tools" / "cloudflared"
CONFIG_PATH = ROOT / "go2rtc.yaml"
STATUS_PATH = ROOT / "webrtc_status.json"
TUNNEL_URL_PATH = ROOT / "webrtc_tunnel_url.txt"
PID_PATH = ROOT / "go2rtc.pid"
TUNNEL_PID_PATH = ROOT / "cloudflared.pid"
LOG_PATH = ROOT / "go2rtc.log"
STREAM_NAME = "alertara_live"
GO2RTC_WIN64_URL = "https://github.com/AlexxIT/go2rtc/releases/latest/download/go2rtc_win64.zip"
CLOUDFLARED_WIN64_URL = (
    "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"
)
TUNNEL_URL_RE = __import__("re").compile(r"https://[a-z0-9-]+\.trycloudflare\.com", __import__("re").I)


def log(msg: str) -> None:
    stamp = time.strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{stamp}] {msg}"
    print(line, flush=True)
    try:
        with LOG_PATH.open("a", encoding="utf-8") as fh:
            fh.write(line + "\n")
    except OSError:
        pass


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


def windows_hide_kwargs() -> dict:
    startupinfo = None
    creationflags = 0
    if os.name == "nt":
        startupinfo = subprocess.STARTUPINFO()
        startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
        startupinfo.wShowWindow = 0
        creationflags = getattr(subprocess, "CREATE_NO_WINDOW", 0)
    return {"startupinfo": startupinfo, "creationflags": creationflags}


def go2rtc_binary() -> Path:
    if os.name == "nt":
        return TOOLS_DIR / "go2rtc.exe"
    return TOOLS_DIR / "go2rtc"


def ensure_go2rtc_binary() -> Optional[Path]:
    binary = go2rtc_binary()
    if binary.is_file():
        return binary
    if os.name != "nt":
        log("go2rtc binary missing — place it in tools/go2rtc/")
        return None
    TOOLS_DIR.mkdir(parents=True, exist_ok=True)
    zip_path = TOOLS_DIR / "go2rtc_win64.zip"
    try:
        log("Downloading go2rtc (Windows 64-bit)…")
        urlretrieve(GO2RTC_WIN64_URL, zip_path)
        with zipfile.ZipFile(zip_path, "r") as zf:
            zf.extractall(TOOLS_DIR)
        if not binary.is_file():
            for candidate in TOOLS_DIR.rglob("go2rtc.exe"):
                if candidate != binary:
                    candidate.replace(binary)
                    break
        try:
            zip_path.unlink()
        except OSError:
            pass
        if binary.is_file():
            log(f"go2rtc ready: {binary}")
            return binary
    except Exception as exc:  # noqa: BLE001
        log(f"Failed to download go2rtc: {exc}")
    return None if not binary.is_file() else binary


def password_from_camera(camera: dict) -> str:
    pwd = str((camera or {}).get("password") or "")
    if pwd.strip():
        return pwd
    rtsp = str((camera or {}).get("rtspUrl") or "")
    if "://" in rtsp and "@" in rtsp:
        try:
            from urllib.parse import unquote

            parsed = urlparse(rtsp)
            if parsed.password:
                return unquote(parsed.password)
        except Exception:
            pass
    return ""


def build_rtsp(ip: str, port: str, user: str, password: str, path: str) -> str:
    return (
        f"rtsp://{quote(user, safe='')}:{quote(password, safe='')}@"
        f"{ip}:{port}/{path.lstrip('/')}"
    )


def swap_stream_path(rtsp_url: str, stream_type: str) -> str:
    parsed = urlparse(rtsp_url)
    path = parsed.path or ""
    if stream_type == "main":
        path = path.replace("Preview_01_sub", "Preview_01_main").replace(
            "h264Preview_01_sub", "h264Preview_01_main"
        )
        if "main" not in path and "sub" not in path:
            path = "/h264Preview_01_main"
    else:
        path = path.replace("Preview_01_main", "Preview_01_sub").replace(
            "h264Preview_01_main", "h264Preview_01_sub"
        )
        if "main" not in path and "sub" not in path:
            path = "/h264Preview_01_sub"
    return urlunparse(parsed._replace(path=path))


def pick_camera(cameras: list) -> Optional[dict]:
    for cam in cameras:
        if not isinstance(cam, dict):
            continue
        status = str(cam.get("status") or "").lower()
        if status in {"", "online"} and (
            cam.get("ipAddress") or cam.get("rtspUrl")
        ):
            return cam
    for cam in cameras:
        if isinstance(cam, dict) and (cam.get("ipAddress") or cam.get("rtspUrl")):
            return cam
    return None


def camera_rtsp_urls(camera: dict) -> Tuple[str, str]:
    """Return (main_url, sub_url) for live WebRTC (prefer Clear/main like Reolink)."""
    ip = str(camera.get("ipAddress") or "").strip()
    port = str(camera.get("port") or "554").strip() or "554"
    user = str(camera.get("username") or "admin").strip() or "admin"
    password = password_from_camera(camera)
    base = str(camera.get("rtspUrl") or "").strip()

    if ip and password:
        main = build_rtsp(ip, port, user, password, "h264Preview_01_main")
        sub = build_rtsp(ip, port, user, password, "h264Preview_01_sub")
        return main, sub
    if base:
        return swap_stream_path(base, "main"), swap_stream_path(base, "sub")
    return "", ""


def write_go2rtc_config(cameras: Optional[list] = None) -> Dict[str, Any]:
    if cameras is None:
        try:
            cameras = json.loads((ROOT / "cameras.json").read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError, TypeError):
            cameras = []
    if not isinstance(cameras, list):
        cameras = []

    cam = pick_camera(cameras)
    main_url, sub_url = camera_rtsp_urls(cam) if cam else ("", "")
    file_env = load_env(ROOT / ".env")
    transcode = str(
        file_env.get("CCTV_WEBRTC_TRANSCODE", "")
        or os.environ.get("CCTV_WEBRTC_TRANSCODE", "")
        or "false"
    ).strip().lower() in {"1", "true", "yes", "on"}

    sources: List[str] = []
    # Zero-copy first (same path Reolink uses). ffmpeg re-encode of 4K HEVC adds multi-second delay.
    if main_url:
        sources.append(main_url)
    if sub_url:
        sources.append(sub_url)
    if transcode and main_url:
        # Optional last resort only — not used for "match Reolink delay".
        sources.append(f"ffmpeg:{main_url}#video=h264#hardware")

    listen = (
        file_env.get("CCTV_GO2RTC_LISTEN", "")
        or os.environ.get("CCTV_GO2RTC_LISTEN", "")
        or ":1984"
    ).strip() or ":1984"
    lan_ip = local_lan_ip()

    lines = [
        "# Auto-generated by go2rtc_manager.py — do not edit by hand.",
        "# Zero-copy RTSP (no default ffmpeg) for near-Reolink-app latency.",
        "api:",
        f'  listen: "{listen}"',
        '  origin: "*"',
        "rtsp:",
        '  listen: ":8554"',
        "webrtc:",
        '  listen: ":8555"',
        "  candidates:",
        f'    - "{lan_ip}:8555"',
        "    - stun:8555",
        "streams:",
        f"  {STREAM_NAME}:",
    ]
    if sources:
        for src in sources:
            lines.append(f'    - "{src}"')
    else:
        lines.append('    - "rtsp://127.0.0.1:554/h264Preview_01_sub"')

    CONFIG_PATH.write_text("\n".join(lines) + "\n", encoding="utf-8")
    meta = {
        "stream": STREAM_NAME,
        "camera_id": (cam or {}).get("id"),
        "camera_name": (cam or {}).get("name") or (cam or {}).get("cameraId"),
        "has_main": bool(main_url),
        "has_sub": bool(sub_url),
        "transcode": transcode,
        "listen": listen,
        "lan_ip": lan_ip,
        "updated_at": time.strftime("%Y-%m-%d %H:%M:%S"),
    }
    return meta


def read_pid() -> Optional[int]:
    try:
        raw = PID_PATH.read_text(encoding="utf-8").strip()
        pid = int(raw or "0")
        return pid if pid > 0 else None
    except (OSError, ValueError):
        return None


def pid_alive(pid: Optional[int]) -> bool:
    if not pid:
        return False
    try:
        if os.name == "nt":
            out = subprocess.run(
                ["tasklist", "/FI", f"PID eq {pid}"],
                capture_output=True,
                text=True,
                timeout=5,
                **windows_hide_kwargs(),
            )
            return str(pid) in (out.stdout or "")
        os.kill(pid, 0)
        return True
    except Exception:
        return False


def port_open(host: str, port: int, timeout: float = 0.6) -> bool:
    try:
        with socket.create_connection((host, port), timeout=timeout):
            return True
    except OSError:
        return False


def go2rtc_is_running() -> bool:
    if pid_alive(read_pid()):
        return True
    return port_open("127.0.0.1", 1984)


def stop_go2rtc(reason: str = "stop") -> None:
    pid = read_pid()
    log(f"Stopping go2rtc ({reason})…")
    hide = windows_hide_kwargs()
    try:
        if os.name == "nt":
            if pid:
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
                    "Where-Object { $_.Name -match 'go2rtc' -or ($_.CommandLine -and $_.CommandLine -match 'go2rtc') } | "
                    "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }",
                ],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                check=False,
                **hide,
            )
        elif pid:
            try:
                os.kill(pid, 15)
            except OSError:
                pass
    except OSError as exc:
        log(f"stop_go2rtc error: {exc}")
    try:
        if PID_PATH.exists():
            PID_PATH.unlink()
    except OSError:
        pass


def start_go2rtc(force_restart: bool = False) -> bool:
    meta = write_go2rtc_config()
    binary = ensure_go2rtc_binary()
    if not binary:
        write_status(running=False, error="go2rtc binary missing", meta=meta)
        return False

    if go2rtc_is_running():
        if force_restart:
            stop_go2rtc("config refresh")
            time.sleep(0.8)
        else:
            write_status(running=True, error="", meta=meta)
            return True

    log("Starting go2rtc for low-latency live view…")
    try:
        with LOG_PATH.open("a", encoding="utf-8") as log_fh:
            proc = subprocess.Popen(
                [str(binary), "-config", str(CONFIG_PATH)],
                cwd=str(ROOT),
                stdout=log_fh,
                stderr=subprocess.STDOUT,
                stdin=subprocess.DEVNULL,
                close_fds=(os.name != "nt"),
                **windows_hide_kwargs(),
            )
        PID_PATH.write_text(str(proc.pid), encoding="utf-8")
    except OSError as exc:
        log(f"Failed to start go2rtc: {exc}")
        write_status(running=False, error=str(exc), meta=meta)
        return False

    for _ in range(20):
        time.sleep(0.25)
        if port_open("127.0.0.1", 1984):
            log(f"go2rtc listening on :1984 (stream={STREAM_NAME})")
            write_status(running=True, error="", meta=meta)
            return True

    log("go2rtc started but API port 1984 not reachable yet")
    write_status(running=True, error="starting", meta=meta)
    return True


def local_lan_ip() -> str:
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        sock.connect(("8.8.8.8", 80))
        ip = sock.getsockname()[0]
        sock.close()
        return ip
    except OSError:
        return "127.0.0.1"


def public_base_url() -> str:
    file_env = load_env(ROOT / ".env")
    configured = (
        file_env.get("CCTV_WEBRTC_PUBLIC_URL", "")
        or os.environ.get("CCTV_WEBRTC_PUBLIC_URL", "")
        or file_env.get("CCTV_WEBRTC_URL", "")
        or os.environ.get("CCTV_WEBRTC_URL", "")
    ).strip().rstrip("/")
    if configured:
        return configured
    tunnel = read_tunnel_url()
    if tunnel:
        return tunnel
    return f"http://{local_lan_ip()}:1984"


def cloudflared_binary() -> Path:
    if os.name == "nt":
        return CLOUDFLARED_DIR / "cloudflared.exe"
    return CLOUDFLARED_DIR / "cloudflared"


def ensure_cloudflared_binary() -> Optional[Path]:
    binary = cloudflared_binary()
    if binary.is_file():
        return binary
    CLOUDFLARED_DIR.mkdir(parents=True, exist_ok=True)
    try:
        log("Downloading cloudflared (HTTPS tunnel for in-page live view)…")
        urlretrieve(CLOUDFLARED_WIN64_URL if os.name == "nt" else CLOUDFLARED_WIN64_URL, binary)
        if os.name != "nt":
            try:
                os.chmod(binary, 0o755)
            except OSError:
                pass
        if binary.is_file():
            log(f"cloudflared ready: {binary}")
            return binary
    except Exception as exc:  # noqa: BLE001
        log(f"Failed to download cloudflared: {exc}")
    return None if not binary.is_file() else binary


def read_tunnel_url() -> str:
    try:
        url = TUNNEL_URL_PATH.read_text(encoding="utf-8").strip()
        if url.startswith("https://"):
            return url.rstrip("/")
    except OSError:
        pass
    return ""


def write_tunnel_url(url: str) -> None:
    try:
        TUNNEL_URL_PATH.write_text((url or "").strip() + "\n", encoding="utf-8")
    except OSError:
        pass


def tunnel_enabled() -> bool:
    file_env = load_env(ROOT / ".env")
    # Default ON so Hostinger HTTPS can embed the live stream in-page.
    flag = str(
        file_env.get("CCTV_WEBRTC_TUNNEL", "")
        or os.environ.get("CCTV_WEBRTC_TUNNEL", "")
        or "true"
    ).strip().lower()
    return flag not in {"0", "false", "no", "off"}


def stop_cloudflared(reason: str = "stop") -> None:
    pid = None
    try:
        pid = int(TUNNEL_PID_PATH.read_text(encoding="utf-8").strip() or "0")
    except (OSError, ValueError):
        pid = None
    log(f"Stopping cloudflared ({reason})…")
    hide = windows_hide_kwargs()
    try:
        if os.name == "nt":
            if pid:
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
                    "Where-Object { $_.Name -match 'cloudflared' } | "
                    "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }",
                ],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                check=False,
                **hide,
            )
        elif pid:
            try:
                os.kill(pid, 15)
            except OSError:
                pass
    except OSError:
        pass
    try:
        if TUNNEL_PID_PATH.exists():
            TUNNEL_PID_PATH.unlink()
    except OSError:
        pass


def ensure_https_tunnel() -> str:
    """
    Start Cloudflare quick tunnel → https://*.trycloudflare.com → local go2rtc :1984
    so Hostinger HTTPS pages can iframe the live stream (no mixed-content block).
    """
    file_env = load_env(ROOT / ".env")
    configured = (
        file_env.get("CCTV_WEBRTC_PUBLIC_URL", "")
        or os.environ.get("CCTV_WEBRTC_PUBLIC_URL", "")
        or ""
    ).strip().rstrip("/")
    if configured.startswith("https://"):
        write_tunnel_url(configured)
        return configured
    if not tunnel_enabled():
        return read_tunnel_url()

    existing = read_tunnel_url()
    tunnel_pid = None
    if TUNNEL_PID_PATH.is_file():
        try:
            tunnel_pid = int(TUNNEL_PID_PATH.read_text(encoding="utf-8").strip() or "0")
        except ValueError:
            tunnel_pid = None
    if existing and pid_alive(tunnel_pid):
        return existing

    binary = ensure_cloudflared_binary()
    if not binary:
        return existing

    stop_cloudflared("refresh")
    time.sleep(0.4)
    log("Starting Cloudflare HTTPS tunnel for in-page live embed…")
    try:
        with LOG_PATH.open("a", encoding="utf-8") as log_fh:
            proc = subprocess.Popen(
                [
                    str(binary),
                    "tunnel",
                    "--no-autoupdate",
                    "--url",
                    "http://127.0.0.1:1984",
                ],
                cwd=str(ROOT),
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                stdin=subprocess.DEVNULL,
                text=True,
                encoding="utf-8",
                errors="replace",
                **windows_hide_kwargs(),
            )
        TUNNEL_PID_PATH.write_text(str(proc.pid), encoding="utf-8")
    except OSError as exc:
        log(f"Failed to start cloudflared: {exc}")
        return existing

    url = ""
    deadline = time.time() + 45.0
    buf = ""
    while time.time() < deadline and proc.poll() is None:
        try:
            line = proc.stdout.readline() if proc.stdout else ""
        except Exception:
            line = ""
        if line:
            buf += line
            try:
                with LOG_PATH.open("a", encoding="utf-8") as log_fh:
                    log_fh.write(line)
            except OSError:
                pass
            match = TUNNEL_URL_RE.search(buf)
            if match:
                url = match.group(0).rstrip("/")
                break
        else:
            time.sleep(0.2)

    if url:
        write_tunnel_url(url)
        log(f"HTTPS tunnel ready -> {url}")
        return url

    log("cloudflared started but tunnel URL not detected yet")
    return existing


def player_url_for(base: str, stream: str = STREAM_NAME) -> str:
    # MSE over HTTPS tunnel embeds cleanly in Hostinger; WebRTC as secondary.
    return f"{base.rstrip('/')}/stream.html?src={stream}&mode=mse,webrtc"


def write_status(running: bool, error: str = "", meta: Optional[dict] = None) -> dict:
    meta = meta or {}
    lan_ip = str(meta.get("lan_ip") or local_lan_ip())
    tunnel = read_tunnel_url()
    public = public_base_url()
    local_base = "http://127.0.0.1:1984"
    lan_base = f"http://{lan_ip}:1984"
    bases = []
    # Prefer HTTPS first so Open Surveillance (Hostinger) can embed in-page.
    for candidate in (tunnel, public, local_base, lan_base):
        if candidate and candidate not in bases:
            bases.append(candidate)

    primary = bases[0] if bases else lan_base
    payload = {
        "success": True,
        "enabled": True,
        "running": bool(running),
        "stream": STREAM_NAME,
        "base_url": primary,
        "base_urls": bases,
        "player_url": player_url_for(primary),
        "player_urls": [player_url_for(b) for b in bases],
        "localhost_player_url": player_url_for(local_base),
        "lan_player_url": player_url_for(lan_base),
        "tunnel_url": tunnel,
        "webrtc_url": f"{primary}/api/webrtc?src={STREAM_NAME}",
        "ws_url": f"{primary.replace('https://', 'wss://').replace('http://', 'ws://')}/api/ws?src={STREAM_NAME}",
        "listen": meta.get("listen") or ":1984",
        "camera_id": meta.get("camera_id"),
        "camera_name": meta.get("camera_name"),
        "transcode": bool(meta.get("transcode")),
        "error": error or "",
        "updated_at": time.strftime("%Y-%m-%dT%H:%M:%S"),
        "updated_ts": time.time(),
    }
    try:
        STATUS_PATH.write_text(json.dumps(payload, indent=2), encoding="utf-8")
    except OSError:
        pass
    return payload


def ensure_go2rtc(force_config_refresh: bool = False) -> dict:
    """Ensure config is current and process is up. Returns status dict."""
    prev_cfg = CONFIG_PATH.read_text(encoding="utf-8") if CONFIG_PATH.is_file() else ""
    meta = write_go2rtc_config()
    new_cfg = CONFIG_PATH.read_text(encoding="utf-8") if CONFIG_PATH.is_file() else ""
    changed = prev_cfg != new_cfg
    ok = start_go2rtc(force_restart=force_config_refresh or changed)
    if ok:
        ensure_https_tunnel()
        meta["lan_ip"] = meta.get("lan_ip") or local_lan_ip()
    return write_status(running=ok and go2rtc_is_running(), error="" if ok else "not running", meta=meta)


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Manage go2rtc low-latency live view")
    parser.add_argument("action", choices=["start", "stop", "restart", "status", "ensure"])
    args = parser.parse_args()
    if args.action == "stop":
        stop_cloudflared("cli")
        stop_go2rtc("cli")
        print(json.dumps(write_status(running=False, error="stopped"), indent=2))
    elif args.action == "status":
        print(json.dumps(write_status(running=go2rtc_is_running()), indent=2))
    elif args.action == "restart":
        stop_cloudflared("restart")
        stop_go2rtc("restart")
        time.sleep(0.5)
        print(json.dumps(ensure_go2rtc(force_config_refresh=True), indent=2))
    else:
        print(json.dumps(ensure_go2rtc(force_config_refresh=(args.action == "start")), indent=2))
