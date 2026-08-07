#!/usr/bin/env python3
"""
Probe Reolink camera encoding (GetEnc) and recommend RTSP stream + display quality.

Uses the camera LAN HTTP API. Many Reolink firmwares accept the same
user/password query style as Snap (already used by detect.py); newer ones
use Login → token → GetEnc.
"""

from __future__ import annotations

import json
import ssl
import time
from typing import Any, Dict, List, Optional, Tuple
from urllib.error import HTTPError, URLError
from urllib.parse import quote
from urllib.request import Request, urlopen

try:
    import requests as _requests

    HAS_REQUESTS = True
except ImportError:
    HAS_REQUESTS = False

# Avoid SSL verify failures on cameras with self-signed HTTPS certs.
_SSL_CTX = ssl.create_default_context()
_SSL_CTX.check_hostname = False
_SSL_CTX.verify_mode = ssl.CERT_NONE


def _http_exchange(
    url: str,
    payload: Any = None,
    timeout: float = 6.0,
    content_type: str = "application/json",
    method: str = "POST",
) -> Tuple[Optional[Any], str]:
    """
    Returns (parsed_json_or_None, detail).
    detail is empty on success, otherwise a short error reason.
    """
    body = None
    headers = {"Accept": "application/json, text/plain, */*"}
    if payload is not None:
        body = json.dumps(payload).encode("utf-8")
        headers["Content-Type"] = content_type

    if HAS_REQUESTS:
        try:
            resp = _requests.request(
                method,
                url,
                data=body,
                headers=headers,
                timeout=timeout,
                verify=False,
            )
            text = resp.text or ""
            if resp.status_code >= 400:
                return None, f"HTTP {resp.status_code}"
            try:
                return resp.json(), ""
            except Exception:
                return None, f"non-JSON response ({text[:80]!r})"
        except Exception as exc:
            return None, f"{type(exc).__name__}: {exc}"

    try:
        req = Request(url, data=body, headers=headers, method=method)
        with urlopen(req, timeout=timeout, context=_SSL_CTX) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            try:
                return json.loads(raw), ""
            except json.JSONDecodeError:
                return None, f"non-JSON response ({raw[:80]!r})"
    except HTTPError as exc:
        return None, f"HTTP {exc.code}"
    except Exception as exc:
        return None, f"{type(exc).__name__}: {exc}"


def _extract_token(data: Any) -> Optional[str]:
    if not isinstance(data, list) or not data:
        return None
    row = data[0] if isinstance(data[0], dict) else None
    if not row:
        return None
    # Failed login
    code = row.get("code")
    if code is not None and int(code) != 0:
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
    code = row.get("code")
    if code is not None and int(code) != 0:
        return None

    value = row.get("value") if isinstance(row.get("value"), dict) else {}
    enc = value.get("Enc") if isinstance(value.get("Enc"), dict) else None
    if enc is None and isinstance(row.get("initial"), dict):
        enc = row["initial"].get("Enc") if isinstance(row["initial"].get("Enc"), dict) else None
    # Some firmwares nest under range/value differently
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


def _cgi_bases(ip: str) -> List[str]:
    return [
        f"http://{ip}/cgi-bin/api.cgi",
        f"http://{ip}/api.cgi",
        f"https://{ip}/cgi-bin/api.cgi",
        f"https://{ip}/api.cgi",
    ]


def reolink_login(ip: str, username: str, password: str, timeout: float = 6.0) -> Tuple[Optional[str], str]:
    """Return (token, detail)."""
    ip = (ip or "").strip()
    if not ip:
        return None, "missing IP"
    payload = [
        {
            "cmd": "Login",
            "action": 0,
            "param": {"User": {"userName": username or "admin", "password": password or ""}},
        }
    ]
    errors: List[str] = []
    for base in _cgi_bases(ip):
        for ctype in ("application/json", "application/octet-stream"):
            data, err = _http_exchange(
                f"{base}?cmd=Login",
                payload=payload,
                timeout=timeout,
                content_type=ctype,
            )
            if err:
                errors.append(f"Login {ctype} @ {base}: {err}")
                continue
            token = _extract_token(data)
            if token:
                return token, ""
            errors.append(f"Login @ {base}: bad credentials or unexpected response")
    return None, "; ".join(errors[:3]) or "Login failed"


