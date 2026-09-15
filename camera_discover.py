#!/usr/bin/env python3
"""
Scan the local LAN for likely IP cameras (open RTSP / camera HTTP ports).

Usage:
  py camera_discover.py
  py camera_discover.py --json
  py camera_discover.py --subnet 192.168.1.0/24 --json

Outputs a list of candidates. Credentials are NOT discovered — enter them in Camera Management.
"""

from __future__ import annotations

import argparse
import concurrent.futures
import ipaddress
import json
import os
import socket
import sys
import time
from typing import Dict, List, Optional, Set, Tuple
from urllib.request import Request, urlopen

# Common IP-camera service ports
CAMERA_PORTS = (554, 8554, 80, 443, 8000, 8080, 37777, 34567)
PRIORITY_PORTS = (554, 8554)  # Strong camera signal
HTTP_HINT_PORTS = (80, 443, 8000, 8080)

CAMERA_KEYWORDS = (
    "reolink",
    "hikvision",
    "dahua",
    "amcrest",
    "axis",
    "onvif",
    "ipcam",
    "ip camera",
    "webcam",
    "dvr",
    "nvr",
    "rtsp",
    "cgi-bin",
    "bosch",
    "uniview",
    "foscam",
    "tp-link",
    "tapo",
    "wyze",
    "annke",
    "lorex",
)

# Web/app servers that often share a LAN and must NOT be listed as cameras
# even if something happens to listen on TCP 554.
NON_CAMERA_MARKERS = (
    "apache",
    "nginx",
    "microsoft-iis",
    "iis/",
    "php/",
    "xampp",
    "tomcat",
    "caddy",
    "lighttpd",
    "cloudflare",
    "werkzeug",
    "gunicorn",
    "uvicorn",
    "express",
    "node.js",
    "openresty",
    "cherokee",
    "litespeed",
    "wordpress",
    "phpmyadmin",
)


def local_ipv4_addrs() -> List[str]:
    addrs: List[str] = []
    try:
        hostname = socket.gethostname()
        for info in socket.getaddrinfo(hostname, None, socket.AF_INET):
            ip = info[4][0]
            if ip and not ip.startswith("127."):
                addrs.append(ip)
    except OSError:
        pass

    # UDP trick for primary outbound interface
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        sock.connect(("8.8.8.8", 80))
        ip = sock.getsockname()[0]
        sock.close()
        if ip and not ip.startswith("127.") and ip not in addrs:
            addrs.insert(0, ip)
    except OSError:
        pass

    # Enumerate Windows / multi-NIC addresses more thoroughly.
    try:
        for info in socket.getaddrinfo(None, 0, socket.AF_INET, socket.SOCK_DGRAM):
            ip = info[4][0]
            if ip and not ip.startswith("127.") and ip not in addrs:
                addrs.append(ip)
    except OSError:
        pass

    # Dedupe preserve order
    seen: Set[str] = set()
    out: List[str] = []
    for ip in addrs:
        if ip not in seen:
            seen.add(ip)
            out.append(ip)
    return out


def default_subnets() -> List[str]:
    subnets: List[str] = []
    for ip in local_ipv4_addrs():
        try:
            net = ipaddress.ip_network(f"{ip}/24", strict=False)
            cidr = str(net)
            if cidr not in subnets:
                subnets.append(cidr)
        except ValueError:
            continue
    if not subnets:
        subnets.append("192.168.1.0/24")
    return subnets


def tcp_open(ip: str, port: int, timeout: float = 0.35) -> bool:
    try:
        with socket.create_connection((ip, port), timeout=timeout):
            return True
    except OSError:
        return False


def rtsp_responds(ip: str, port: int, timeout: float = 0.9) -> bool:
    """True only if the service answers with an RTSP protocol response."""
    try:
        with socket.create_connection((ip, port), timeout=timeout) as sock:
            sock.settimeout(timeout)
            payload = f"OPTIONS rtsp://{ip}:{port}/ RTSP/1.0\r\nCSeq: 1\r\nUser-Agent: AlertaraQC-CameraScan/1.0\r\n\r\n"
            sock.sendall(payload.encode("ascii", errors="ignore"))
            data = sock.recv(512)
            if not data:
                return False
            text = data.decode("utf-8", errors="ignore")
            head = text.lstrip()[:48].upper()
            return head.startswith("RTSP/") or "RTSP/1." in head
    except OSError:
        return False


def http_probe(ip: str, port: int, timeout: float = 0.8) -> Tuple[Optional[str], Optional[str]]:
    """
    Returns (camera_hint_or_None, raw_server_banner_or_None).
    camera_hint is only set when the response looks like an IP camera UI/API.
    """
    schemes = ("https", "http") if port == 443 else ("http",)
    for scheme in schemes:
        url = f"{scheme}://{ip}:{port}/"
        try:
            req = Request(url, method="GET", headers={"User-Agent": "AlertaraQC-CameraScan/1.0"})
            with urlopen(req, timeout=timeout) as resp:
                server = (resp.headers.get("Server") or "").strip()
                body = resp.read(4096).decode("utf-8", errors="ignore")
                blob = (server + " " + body).lower()

                if any(marker in blob for marker in NON_CAMERA_MARKERS) and not any(
                    kw in blob for kw in CAMERA_KEYWORDS
                ):
                    return None, server[:80] if server else "web-server"

                for kw in CAMERA_KEYWORDS:
                    if kw in blob:
                        return kw, server[:80] if server else None
                return None, server[:80] if server else None
        except Exception:
            continue
    return None, None


