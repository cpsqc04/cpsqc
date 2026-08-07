#!/usr/bin/env python3
"""
Probe Reolink camera encoding and recommend RTSP stream + display quality.

1) Fast HTTP API Login/GetEnc (few attempts, short timeout)
2) Fallback: open RTSP main/sub briefly with OpenCV (works whenever VLC/RTSP works)
"""

from __future__ import annotations

import json
import os
import socket
import ssl
import time
from typing import Any, Dict, List, Optional, Tuple
from urllib.error import HTTPError
from urllib.parse import quote
from urllib.request import Request, urlopen

try:
    import requests as _requests

    HAS_REQUESTS = True
except ImportError:
    HAS_REQUESTS = False

try:
    import cv2

    HAS_CV2 = True
except ImportError:
    HAS_CV2 = False

_SSL_CTX = ssl.create_default_context()
_SSL_CTX.check_hostname = False
_SSL_CTX.verify_mode = ssl.CERT_NONE


def _http_post_raw(
    url: str,
    body_text: str,
    timeout: float = 3.0,
    content_type: Optional[str] = None,
) -> Tuple[Optional[Any], str]:
    """
    POST exactly like working Reolink curl examples:
      curl -d '[{...}]' 'http://ip/cgi-bin/api.cgi?cmd=Login&token=null'
    """
    body = body_text.encode("utf-8")
    headers = {"Accept": "*/*"}
    if content_type:
        headers["Content-Type"] = content_type

    if HAS_REQUESTS:
        try:
            resp = _requests.post(
                url,
                data=body,
                headers=headers,
                timeout=timeout,
                verify=False,
            )
            if resp.status_code >= 400:
                return None, f"HTTP {resp.status_code}"
            try:
                return resp.json(), ""
            except Exception:
                return None, f"non-JSON ({(resp.text or '')[:100]!r})"
        except Exception as exc:
            return None, f"{type(exc).__name__}: {exc}"

    try:
        req = Request(url, data=body, headers=headers, method="POST")
        with urlopen(req, timeout=timeout, context=_SSL_CTX) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            try:
                return json.loads(raw), ""
            except json.JSONDecodeError:
                return None, f"non-JSON ({raw[:100]!r})"
    except HTTPError as exc:
        return None, f"HTTP {exc.code}"
    except Exception as exc:
        return None, f"{type(exc).__name__}: {exc}"


def _http_get(url: str, timeout: float = 3.0) -> Tuple[Optional[Any], str]:
    if HAS_REQUESTS:
        try:
            resp = _requests.get(url, timeout=timeout, verify=False)
            if resp.status_code >= 400:
                return None, f"HTTP {resp.status_code}"
            try:
                return resp.json(), ""
            except Exception:
                return None, f"non-JSON ({(resp.text or '')[:100]!r})"
        except Exception as exc:
            return None, f"{type(exc).__name__}: {exc}"
    try:
        req = Request(url, method="GET")
        with urlopen(req, timeout=timeout, context=_SSL_CTX) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            try:
                return json.loads(raw), ""
            except json.JSONDecodeError:
                return None, f"non-JSON ({raw[:100]!r})"
    except Exception as exc:
        return None, f"{type(exc).__name__}: {exc}"


def _tcp_open(host: str, port: int, timeout: float = 1.0) -> bool:
    try:
        with socket.create_connection((host, port), timeout=timeout):
            return True
    except OSError:
        return False


def _extract_token(data: Any) -> Optional[str]:
    if not isinstance(data, list) or not data:
        return None
    row = data[0] if isinstance(data[0], dict) else None
    if not isinstance(row, dict):
        return None
    if row.get("code") is not None and int(row.get("code")) != 0:
        return None
    value = row.get("value") if isinstance(row.get("value"), dict) else {}
    token_obj = value.get("Token") if isinstance(value.get("Token"), dict) else {}
    name = token_obj.get("name") or token_obj.get("token")
    if name:
        return str(name)
    if row.get("token"):
        return str(row["token"])
    return None


def _stream_info(block: Any) -> Dict[str, Any]:
    if not isinstance(block, dict):
        return {}
    width = int(block.get("width") or 0)
    height = int(block.get("height") or 0)
    vtype = str(block.get("vType") or block.get("type") or "").strip().lower()
    return {
        "width": width,
        "height": height,
        "frameRate": int(block.get("frameRate") or 0),
        "bitRate": int(block.get("bitRate") or 0),
        "vType": vtype,
        "size": str(block.get("size") or (f"{width}*{height}" if width and height else "")),
        "profile": str(block.get("profile") or ""),
    }


