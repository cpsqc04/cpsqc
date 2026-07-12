#!/usr/bin/env python3
"""
Convert legacy mp4v recordings to browser-playable H.264 (avc1).

Run: py api/convert_recordings.py
"""

from __future__ import annotations

import glob
import os
import sys

import cv2

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RECORDINGS_DIR = os.path.join(ROOT, "recordings")
MIN_BYTES = 1024


def codec_sample(path: str) -> bytes:
    size = os.path.getsize(path)
    with open(path, "rb") as handle:
        head = handle.read(min(1048576, size))
        if size <= 1048576:
            return head
        handle.seek(max(0, size - 1048576))
        tail = handle.read(1048576)
    return head + tail


def uses_legacy_codec(path: str) -> bool:
    sample = codec_sample(path)
    if b"mp4v" in sample or b"MP4V" in sample:
        return True
    return not (b"avc1" in sample or b"h264" in sample or b"H264" in sample)


def convert_file(path: str) -> bool:
    size = os.path.getsize(path)
    if size < MIN_BYTES:
        os.remove(path)
        print(f"Removed empty recording: {os.path.basename(path)}")
        return True

    if not uses_legacy_codec(path):
        print(f"Skip (already browser-ready): {os.path.basename(path)}")
        return True

    temp_path = path + ".converting.mp4"
    if os.path.exists(temp_path):
        os.remove(temp_path)

    cap = cv2.VideoCapture(path)
    if not cap.isOpened():
        print(f"Failed to open: {os.path.basename(path)}")
        return False

    width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH) or 640)
    height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT) or 360)
    fps = cap.get(cv2.CAP_PROP_FPS) or 30
    if fps <= 0:
        fps = 30

    writer = None
    for codec in ("avc1", "H264", "mp4v"):
        fourcc = cv2.VideoWriter_fourcc(*codec)
        candidate = cv2.VideoWriter(temp_path, fourcc, fps, (width, height))
        if candidate.isOpened():
            writer = candidate
            chosen_codec = codec
            break
        candidate.release()

    if writer is None:
        cap.release()
        print(f"Failed to create writer for: {os.path.basename(path)}")
        return False

    frame_count = 0
    while True:
        ok, frame = cap.read()
        if not ok:
            break
        writer.write(frame)
        frame_count += 1

    cap.release()
    writer.release()

    if frame_count == 0 or not os.path.exists(temp_path) or os.path.getsize(temp_path) < MIN_BYTES:
        if os.path.exists(temp_path):
            os.remove(temp_path)
        print(f"Conversion produced no frames: {os.path.basename(path)}")
        return False

    os.replace(temp_path, path)
    print(f"Converted ({chosen_codec}, {frame_count} frames): {os.path.basename(path)}")
    return True


def main() -> int:
    os.chdir(ROOT)
    os.makedirs(RECORDINGS_DIR, exist_ok=True)

    files = sorted(glob.glob(os.path.join(RECORDINGS_DIR, "recording_*.mp4")))
    if not files:
        print("No recordings found.")
        return 0

    converted = 0
    failed = 0
    for path in files:
        if convert_file(path):
            converted += 1
        else:
            failed += 1

    print(f"Done. Processed {len(files)} file(s), failed {failed}.")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