def is_non_camera_banner(banner: Optional[str]) -> bool:
    if not banner:
        return False
    blob = banner.lower()
    return any(marker in blob for marker in NON_CAMERA_MARKERS)


def probe_host(ip: str, timeout: float) -> Optional[Dict]:
    open_ports: List[int] = []
    for port in CAMERA_PORTS:
        if tcp_open(ip, port, timeout=timeout):
            open_ports.append(port)

    if not open_ports:
        return None

    rtsp_ports = [p for p in open_ports if p in PRIORITY_PORTS]
    http_ports = [p for p in open_ports if p in HTTP_HINT_PORTS]

    camera_hint: Optional[str] = None
    server_banner: Optional[str] = None
    for port in http_ports[:3]:
        hint, banner = http_probe(ip, port)
        if banner and not server_banner:
            server_banner = banner
        if hint:
            camera_hint = hint
            break

    # Reject clear web servers (XAMPP/Apache/nginx/etc.) unless they also look like a camera UI.
    if is_non_camera_banner(server_banner) and not camera_hint:
        return None

    rtsp_ok = False
    confirmed_rtsp_port: Optional[int] = None
    for port in rtsp_ports:
        if rtsp_responds(ip, port, timeout=max(0.7, timeout + 0.4)):
            rtsp_ok = True
            confirmed_rtsp_port = port
            break

    # Must have real RTSP or a camera-brand HTTP fingerprint.
    if not rtsp_ok and not camera_hint:
        return None

    if rtsp_ok and camera_hint:
        confidence = "high"
    elif rtsp_ok:
        confidence = "high"
    else:
        confidence = "medium"

    display_hint = camera_hint or (f"rtsp:{confirmed_rtsp_port}" if rtsp_ok else server_banner)

    return {
        "ip": ip,
        "open_ports": open_ports,
        "rtsp_port": confirmed_rtsp_port
        or (554 if 554 in open_ports else (8554 if 8554 in open_ports else 554)),
        "has_rtsp": rtsp_ok,
        "hint": display_hint,
        "confidence": confidence,
        "suggested_stream_type": "mid",
    }


def scan_subnets(subnets: List[str], timeout: float, workers: int) -> List[Dict]:
    hosts: List[str] = []
    local_set = set(local_ipv4_addrs())
    for cidr in subnets:
        try:
            net = ipaddress.ip_network(cidr, strict=False)
        except ValueError:
            continue
        for host in net.hosts():
            ip = str(host)
            if ip in local_set:
                continue
            hosts.append(ip)

    results: List[Dict] = []
    with concurrent.futures.ThreadPoolExecutor(max_workers=max(8, workers)) as pool:
        futures = {pool.submit(probe_host, ip, timeout): ip for ip in hosts}
        for fut in concurrent.futures.as_completed(futures):
            item = fut.result()
            if item:
                results.append(item)

    rank = {"high": 0, "medium": 1, "low": 2}
    results.sort(key=lambda r: (rank.get(r.get("confidence", "low"), 9), r.get("ip", "")))
    return results


def main(argv: Optional[List[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="Discover IP cameras on the local LAN")
    parser.add_argument("--subnet", action="append", help="CIDR to scan (repeatable). Default: local /24")
    parser.add_argument("--timeout", type=float, default=0.35, help="TCP connect timeout seconds")
    parser.add_argument("--workers", type=int, default=64, help="Parallel probe threads")
    parser.add_argument("--json", action="store_true", help="Print JSON only")
    parser.add_argument("--out", help="Also write JSON results to this file")
    args = parser.parse_args(argv)

    subnets = args.subnet or default_subnets()
    started = time.time()
    cameras = scan_subnets(subnets, timeout=max(0.15, args.timeout), workers=args.workers)
    elapsed = round(time.time() - started, 2)

    payload = {
        "success": True,
        "scanned_subnets": subnets,
        "local_ips": local_ipv4_addrs(),
        "count": len(cameras),
        "elapsed_seconds": elapsed,
        "cameras": cameras,
        "note": "Enter camera username/password in Camera Management. RTSP path is brand-specific.",
    }

    if args.out:
        with open(args.out, "w", encoding="utf-8") as fh:
            json.dump(payload, fh, indent=2)

    if args.json:
        print(json.dumps(payload))
    else:
        print(f"Scanned {', '.join(subnets)} in {elapsed}s — found {len(cameras)} candidate(s)")
        for cam in cameras:
            ports = ",".join(str(p) for p in cam.get("open_ports") or [])
            hint = cam.get("hint") or "-"
            print(f"  {cam['ip']:>15}  ports={ports:<20}  conf={cam.get('confidence')}  hint={hint}")

    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except KeyboardInterrupt:
        print("Scan cancelled.", file=sys.stderr)
        raise SystemExit(130)