def reolink_get_enc(
    ip: str,
    username: str,
    password: str,
    channel: int = 0,
    timeout: float = 6.0,
) -> Tuple[Optional[Dict[str, Any]], str]:
    """
    Return (enc_dict, detail).
    Tries Snap-style user/password query first (works on many Reolinks),
    then Login+token.
    """
    ip = (ip or "").strip()
    if not ip:
        return None, "missing IP"
    user_q = quote(str(username or ""), safe="")
    pwd_q = quote(str(password or ""), safe="")
    payload_variants = [
        [{"cmd": "GetEnc", "action": 1, "param": {"channel": int(channel)}}],
        [{"cmd": "GetEnc", "action": 0, "param": {"channel": int(channel)}}],
        [{"cmd": "GetEnc", "param": {"channel": int(channel)}}],
    ]
    errors: List[str] = []

    # 1) Direct GetEnc with credentials in query (same pattern as working Snap URLs).
    for base in _cgi_bases(ip):
        for auth_q in (
            f"user={user_q}&password={pwd_q}",
            f"user={user_q}&pwd={pwd_q}",
            f"token=null&user={user_q}&password={pwd_q}",
        ):
            for payload in payload_variants:
                url = f"{base}?cmd=GetEnc&{auth_q}"
                data, err = _http_exchange(url, payload=payload, timeout=timeout)
                if err:
                    errors.append(f"GetEnc-query: {err}")
                    continue
                enc = _extract_enc(data)
                if enc and (enc["mainStream"].get("width") or enc["subStream"].get("width")):
                    return enc, ""
                # Also accept GET without body
            url_get = f"{base}?cmd=GetEnc&channel={int(channel)}&{auth_q}"
            data, err = _http_exchange(url_get, payload=None, timeout=timeout, method="GET")
            if not err:
                enc = _extract_enc(data)
                if enc and (enc["mainStream"].get("width") or enc["subStream"].get("width")):
                    return enc, ""

    # 2) Login → token → GetEnc
    token, login_err = reolink_login(ip, username, password, timeout=timeout)
    if not token:
        errors.append(login_err or "Login failed")
        return None, "; ".join([e for e in errors if e][:4])

    for base in _cgi_bases(ip):
        for payload in payload_variants:
            url = f"{base}?cmd=GetEnc&token={quote(token, safe='')}"
            data, err = _http_exchange(url, payload=payload, timeout=timeout)
            if err:
                errors.append(f"GetEnc-token: {err}")
                continue
            enc = _extract_enc(data)
            if enc and (enc["mainStream"].get("width") or enc["subStream"].get("width")):
                return enc, ""

    return None, "; ".join([e for e in errors if e][:4]) or "GetEnc failed after Login"


def is_h264(vtype: str) -> bool:
    v = (vtype or "").lower()
    return "h264" in v or v in {"avc", "avc1"}


def is_h265(vtype: str) -> bool:
    v = (vtype or "").lower()
    return "h265" in v or "hevc" in v or v in {"h.265"}


def recommend_stream(enc: Dict[str, Any], prefer_main_if_h264: bool = True) -> Tuple[str, str]:
    """
    Pick OpenCV-safe stream.
    Prefer main when it is H.264; otherwise use sub (usually H.264 on Reolink).
    Returns (stream_type, reason).
    """
    main = enc.get("mainStream") if isinstance(enc.get("mainStream"), dict) else {}
    sub = enc.get("subStream") if isinstance(enc.get("subStream"), dict) else {}
    main_type = str(main.get("vType") or "")
    sub_type = str(sub.get("vType") or "")

    if prefer_main_if_h264 and is_h264(main_type):
        return (
            "main",
            f"Main stream is {main_type or 'H.264'} "
            f"{main.get('width', '?')}x{main.get('height', '?')} — using Clear/main.",
        )
    if is_h265(main_type) and (is_h264(sub_type) or not sub_type):
        return (
            "sub",
            f"Main is {main_type or 'H.265'} (OpenCV unstable); "
            f"using Fluent/sub ({sub_type or 'H.264'} "
            f"{sub.get('width', '?')}x{sub.get('height', '?')}).",
        )
    if is_h264(sub_type):
        return (
            "sub",
            f"Using H.264 sub-stream {sub.get('width', '?')}x{sub.get('height', '?')}.",
        )
    if main.get("width"):
        return (
            "main",
            f"No clear codec match; defaulting to main "
            f"{main.get('width')}x{main.get('height')} ({main_type or 'unknown'}).",
        )
    return "sub", "Encoding incomplete; defaulting to sub-stream for stability."


def recommend_display_quality(stream: Dict[str, Any], stream_type: str) -> Dict[str, Any]:
    """Map camera stream resolution → website JPEG relay settings."""
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
    timeout: float = 8.0,
) -> Dict[str, Any]:
    """Full probe → recommendation payload for cameras.json / detect.py."""
    started = time.time()
    if not (password or "").strip():
        return {
            "success": False,
            "message": "Camera password is empty — save the password in Camera Management first.",
            "elapsed_seconds": round(time.time() - started, 2),
        }

    enc, detail = reolink_get_enc(ip, username, password, channel=channel, timeout=timeout)
    if not enc:
        return {
            "success": False,
            "message": (
                "Could not read Reolink encoding (Login/GetEnc failed). "
                "Check LAN IP and camera credentials."
                + (f" Detail: {detail}" if detail else "")
            ),
            "detail": detail,
            "elapsed_seconds": round(time.time() - started, 2),
        }

    if force_main:
        stream_type = "main"
        reason = "Forced main stream via CCTV_USE_MAIN_STREAM."
    else:
        stream_type, reason = recommend_stream(enc)

    chosen = enc["mainStream"] if stream_type == "main" else enc["subStream"]
    display = recommend_display_quality(chosen, stream_type)

    return {
        "success": True,
        "message": reason,
        "channel": enc.get("channel", 0),
        "mainStream": enc.get("mainStream") or {},
        "subStream": enc.get("subStream") or {},
        "recommendedStream": stream_type,
        "reason": reason,
        "displayQuality": display,
        "detectedAt": time.strftime("%Y-%m-%d %H:%M:%S"),
        "elapsed_seconds": round(time.time() - started, 2),
    }


if __name__ == "__main__":
    import argparse
    import os

    parser = argparse.ArgumentParser(description="Probe Reolink GetEnc settings")
    parser.add_argument("--ip", required=True)
    parser.add_argument("--user", default="admin")
    parser.add_argument("--password", default=os.environ.get("REOLINK_PASSWORD", ""))
    parser.add_argument("--channel", type=int, default=0)
    args = parser.parse_args()
    result = probe_reolink_encoding(args.ip, args.user, args.password, channel=args.channel)
    print(json.dumps(result, indent=2))