def _extract_enc(data: Any) -> Optional[Dict[str, Any]]:
    if not isinstance(data, list) or not data:
        return None
    row = data[0] if isinstance(data[0], dict) else None
    if not isinstance(row, dict):
        return None
    if row.get("code") is not None and int(row.get("code")) != 0:
        return None
    value = row.get("value") if isinstance(row.get("value"), dict) else {}
    enc = value.get("Enc") if isinstance(value.get("Enc"), dict) else None
    if enc is None and isinstance(row.get("initial"), dict):
        enc = row["initial"].get("Enc") if isinstance(row["initial"].get("Enc"), dict) else None
    if enc is None and isinstance(value.get("enc"), dict):
        enc = value["enc"]
    if not isinstance(enc, dict):
        return None
    return {
        "channel": int(enc.get("channel") or 0),
        "audio": enc.get("audio"),
        "mainStream": _stream_info(enc.get("mainStream")),
        "subStream": _stream_info(enc.get("subStream")),
    }


def reolink_login(ip: str, username: str, password: str, timeout: float = 2.5) -> Tuple[Optional[str], str]:
    """Return (token, detail). Few fast attempts only."""
    ip = (ip or "").strip()
    if not ip:
        return None, "missing IP"
    body_text = json.dumps(
        [
            {
                "cmd": "Login",
                "action": 0,
                "param": {
                    "User": {
                        "userName": username or "admin",
                        "password": password or "",
                    }
                },
            }
        ],
        separators=(",", ":"),
    )
    roots = [f"http://{ip}/cgi-bin/api.cgi"]
    if _tcp_open(ip, 443, timeout=0.4):
        roots.append(f"https://{ip}/cgi-bin/api.cgi")

    errors: List[str] = []
    for root in roots:
        url = f"{root}?cmd=Login&token=null"
        data, err = _http_post_raw(url, body_text, timeout=timeout, content_type=None)
        if err:
            errors.append(err)
            continue
        token = _extract_token(data)
        if token:
            return token, ""
        try:
            detail = data[0].get("error", {}).get("detail") if isinstance(data, list) else None
            if detail:
                errors.append(str(detail))
            else:
                errors.append("login rejected")
        except Exception:
            errors.append("login rejected")
    return None, "; ".join(dict.fromkeys(errors))[:240] or "Login failed"


def reolink_get_enc_http(
    ip: str,
    username: str,
    password: str,
    channel: int = 0,
    timeout: float = 2.5,
) -> Tuple[Optional[Dict[str, Any]], str]:
    """
    Fast HTTP probe. Skip entirely if port 80 is closed so RTSP can run within UI timeout.
    """
    ip = (ip or "").strip()
    if not ip:
        return None, "missing IP"
    if not _tcp_open(ip, 80, timeout=0.6) and not _tcp_open(ip, 443, timeout=0.4):
        return None, "HTTP API port closed"

    user_q = quote(str(username or ""), safe="")
    pwd_q = quote(str(password or ""), safe="")
    enc_body = json.dumps(
        [{"cmd": "GetEnc", "action": 1, "param": {"channel": int(channel)}}],
        separators=(",", ":"),
    )
    root = f"http://{ip}/cgi-bin/api.cgi"
    errors: List[str] = []

    # Credential-in-query first (common on Reolink).
    for auth in (
        f"user={user_q}&password={pwd_q}",
        f"token=null&user={user_q}&password={pwd_q}",
    ):
        url = f"{root}?cmd=GetEnc&{auth}"
        data, err = _http_post_raw(url, enc_body, timeout=timeout, content_type=None)
        if not err:
            enc = _extract_enc(data)
            if enc and (enc["mainStream"].get("width") or enc["subStream"].get("width")):
                return enc, "http-query"
        else:
            errors.append(err)

    token, login_err = reolink_login(ip, username, password, timeout=timeout)
    if not token:
        return None, login_err or "; ".join(dict.fromkeys(errors))[:240]

    for url in (
        f"{root}?cmd=GetEnc&token={quote(token, safe='')}",
        f"{root}?cmd=GetEnc&token={quote(token, safe='')}&user={user_q}&password={pwd_q}",
    ):
        data, err = _http_post_raw(url, enc_body, timeout=timeout, content_type=None)
        if err:
            errors.append(err)
            continue
        enc = _extract_enc(data)
        if enc and (enc["mainStream"].get("width") or enc["subStream"].get("width")):
            return enc, "http-token"
    return None, "; ".join(dict.fromkeys(errors))[:240] or "GetEnc failed"


def _fourcc_to_str(value: float) -> str:
    try:
        code = int(value)
        chars = "".join(chr((code >> (8 * i)) & 0xFF) for i in range(4))
        return "".join(c if 32 <= ord(c) < 127 else "?" for c in chars).strip() or ""
    except Exception:
        return ""


