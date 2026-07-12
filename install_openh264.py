#!/usr/bin/env python3
"""Download and install Cisco OpenH264 1.8.0 for OpenCV H.264 recording on Windows."""

import bz2
import shutil
import sys
import urllib.request
from pathlib import Path

DLL_NAME = "openh264-1.8.0-win64.dll"
DOWNLOAD_URL = "http://ciscobinary.openh264.org/openh264-1.8.0-win64.dll.bz2"


def install_openh264() -> int:
    if sys.platform != "win32":
        print("This installer is for Windows only.")
        return 1

    project_dir = Path(__file__).resolve().parent
    bz2_path = project_dir / f"{DLL_NAME}.bz2"
    dll_path = project_dir / DLL_NAME

    print("Downloading OpenH264 1.8.0 from Cisco...")
    print(f"  {DOWNLOAD_URL}")
    try:
        urllib.request.urlretrieve(DOWNLOAD_URL, bz2_path)
    except Exception as exc:
        print(f"Download failed: {exc}")
        print("You can download manually from:")
        print("  https://github.com/cisco/openh264/releases/tag/v1.8.0")
        return 1

    print("Extracting DLL...")
    try:
        with bz2.open(bz2_path, "rb") as compressed:
            dll_path.write_bytes(compressed.read())
    finally:
        bz2_path.unlink(missing_ok=True)

    targets = [dll_path]

    python_dir = Path(sys.executable).resolve().parent
    python_target = python_dir / DLL_NAME
    if python_target != dll_path:
        shutil.copy2(dll_path, python_target)
        targets.append(python_target)

    try:
        import cv2

        cv2_dir = Path(cv2.__file__).resolve().parent
        cv2_target = cv2_dir / DLL_NAME
        if cv2_target not in targets:
            shutil.copy2(dll_path, cv2_target)
            targets.append(cv2_target)
    except Exception as exc:
        print(f"Warning: could not copy into OpenCV folder: {exc}")

    print("")
    print("OpenH264 installed to:")
    for target in targets:
        print(f"  {target}")
    print("")
    print("Restart start_detection.bat so recordings use H.264 (avc1) without errors.")
    return 0


if __name__ == "__main__":
    raise SystemExit(install_openh264())