def probe_rtsp_streams(
    ip: str,
    username: str,
    password: str,
    port: str = "554",
    timeout_each: float = 3.5,
) -> Tuple[Optional[Dict[str, Any]], str]:
    """
    Open Reolink RTSP paths briefly and read resolution (and codec hint if available).
    This works whenever the camera RTSP URL works in VLC.
    """
    if not HAS_CV2:
        return None, "OpenCV not available for RTSP probe"
    ip = (ip or "").strip()
    port = str(port or "554").strip() or "554"
    user_q = quote(str(username or ""), safe="")
    pwd_q = quote(str(password or ""), safe="")
    os.environ.setdefault(
        "OPENCV_FFMPEG_CAPTURE_OPTIONS",
        "rtsp_transport;tcp|stimeout;2000000|rw_timeout;2000000|max_delay;500000",
    )

    # Sub first (usually H.264 / OpenCV-safe), then main.
    paths = {
        "sub": [
            "h264Preview_01_sub",
            "Preview_01_sub",
        ],
        "main": [
            "h264Preview_01_main",
            "Preview_01_main",
        ],
    }
    found: Dict[str, Dict[str, Any]] = {}

    for stream_type, candidates in paths.items():
        for path in candidates:
            url = f"rtsp://{user_q}:{pwd_q}@{ip}:{port}/{path}"
            cap = None
            try:
                cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
                try:
                    cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
                except Exception:
                    pass
                if not cap.isOpened():
                    continue
                deadline = time.time() + timeout_each
                frame = None
                while time.time() < deadline:
                    ret, frame = cap.read()
                    if ret and frame is not None and getattr(frame, "size", 0) > 0:
                        break
                    time.sleep(0.05)
                if frame is None or getattr(frame, "size", 0) == 0:
                    continue
                height, width = frame.shape[:2]
                fourcc = _fourcc_to_str(cap.get(cv2.CAP_PROP_FOURCC))
                fps = float(cap.get(cv2.CAP_PROP_FPS) or 0)
                vtype = ""
                low = fourcc.lower()
                if "hevc" in low or "h265" in low or "hev1" in low or "hvc1" in low:
                    vtype = "h265"
                elif "avc" in low or "h264" in low or "x264" in low or "avc1" in low:
                    vtype = "h264"
                # Reolink 4K/5MP+ main is usually HEVC even when fourcc is opaque.
                if stream_type == "main" and width >= 2560 and not vtype:
                    vtype = "h265"
                elif stream_type == "sub" and not vtype:
                    vtype = "h264"
                found[stream_type] = {
                    "width": int(width),
                    "height": int(height),
                    "frameRate": int(round(fps)) if fps > 0 else 0,
                    "bitRate": 0,
                    "vType": vtype,
                    "size": f"{width}*{height}",
                    "profile": fourcc or "",
                    "path": path,
                }
                break
            except Exception:
                continue
            finally:
                if cap is not None:
                    try:
                        cap.release()
                    except Exception:
                        pass

    if not found:
        return None, "RTSP probe could not open main or sub stream"

    return {
        "channel": 0,
        "audio": None,
        "mainStream": found.get("main") or {},
        "subStream": found.get("sub") or {},
    }, "rtsp"


def is_h264(vtype: str) -> bool:
    v = (vtype or "").lower()
    return "h264" in v or v in {"avc", "avc1"}


def is_h265(vtype: str) -> bool:
    v = (vtype or "").lower()
    return "h265" in v or "hevc" in v or v in {"h.265", "hev1", "hvc1"}


def recommend_stream(enc: Dict[str, Any], prefer_main_if_h264: bool = True) -> Tuple[str, str]:
    main = enc.get("mainStream") if isinstance(enc.get("mainStream"), dict) else {}
    sub = enc.get("subStream") if isinstance(enc.get("subStream"), dict) else {}
    main_type = str(main.get("vType") or "")
    sub_type = str(sub.get("vType") or "")
    main_w = int(main.get("width") or 0)
    sub_w = int(sub.get("width") or 0)

    if prefer_main_if_h264 and is_h264(main_type) and main_w > 0:
        return (
            "main",
            f"Main stream is {main_type} {main_w}x{main.get('height', '?')} — using Clear/main.",
        )
    if (is_h265(main_type) or main_w >= 2560) and (sub_w > 0 or is_h264(sub_type) or not sub_type):
        return (
            "sub",
            f"Main looks H.265/high-res ({main_type or 'unknown'} {main_w}x{main.get('height', '?')}); "
            f"using Fluent/sub ({sub_type or 'H.264'} {sub_w or '?'}x{sub.get('height', '?')}).",
        )
    if sub_w > 0 and (is_h264(sub_type) or not main_w):
        return (
            "sub",
            f"Using H.264/sub-stream {sub_w}x{sub.get('height', '?')}.",
        )
    if main_w > 0:
        return (
            "main",
            f"Using main {main_w}x{main.get('height', '?')} ({main_type or 'unknown'}).",
        )
    return "sub", "Encoding incomplete; defaulting to sub-stream for stability."


def recommend_display_quality(stream: Dict[str, Any], stream_type: str) -> Dict[str, Any]:
    width = int((stream or {}).get("width") or 0)
    height = int((stream or {}).get("height") or 0)
    fps = int((stream or {}).get("frameRate") or 0)

    if width >= 1920:
        preset = "vlc"
        interval, quality, max_w, live_q = 0.08, 95, min(1920, width), 97
    elif width >= 1280:
        preset = "lan"
        interval, quality, max_w, live_q = 0.10, 93, min(1600, width), 95
    elif width >= 720:
        preset = "balanced"
        interval, quality, max_w, live_q = 0.20, 85, width, 90
    else:
        preset = "balanced"
        interval, quality, max_w, live_q = 0.15, 88, max(width, 640) if width else 960, 92

    return {
        "preset": preset,
        "streamType": stream_type,
        "interval": interval,
        "jpegQuality": quality,
        "maxWidth": max_w,
        "liveJpegQuality": live_q,
        "sourceWidth": width,
        "sourceHeight": height,
        "sourceFps": fps,
    }


def probe_reolink_encoding(
    ip: str,
    username: str,
    password: str,
    channel: int = 0,
    force_main: bool = False,
    timeout: float = 2.5,
    rtsp_port: str = "554",
) -> Dict[str, Any]:
    started = time.time()
    if not (password or "").strip():
        return {
            "success": False,
            "message": "Camera password is empty — save the password in Camera Management first.",
            "elapsed_seconds": round(time.time() - started, 2),
        }

    method = ""
    http_timeout = min(float(timeout or 2.5), 3.0)
    enc, detail = reolink_get_enc_http(
        ip, username, password, channel=channel, timeout=http_timeout
    )
    if enc:
        method = detail or "http"
    else:
        # RTSP fallback — reliable when the live camera URL already works.
        enc, rtsp_detail = probe_rtsp_streams(
            ip, username, password, port=rtsp_port, timeout_each=3.5
        )
        if enc:
            method = rtsp_detail or "rtsp"
            detail = ""
        else:
            return {
                "success": False,
                "message": (
                    "Could not read Reolink encoding (HTTP Login/GetEnc failed; RTSP probe also failed). "
                    "Check LAN IP and camera credentials."
                    + (f" HTTP: {detail}." if detail else "")
                    + (f" RTSP: {rtsp_detail}." if rtsp_detail else "")
                ),
                "detail": f"http={detail}; rtsp={rtsp_detail}",
                "elapsed_seconds": round(time.time() - started, 2),
            }

    if force_main:
        stream_type = "main"
        reason = "Forced main stream via CCTV_USE_MAIN_STREAM."
    else:
        stream_type, reason = recommend_stream(enc)

    chosen = enc["mainStream"] if stream_type == "main" else enc["subStream"]
    if not chosen.get("width"):
        # If recommended stream missing, flip to whatever we actually opened.
        if enc.get("subStream", {}).get("width"):
            stream_type = "sub"
            chosen = enc["subStream"]
            reason = "Falling back to sub-stream (main not readable)."
        elif enc.get("mainStream", {}).get("width"):
            stream_type = "main"
            chosen = enc["mainStream"]
            reason = "Using main stream (sub not readable)."

    display = recommend_display_quality(chosen, stream_type)
    via = f" via {method}" if method else ""
    return {
        "success": True,
        "message": reason + via,
        "reason": reason + via,
        "channel": enc.get("channel", 0),
        "mainStream": enc.get("mainStream") or {},
        "subStream": enc.get("subStream") or {},
        "recommendedStream": stream_type,
        "displayQuality": display,
        "probeMethod": method,
        "detectedAt": time.strftime("%Y-%m-%d %H:%M:%S"),
        "elapsed_seconds": round(time.time() - started, 2),
    }


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Probe Reolink encoding (HTTP + RTSP fallback)")
    parser.add_argument("--ip", required=True)
    parser.add_argument("--user", default="admin")
    parser.add_argument("--password", default=os.environ.get("REOLINK_PASSWORD", ""))
    parser.add_argument("--channel", type=int, default=0)
    parser.add_argument("--port", default="554")
    args = parser.parse_args()
    result = probe_reolink_encoding(
        args.ip, args.user, args.password, channel=args.channel, rtsp_port=args.port
    )
    print(json.dumps(result, indent=2))
