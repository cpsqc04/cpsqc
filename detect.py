#!/usr/bin/env python3
"""
YOLO Object Detection Script for RTSP Camera Stream
Detects: person, group/crowd, vehicle, animal, plant, phone, backpack, suitcase (and weapon when model supports it)
Stable, offline-capable, with auto-reconnection
"""

import cv2
import json
import time
import os
from datetime import datetime
from pathlib import Path
import numpy as np
import tempfile
import shutil
import sys
import warnings
import subprocess
import contextlib
import atexit
from urllib.parse import quote, urlparse
try:
    import requests
    from requests.auth import HTTPDigestAuth, HTTPBasicAuth
    REQUESTS_AVAILABLE = True
except ImportError:
    REQUESTS_AVAILABLE = False

# Suppress OpenCV/FFmpeg warnings and errors - MUST be before cv2 import
warnings.filterwarnings('ignore')
os.environ['OPENCV_LOG_LEVEL'] = 'ERROR'
os.environ['OPENCV_FFMPEG_READ_ATTEMPTS'] = '1'
os.environ['OPENCV_FFMPEG_READ_ATTEMPT_MS'] = '1000'
# Aggressively suppress FFmpeg errors
os.environ['GST_DEBUG'] = '0'
os.environ['GST_DEBUG_NO_COLOR'] = '1'
# Redirect stderr globally to suppress ALL FFmpeg H.264 decoding errors
# This prevents error spam in console while still allowing important errors through
class StderrFilter:
    """Filter stderr to suppress H.264 decoding errors but allow important messages"""
    def __init__(self, original_stderr):
        self.original_stderr = original_stderr
        self.error_count = 0
    
    def write(self, message):
        # Filter out ALL H.264 decoding errors and related FFmpeg noise
        if isinstance(message, str):
            msg_lower = message.lower()
            # Suppress H.264 decoding errors
            if 'h264' in msg_lower and ('error while decoding' in msg_lower or 'error' in msg_lower):
                return
            # Suppress cabac decode errors
            if 'cabac decode' in msg_lower and 'failed' in msg_lower:
                return
            # Suppress any [h264 @ ...] messages (all H.264 related errors)
            if '[h264 @' in message:
                return
            # Suppress bytestream errors
            if 'bytestream' in msg_lower and 'error' in msg_lower:
                return
        # Write everything else to original stderr
        self.original_stderr.write(message)
    
    def flush(self):
        self.original_stderr.flush()
    
    def isatty(self):
        return self.original_stderr.isatty()

# Redirect stderr to filter
_original_stderr = sys.stderr
sys.stderr = StderrFilter(_original_stderr)

# Context manager for additional local stderr suppression
@contextlib.contextmanager
def suppress_stderr():
    """Context manager to suppress stderr output completely"""
    with open(os.devnull, 'w') as devnull:
        old_stderr = sys.stderr
        try:
            sys.stderr = devnull
            yield
        finally:
            sys.stderr = old_stderr

# Try to import ultralytics YOLO, fallback to other options if not available
try:
    from ultralytics import YOLO
    USE_ULTRALYTICS = True
except ImportError:
    try:
        import torch
        from yolov5 import YOLOv5  # Alternative YOLOv5
        USE_ULTRALYTICS = False
    except ImportError:
        print("Error: Please install ultralytics: pip install ultralytics")
        print("Or install yolov5: pip install yolov5")
        exit(1)

# Try to import deepface for face analysis (gender, expression, etc.) - OPTIONAL
# Note: DeepFace requires TensorFlow which may have dependency conflicts
# The system works fine without it using heuristic-based detection
try:
    from deepface import DeepFace
    DEEPFACE_AVAILABLE = True
except ImportError:
    DEEPFACE_AVAILABLE = False
    # Silent - heuristic methods will be used instead

# Configuration
# Main stream for 4K quality: H.264, 3840x2160 (4K UHD)
# Sub-stream (fallback): 640x360, 10fps - lower quality but more stable
# REOLINK cameras use Preview_01_sub (not h264Preview_01_sub)
RTSP_URL = "rtsp://127.0.0.1:554/Preview_01_sub"
PREFER_SUB_STREAM = True  # Sub-stream = lower latency, fewer decode errors on Reolink
RTSP_FLUSH_GRABS = 5  # Drop buffered frames when reading RTSP (reduces lag)
MAX_DECODE_FAILURES_BEFORE_RECONNECT = 8
CAMERAS_FILE = "cameras.json"
ACTIVE_CAMERA = None
DETECTIONS_FILE = "detections.json"
FRAME_FILE = "current_frame.jpg"  # Frame saved for web display
FRAME_FILE_ALT = "current_frame_alt.jpg"  # Alternate file to avoid locks
FRAME_FILE_TEMP = "current_frame_temp.jpg"  # Temporary file for atomic writes
DETECTED_OBJECTS_DIR = "detected_objects"  # Directory for cropped detected object images
LOCK_FILE = "detect.lock"  # Lock file to prevent multiple instances
RECORDINGS_DIR = "recordings"  # Directory for recorded video files
# Display/recording target — use native camera resolution up to this cap (never upscale).
MAX_DISPLAY_WIDTH = 1920
MAX_DISPLAY_HEIGHT = 1080
FRAME_WIDTH = 1280   # Updated from first frame; used for placeholders/recording
FRAME_HEIGHT = 720
# Video recording settings
ENABLE_RECORDING = True  # Set to False to disable recording
RECORDING_FPS = 30  # FPS for RTSP recorded video
HTTP_SNAPSHOT_INTERVAL = 0.35  # Seconds between HTTP snapshots (fallback mode)
HTTP_RECORDING_FPS = 1.0 / HTTP_SNAPSHOT_INTERVAL  # Match snapshot rate for recording duration
LIVE_JPEG_QUALITY = 92  # Higher quality for sharper live web view
RECORDING_CHUNK_DURATION = 300  # Record in 5-minute chunks (seconds)
MIN_RECORDING_DURATION = 120  # Discard fragments shorter than 2 minutes
RECORDING_BUCKET_SECONDS = 300  # Align filenames to 5-minute windows
RECORDING_CODEC = 'avc1'  # H.264 for browser playback (fallback: mp4v)
RECORDING_EXTENSION = '.mp4'  # File extension for recordings
RECORDING_RETENTION_DAYS = 30  # Auto-delete recordings older than 30 days
CONFIDENCE_THRESHOLD = 0.35
PLANT_CONFIDENCE_THRESHOLD = 0.20  # Potted plants are often lower-confidence in YOLO
PHONE_CONFIDENCE_THRESHOLD = 0.25  # Phones are small / often partially occluded
PHONE_YOLO_SCAN_CONF = 0.05  # Low conf for dedicated phone scan passes
PHONE_CONTOUR_CONFIDENCE = 0.42  # Estimated confidence for shape-based phone hits
PHONE_SCAN_IMGSZ = 1280
ENABLE_PHONE_SECONDARY_SCAN = True
PHONE_MODEL_CANDIDATES = ('phone_yolov8.pt', 'yolov8s.pt', 'yolov8m.pt', 'yolov8n.pt')
PHONE_COCO_CLASS_ID = 67
GROUP_MIN_PEOPLE = 2   # Nearby people counted as a group
CROWD_MIN_PEOPLE = 4   # Larger clusters counted as a crowd
<<<<<<< HEAD
BAG_CONFIDENCE_THRESHOLD = 0.25  # Backpacks/suitcases are often partially occluded
BAG_YOLO_SCAN_CONF = 0.12  # Low conf for dedicated bag scan passes
ENABLE_BAG_SECONDARY_SCAN = True
BAG_COCO_CLASS_IDS = [24, 26, 28]  # backpack, handbag, suitcase
SUSPICIOUS_CATEGORIES = frozenset({'crowd', 'group', 'backpack', 'suitcase'})
MAX_PERSON_DETECTIONS = 25  # Keep enough people for crowd counting
MAX_OTHER_DETECTIONS = 15
=======
MAX_PERSON_DETECTIONS = 25  # Keep enough people for crowd counting
MAX_OTHER_DETECTIONS = 10
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
MAX_RECONNECT_ATTEMPTS = 10
RECONNECT_DELAY = 3  # seconds
FRAME_READ_TIMEOUT = 5  # seconds
STREAM_TIMEOUT = 30  # seconds for initial connection
MAX_DETECTED_OBJECT_IMAGES = 20  # Maximum number of object images to keep
FRAME_PROCESS_DELAY = 0.0  # No delay for lowest latency
DETECTION_INTERVAL = 15  # Run detection more often so boxes stay visible
TARGET_FPS = 30  # Target frame rate for smooth real-time video (matches recording FPS)
ENABLE_DETECTION = True  # Set to False to disable detection for absolute lowest latency
FRAME_SAVE_INTERVAL = 1  # Save EVERY frame - CRITICAL for real-time viewing
# Prioritize frame saving over detection for real-time performance
PRIORITIZE_FRAME_SAVING = True  # Save frame BEFORE detection to minimize latency

# Class mapping for YOLO
# COCO dataset classes: 0=person, 2=car, 3=motorcycle, 5=bus, 7=truck (vehicles)
# 14=bird, 15=cat, 16=dog, 17=horse, 18=sheep, 19=cow, 20=elephant, 21=bear, 22=zebra, 23=giraffe (animals)
<<<<<<< HEAD
# 58=potted plant, 67=cell phone, 24=backpack, 26=handbag, 28=suitcase
=======
# 58=potted plant, 67=cell phone, 24=backpack, 28=suitcase
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
CLASS_NAMES = {
    0: "person",
    2: "vehicle",  # car
    3: "vehicle",  # motorcycle
    5: "vehicle",  # bus
    7: "vehicle",  # truck
    14: "animal",  # bird
    15: "animal",  # cat
    16: "animal",  # dog
    17: "animal",  # horse
    18: "animal",  # sheep
    19: "animal",  # cow
    20: "animal",  # elephant
    21: "animal",  # bear
    22: "animal",  # zebra
    23: "animal",  # giraffe
    24: "backpack",  # backpack
<<<<<<< HEAD
    26: "backpack",  # handbag (treated as backpack for suspicious-activity monitoring)
=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
    28: "suitcase",  # suitcase / luggage
    58: "plant",   # potted plant
    67: "phone",   # cell phone / smartphone
}

# Target classes we want to detect
TARGET_CLASSES = ["person", "vehicle", "animal", "plant", "phone", "backpack", "suitcase"]

# For weapon detection, we'll use a separate approach or custom model
# For now, we'll detect weapons as "knife", "gun", etc. if available in model
WEAPON_CLASSES = ["knife", "gun", "pistol", "rifle", "weapon"]

# YOLO class IDs we care about (speeds inference and keeps plant/phone/bag enabled)
<<<<<<< HEAD
YOLO_TARGET_CLASS_IDS = [0, 2, 3, 5, 7, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 26, 28, 58, 67]
=======
YOLO_TARGET_CLASS_IDS = [0, 2, 3, 5, 7, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 28, 58, 67]
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69


def get_rtsp_bases(rtsp_url):
    """Build RTSP base URLs (with and without auth) from configured camera URL."""
    parsed = urlparse(rtsp_url)
    if parsed.scheme != "rtsp" or not parsed.hostname:
        return rtsp_url, rtsp_url

    port = parsed.port or 554
    base_no_auth = f"rtsp://{parsed.hostname}:{port}"

    if parsed.username:
        auth = parsed.username
        if parsed.password:
            auth = f"{auth}:{parsed.password}"
        base_with_auth = f"rtsp://{auth}@{parsed.hostname}:{port}"
    else:
        base_with_auth = base_no_auth

    return base_with_auth, base_no_auth


def build_rtsp_url(ip, port, username, password, stream_type):
    """Build a valid RTSP URL with URL-encoded credentials."""
    port = str(port or "554").strip() or "554"
    suffix = "Preview_01_main" if stream_type == "main" else "Preview_01_sub"
    if username:
        user = quote(str(username).strip(), safe="")
        pwd = quote(str(password).strip(), safe="") if password else ""
        auth = f"{user}:{pwd}" if password else user
        return f"rtsp://{auth}@{ip}:{port}/{suffix}"
    return f"rtsp://{ip}:{port}/{suffix}"


def load_active_camera_config():
    """Load first available camera from cameras.json and derive RTSP source."""
    cameras_path = Path(CAMERAS_FILE)
    if not cameras_path.exists():
        return None

    try:
        cameras = json.loads(cameras_path.read_text(encoding="utf-8"))
    except Exception:
        return None

    if not isinstance(cameras, list) or not cameras:
        return None

    selected = None
    for camera in cameras:
        status = str(camera.get("status", "")).strip().lower()
        if status == "online":
            selected = camera
            break
    if selected is None:
        selected = cameras[0]

    stream_type = str(selected.get("streamType", "sub")).strip().lower()
    ip = str(selected.get("ipAddress", "")).strip()
    port = str(selected.get("port", "554")).strip() or "554"
    username = str(selected.get("username", "")).strip()
    password = str(selected.get("password", "")).strip()

    if ip:
        rtsp_url = build_rtsp_url(ip, port, username, password, stream_type)
    else:
        rtsp_url = str(selected.get("rtspUrl", "")).strip()
        if not rtsp_url:
            return None

    return {
        "camera_id": selected.get("cameraId") or selected.get("id") or "CAMERA",
        "name": selected.get("name") or "Camera",
        "stream_type": stream_type if stream_type in {"main", "sub"} else "sub",
        "rtsp_url": rtsp_url,
        "ipAddress": ip,
        "port": port,
        "username": username,
        "password": password,
    }


def configure_camera_source():
    """Set RTSP source from camera config file when available."""
    global RTSP_URL, PREFER_SUB_STREAM, ACTIVE_CAMERA

    camera_cfg = load_active_camera_config()
    if not camera_cfg:
        print(f"Using fallback RTSP URL from script: {RTSP_URL}")
        return

    ACTIVE_CAMERA = camera_cfg
    RTSP_URL = camera_cfg["rtsp_url"]
    PREFER_SUB_STREAM = camera_cfg["stream_type"] != "main"
    print(f"Using camera {camera_cfg['camera_id']} ({camera_cfg['name']})")
    print(f"Stream type: {camera_cfg['stream_type'].upper()}")
    print(f"RTSP source: {RTSP_URL}")


def get_active_camera_credentials():
    """Return IP/username/password from active camera config."""
    global ACTIVE_CAMERA
    if ACTIVE_CAMERA is None:
        ACTIVE_CAMERA = load_active_camera_config()

    if not ACTIVE_CAMERA:
        parsed = urlparse(RTSP_URL)
        return parsed.hostname or "127.0.0.1", parsed.username or "", parsed.password or ""

    return (
        ACTIVE_CAMERA.get("ipAddress") or urlparse(ACTIVE_CAMERA.get("rtsp_url", RTSP_URL)).hostname or "127.0.0.1",
        ACTIVE_CAMERA.get("username", ""),
        ACTIVE_CAMERA.get("password", ""),
    )

def load_yolo_model():
    """Load YOLO model - works offline after first download"""
    try:
        if USE_ULTRALYTICS:
            # Try to load YOLOv8 model from local cache first
            model_path = 'yolov8n.pt'
            if os.path.exists(model_path):
                print(f"Loading YOLOv8 model from local cache: {model_path}")
                model = YOLO(model_path)
            else:
                print("Model not found locally. Downloading (requires internet on first run only)...")
                model = YOLO('yolov8n.pt')  # Will download if not found
                print("Model downloaded and cached for offline use.")
            print("✓ YOLOv8 model loaded successfully")
            return model
        else:
            # Use YOLOv5
            model_path = 'yolov5s.pt'
            if os.path.exists(model_path):
                print(f"Loading YOLOv5 model from local cache: {model_path}")
                model = YOLOv5(model_path, device='cpu')
            else:
                print("Model not found locally. Downloading...")
                model = YOLOv5('yolov5s.pt', device='cpu')
            print("✓ YOLOv5 model loaded successfully")
            return model
    except Exception as e:
        print(f"✗ Error loading model: {e}")
        print("Note: Model file should be cached locally after first download.")
        print("If this is your first run, ensure you have internet connection.")
        sys.exit(1)

_phone_scan_model = None

def get_phone_scan_model(main_model):
    """Use a stronger/local phone-tuned model when available."""
    global _phone_scan_model
    if _phone_scan_model is not None:
        return _phone_scan_model

    if USE_ULTRALYTICS:
        for model_path in PHONE_MODEL_CANDIDATES:
            if os.path.exists(model_path):
                print(f"Phone scan model: {model_path}")
                _phone_scan_model = YOLO(model_path)
                return _phone_scan_model

    _phone_scan_model = main_model
    return _phone_scan_model

def bbox_area(bbox):
    return max(0, bbox['x2'] - bbox['x1']) * max(0, bbox['y2'] - bbox['y1'])

def bbox_iou(a, b):
    x1 = max(a['x1'], b['x1'])
    y1 = max(a['y1'], b['y1'])
    x2 = min(a['x2'], b['x2'])
    y2 = min(a['y2'], b['y2'])
    inter = max(0, x2 - x1) * max(0, y2 - y1)
    if inter <= 0:
        return 0.0
    union = bbox_area(a) + bbox_area(b) - inter
    return inter / union if union > 0 else 0.0

def bbox_overlaps_any(bbox, others, min_iou=0.35):
    for other in others:
        if bbox_iou(bbox, other) >= min_iou:
            return True
    return False

def clamp_bbox(bbox, width, height):
    return {
        'x1': max(0, min(width, int(bbox['x1']))),
        'y1': max(0, min(height, int(bbox['y1']))),
        'x2': max(0, min(width, int(bbox['x2']))),
        'y2': max(0, min(height, int(bbox['y2']))),
    }

def shrink_bbox(bbox, shrink_x=0.08, shrink_y=0.12, min_size=12):
    """Pull box edges inward so overlays sit tighter on the object."""
    x1, y1, x2, y2 = int(bbox['x1']), int(bbox['y1']), int(bbox['x2']), int(bbox['y2'])
    width = max(1, x2 - x1)
    height = max(1, y2 - y1)
    dx = int(width * shrink_x)
    dy = int(height * shrink_y)

    nx1 = x1 + dx
    ny1 = y1 + dy
    nx2 = x2 - dx
    ny2 = y2 - dy

    if nx2 - nx1 < min_size:
        cx = (x1 + x2) // 2
        nx1 = cx - min_size // 2
        nx2 = nx1 + min_size
    if ny2 - ny1 < min_size:
        cy = (y1 + y2) // 2
        ny1 = cy - min_size // 2
        ny2 = ny1 + min_size

    return {'x1': nx1, 'y1': ny1, 'x2': nx2, 'y2': ny2}

def refine_bbox_to_content(frame, bbox, pad_ratio=0.04):
    """
    Tighten a loose YOLO box using edges/content inside the crop.
    Falls back to the original bbox if refinement is unreliable.
    """
    height, width = frame.shape[:2]
    bbox = clamp_bbox(bbox, width, height)
    x1, y1, x2, y2 = bbox['x1'], bbox['y1'], bbox['x2'], bbox['y2']
    if x2 - x1 < 16 or y2 - y1 < 16:
        return bbox

    crop = frame[y1:y2, x1:x2]
    if crop.size == 0:
        return bbox

    gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
    blur = cv2.GaussianBlur(gray, (3, 3), 0)
    edges = cv2.Canny(blur, 50, 150)
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))
    edges = cv2.dilate(edges, kernel, iterations=1)

    ys, xs = np.where(edges > 0)
    if len(xs) < 20:
        # Fallback: keep darker object mass (phones / electronics)
        _, mask = cv2.threshold(blur, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
        ys, xs = np.where(mask > 0)
        if len(xs) < 20:
            return bbox

    cx1, cx2 = int(xs.min()), int(xs.max()) + 1
    cy1, cy2 = int(ys.min()), int(ys.max()) + 1
    crop_w = max(1, x2 - x1)
    crop_h = max(1, y2 - y1)
    content_area = max(1, (cx2 - cx1) * (cy2 - cy1))
    box_area = crop_w * crop_h

    # Ignore refinement if content fills almost the whole box already,
    # or if the mask collapsed to a tiny noisy blob.
    if content_area >= box_area * 0.92 or content_area < box_area * 0.08:
        return bbox

    pad_x = max(2, int((cx2 - cx1) * pad_ratio))
    pad_y = max(2, int((cy2 - cy1) * pad_ratio))
    refined = {
        'x1': x1 + max(0, cx1 - pad_x),
        'y1': y1 + max(0, cy1 - pad_y),
        'x2': x1 + min(crop_w, cx2 + pad_x),
        'y2': y1 + min(crop_h, cy2 + pad_y),
    }
    return clamp_bbox(refined, width, height)

def tighten_detection_bbox(frame, bbox, category):
    """Category-aware tightening so drawn boxes are not oversized."""
    height, width = frame.shape[:2]
    bbox = clamp_bbox(bbox, width, height)

    if category == 'phone':
        bbox = shrink_bbox(bbox, shrink_x=0.10, shrink_y=0.16, min_size=14)
        bbox = refine_bbox_to_content(frame, bbox, pad_ratio=0.05)
    elif category in ('backpack', 'suitcase'):
        bbox = shrink_bbox(bbox, shrink_x=0.06, shrink_y=0.08, min_size=16)
        bbox = refine_bbox_to_content(frame, bbox, pad_ratio=0.05)
    elif category in ('plant', 'weapon'):
        bbox = shrink_bbox(bbox, shrink_x=0.06, shrink_y=0.08, min_size=16)
        bbox = refine_bbox_to_content(frame, bbox, pad_ratio=0.06)
    elif category in ('vehicle', 'animal'):
        bbox = shrink_bbox(bbox, shrink_x=0.03, shrink_y=0.03, min_size=20)
    else:
        # Persons: slight shrink only (faces/pose need more of the box)
        bbox = shrink_bbox(bbox, shrink_x=0.02, shrink_y=0.02, min_size=24)

    return clamp_bbox(bbox, width, height)

def append_phone_detection(detections, frame, bbox, confidence, class_name='cell phone', source='scan'):
    bbox = tighten_detection_bbox(frame, bbox, 'phone')
    if bbox['x2'] <= bbox['x1'] or bbox['y2'] <= bbox['y1']:
        return detections

    existing_phone_boxes = [d['bbox'] for d in detections if d.get('category') == 'phone']
    if bbox_overlaps_any(bbox, existing_phone_boxes):
        return detections

    detection_id = int(time.time() * 1000) + len(detections) + 1
    image_path = save_detected_object_image(frame, bbox, detection_id, 'phone', None)
    detections.append({
        'id': detection_id,
        'category': 'phone',
        'class': class_name,
        'confidence': round(float(confidence), 2),
        'bbox': bbox,
        'image': image_path,
        'timestamp': datetime.now().isoformat(),
        'source': source,
    })
    return detections

def scan_yolo_phones_in_crop(frame, scan_model, offset_x, offset_y, crop):
    hits = []
    if crop is None or crop.size == 0:
        return hits

    try:
        results = scan_model(
            crop,
            verbose=False,
            conf=PHONE_YOLO_SCAN_CONF,
            classes=[PHONE_COCO_CLASS_ID],
            imgsz=PHONE_SCAN_IMGSZ,
        )
    except Exception:
        return hits

    if not USE_ULTRALYTICS:
        return hits

    for result in results:
        boxes = result.boxes
        if boxes is None:
            continue
        for box in boxes:
            conf = float(box.conf[0])
            if conf < PHONE_CONFIDENCE_THRESHOLD:
                continue
            x1, y1, x2, y2 = box.xyxy[0].tolist()
            hits.append({
                'bbox': {
                    'x1': int(offset_x + x1),
                    'y1': int(offset_y + y1),
                    'x2': int(offset_x + x2),
                    'y2': int(offset_y + y2),
                },
                'confidence': conf,
                'class_name': result.names[int(box.cls[0])],
                'source': 'yolo_phone_scan',
            })
    return hits

def scan_contour_phones_in_crop(frame, offset_x, offset_y, crop):
    hits = []
    if crop is None or crop.size == 0:
        return hits

    ch, cw = crop.shape[:2]
    if ch < 30 or cw < 20:
        return hits

    gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
    blur = cv2.GaussianBlur(gray, (5, 5), 0)
    edges = cv2.Canny(blur, 40, 140)
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))
    edges = cv2.dilate(edges, kernel, iterations=1)
    contours, _ = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    best = None
    best_score = 0.0
    min_area = max(250, int(ch * cw * 0.004))
    max_area = int(ch * cw * 0.35)

    for contour in contours:
        area = cv2.contourArea(contour)
        if area < min_area or area > max_area:
            continue

        rect = cv2.minAreaRect(contour)
        (_, _), (rw, rh), _ = rect
        if rw < 8 or rh < 8:
            continue

        aspect = max(rw, rh) / min(rw, rh)
        if aspect < 1.25 or aspect > 3.0:
            continue

        box = cv2.boxPoints(rect)
        bx1 = int(max(0, min(box[:, 0])))
        by1 = int(max(0, min(box[:, 1])))
        bx2 = int(min(cw, max(box[:, 0])))
        by2 = int(min(ch, max(box[:, 1])))
        if bx2 - bx1 < 10 or by2 - by1 < 14:
            continue

        roi = crop[by1:by2, bx1:bx2]
        if roi.size == 0:
            continue

        gray_roi = cv2.cvtColor(roi, cv2.COLOR_BGR2GRAY)
        std_dev = float(np.std(gray_roi))
        if std_dev < 12:
            continue

        score = area * min(1.0, aspect / 2.0) * min(1.0, std_dev / 40.0)
        if score > best_score:
            best_score = score
            best = (bx1, by1, bx2, by2)

    if best is None:
        return hits

    bx1, by1, bx2, by2 = best
    hits.append({
        'bbox': {
            'x1': int(offset_x + bx1),
            'y1': int(offset_y + by1),
            'x2': int(offset_x + bx2),
            'y2': int(offset_y + by2),
        },
        'confidence': PHONE_CONTOUR_CONFIDENCE,
        'class_name': 'cell phone',
        'source': 'contour_phone_scan',
    })
    return hits

def person_phone_scan_regions(person_bbox, frame_width, frame_height):
    x1 = int(person_bbox['x1'])
    y1 = int(person_bbox['y1'])
    x2 = int(person_bbox['x2'])
    y2 = int(person_bbox['y2'])
    width = max(1, x2 - x1)
    height = max(1, y2 - y1)

    regions = [
        (x1, y1, x1 + int(width * 0.50), y1 + int(height * 0.72)),
        (x2 - int(width * 0.50), y1, x2, y1 + int(height * 0.72)),
        (x1 + int(width * 0.20), y1 + int(height * 0.08), x2 - int(width * 0.20), y1 + int(height * 0.58)),
    ]

    clamped = []
    for rx1, ry1, rx2, ry2 in regions:
        bbox = clamp_bbox({'x1': rx1, 'y1': ry1, 'x2': rx2, 'y2': ry2}, frame_width, frame_height)
        if bbox['x2'] - bbox['x1'] >= 20 and bbox['y2'] - bbox['y1'] >= 20:
            clamped.append(bbox)
    return clamped

def dedupe_phone_hits(phone_hits, min_iou=0.25):
    """Keep highest-confidence phone hits and drop overlapping duplicates."""
    phone_hits = sorted(phone_hits, key=lambda hit: hit['confidence'], reverse=True)
    kept = []
    kept_boxes = []
    for hit in phone_hits:
        bbox = hit['bbox']
        if bbox_overlaps_any(bbox, kept_boxes, min_iou=min_iou):
            continue
        kept.append(hit)
        kept_boxes.append(bbox)
    return kept

def enhance_phone_detections(frame, model, detections):
    """Run extra phone passes because COCO phone class is often missed on CCTV footage."""
    if not ENABLE_PHONE_SECONDARY_SCAN or not USE_ULTRALYTICS:
        return detections

    scan_model = get_phone_scan_model(model)
    frame_height, frame_width = frame.shape[:2]
    phone_hits = []

    person_boxes = [d['bbox'] for d in detections if d.get('category') == 'person' and d.get('bbox')]
    if person_boxes:
        for person_bbox in person_boxes:
            region_hits = []
            for region in person_phone_scan_regions(person_bbox, frame_width, frame_height):
                crop = frame[region['y1']:region['y2'], region['x1']:region['x2']]
                region_hits.extend(
                    scan_yolo_phones_in_crop(frame, scan_model, region['x1'], region['y1'], crop)
                )

            yolo_hits = dedupe_phone_hits(
                [hit for hit in region_hits if hit.get('source') == 'yolo_phone_scan']
            )
            if yolo_hits:
                phone_hits.append(yolo_hits[0])
                continue

            contour_hits = []
            for region in person_phone_scan_regions(person_bbox, frame_width, frame_height):
                crop = frame[region['y1']:region['y2'], region['x1']:region['x2']]
                contour_hits.extend(
                    scan_contour_phones_in_crop(frame, region['x1'], region['y1'], crop)
                )
            contour_hits = dedupe_phone_hits(
                [hit for hit in contour_hits if hit.get('source') == 'contour_phone_scan']
            )
            if contour_hits:
                phone_hits.append(contour_hits[0])
    else:
        phone_hits.extend(
            scan_yolo_phones_in_crop(frame, scan_model, 0, 0, frame)
        )

    for hit in dedupe_phone_hits(phone_hits):
        if hit['confidence'] < PHONE_CONFIDENCE_THRESHOLD:
            continue
        append_phone_detection(
            detections,
            frame,
            hit['bbox'],
            hit['confidence'],
            hit.get('class_name', 'cell phone'),
            hit.get('source', 'scan'),
        )

    return detections

<<<<<<< HEAD
def map_bag_class_to_category(class_name):
    class_lower = str(class_name or '').lower()
    if any(b in class_lower for b in ['suitcase', 'luggage']):
        return 'suitcase'
    if any(b in class_lower for b in ['backpack', 'handbag', 'bag']):
        return 'backpack'
    return None

def append_bag_detection(detections, frame, bbox, confidence, class_name='backpack', source='scan'):
    category = map_bag_class_to_category(class_name) or 'backpack'
    bbox = tighten_detection_bbox(frame, bbox, category)
    if bbox['x2'] <= bbox['x1'] or bbox['y2'] <= bbox['y1']:
        return detections

    existing_bag_boxes = [
        d['bbox'] for d in detections if d.get('category') in ('backpack', 'suitcase') and d.get('bbox')
    ]
    if bbox_overlaps_any(bbox, existing_bag_boxes):
        return detections

    detection_id = int(time.time() * 1000) + len(detections) + 1
    image_path = save_detected_object_image(frame, bbox, detection_id, category, None)
    detections.append({
        'id': detection_id,
        'category': category,
        'class': class_name,
        'confidence': round(float(confidence), 2),
        'bbox': bbox,
        'image': image_path,
        'timestamp': datetime.now().isoformat(),
        'source': source,
    })
    return detections

def scan_yolo_bags_in_crop(frame, scan_model, offset_x, offset_y, crop):
    hits = []
    if crop is None or crop.size == 0:
        return hits

    try:
        results = scan_model(
            crop,
            verbose=False,
            conf=BAG_YOLO_SCAN_CONF,
            classes=BAG_COCO_CLASS_IDS,
            imgsz=960,
        )
    except Exception:
        return hits

    if not USE_ULTRALYTICS:
        return hits

    for result in results:
        boxes = result.boxes
        if boxes is None:
            continue
        for box in boxes:
            conf = float(box.conf[0])
            class_name = result.names[int(box.cls[0])]
            category = map_bag_class_to_category(class_name)
            if category is None or conf < BAG_CONFIDENCE_THRESHOLD:
                continue
            x1, y1, x2, y2 = box.xyxy[0].tolist()
            hits.append({
                'bbox': {
                    'x1': int(offset_x + x1),
                    'y1': int(offset_y + y1),
                    'x2': int(offset_x + x2),
                    'y2': int(offset_y + y2),
                },
                'confidence': conf,
                'class_name': class_name,
                'category': category,
                'source': 'yolo_bag_scan',
            })
    return hits

def person_bag_scan_regions(person_bbox, frame_width, frame_height):
    x1 = int(person_bbox['x1'])
    y1 = int(person_bbox['y1'])
    x2 = int(person_bbox['x2'])
    y2 = int(person_bbox['y2'])
    width = max(1, x2 - x1)
    height = max(1, y2 - y1)

    regions = [
        (x1, y1 + int(height * 0.18), x2, y1 + int(height * 0.72)),
        (x1, y1 + int(height * 0.55), x2, y2),
        (x1 - int(width * 0.08), y1 + int(height * 0.25), x1 + int(width * 0.45), y2),
        (x2 - int(width * 0.45), y1 + int(height * 0.25), x2 + int(width * 0.08), y2),
    ]

    clamped = []
    for rx1, ry1, rx2, ry2 in regions:
        bbox = clamp_bbox({'x1': rx1, 'y1': ry1, 'x2': rx2, 'y2': ry2}, frame_width, frame_height)
        if bbox['x2'] - bbox['x1'] >= 24 and bbox['y2'] - bbox['y1'] >= 24:
            clamped.append(bbox)
    return clamped

def dedupe_bag_hits(bag_hits, min_iou=0.25):
    bag_hits = sorted(bag_hits, key=lambda hit: hit['confidence'], reverse=True)
    kept = []
    kept_boxes = []
    for hit in bag_hits:
        bbox = hit['bbox']
        if bbox_overlaps_any(bbox, kept_boxes, min_iou=min_iou):
            continue
        kept.append(hit)
        kept_boxes.append(bbox)
    return kept

def enhance_bag_detections(frame, model, detections):
    """Run extra bag passes because backpacks/suitcases are often missed on CCTV footage."""
    if not ENABLE_BAG_SECONDARY_SCAN or not USE_ULTRALYTICS:
        return detections

    frame_height, frame_width = frame.shape[:2]
    bag_hits = []

    person_boxes = [d['bbox'] for d in detections if d.get('category') == 'person' and d.get('bbox')]
    if person_boxes:
        for person_bbox in person_boxes:
            for region in person_bag_scan_regions(person_bbox, frame_width, frame_height):
                crop = frame[region['y1']:region['y2'], region['x1']:region['x2']]
                bag_hits.extend(
                    scan_yolo_bags_in_crop(frame, model, region['x1'], region['y1'], crop)
                )
    else:
        bag_hits.extend(scan_yolo_bags_in_crop(frame, model, 0, 0, frame))

    for hit in dedupe_bag_hits(bag_hits):
        append_bag_detection(
            detections,
            frame,
            hit['bbox'],
            hit['confidence'],
            hit.get('class_name', hit.get('category', 'backpack')),
            hit.get('source', 'scan'),
        )

    return detections

=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
def confidence_threshold_for_category(category):
    """Use lower thresholds for small / lower-confidence COCO classes."""
    if category == "plant":
        return PLANT_CONFIDENCE_THRESHOLD
    if category == "phone":
        return PHONE_CONFIDENCE_THRESHOLD
    if category in ("backpack", "suitcase"):
<<<<<<< HEAD
        return BAG_CONFIDENCE_THRESHOLD
=======
        return 0.30
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
    return CONFIDENCE_THRESHOLD

def map_class_to_category(class_id, class_name):
    """Map YOLO class to our categories"""
    if class_id in CLASS_NAMES:
        return CLASS_NAMES[class_id]
    
    class_lower = class_name.lower()
    
    # Check for vehicles
    if any(v in class_lower for v in ["car", "truck", "bus", "motorcycle", "bike", "vehicle"]):
        return "vehicle"
    
    # Check for animals
    if any(a in class_lower for a in ["dog", "cat", "bird", "horse", "sheep", "cow", "animal"]):
        return "animal"
    
    # Check for plants
    if any(p in class_lower for p in ["plant", "potted plant", "flower", "tree", "potted"]):
        return "plant"

    # Check for phones / smartphones
    if any(p in class_lower for p in ["cell phone", "cellphone", "mobile phone", "smartphone", "phone"]):
        return "phone"

    # Check for luggage / bags
<<<<<<< HEAD
    if "backpack" in class_lower or "handbag" in class_lower:
=======
    if "backpack" in class_lower:
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
        return "backpack"
    if any(b in class_lower for b in ["suitcase", "luggage"]):
        return "suitcase"
    
    # Check for weapons
    if any(w in class_lower for w in WEAPON_CLASSES):
        return "weapon"
    
    # Check for person
    if "person" in class_lower or "people" in class_lower:
        return "person"
    
    return None

def detect_face_in_bbox(frame, bbox):
    """Detect face within person bounding box"""
    try:
        x1, y1, x2, y2 = int(bbox['x1']), int(bbox['y1']), int(bbox['x2']), int(bbox['y2'])
        height, width = frame.shape[:2]
        x1 = max(0, min(x1, width - 1))
        y1 = max(0, min(y1, height - 1))
        x2 = max(x1 + 1, min(x2, width))
        y2 = max(y1 + 1, min(y2, height))
        
        # Crop person region (upper 35% typically contains face - tighter region for accuracy)
        person_crop = frame[y1:y2, x1:x2]
        person_height = y2 - y1
        face_region_y1 = y1
        face_region_y2 = min(y2, y1 + int(person_height * 0.35))  # Upper 35% for face (tighter, more accurate)
        
        face_region = frame[face_region_y1:face_region_y2, x1:x2]
        
        if face_region.size == 0:
            return None
        
        # Use OpenCV's face detector (Haar Cascade - optimized for accuracy)
        try:
            face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
            gray = cv2.cvtColor(face_region, cv2.COLOR_BGR2GRAY)
            
            # Use more aggressive detection parameters for better accuracy
            # scaleFactor: 1.05 (smaller = more detection scales, slower but more accurate)
            # minNeighbors: 5 (higher = fewer false positives, more accurate)
            # minSize: (40, 40) - larger minimum size for better quality
            faces = face_cascade.detectMultiScale(
                gray, 
                scaleFactor=1.05,  # More precise scaling
                minNeighbors=5,    # Higher threshold for accuracy
                minSize=(40, 40),  # Larger minimum face size
                flags=cv2.CASCADE_SCALE_IMAGE
            )
            
            if len(faces) > 0:
                # Get largest face (most likely to be the actual face)
                largest_face = max(faces, key=lambda rect: rect[2] * rect[3])
                fx, fy, fw, fh = largest_face
                
                # Minimal padding to ensure full face is captured, but keep it very tight
                padding_w = int(fw * 0.1)  # 10% padding width (tighter)
                padding_h = int(fh * 0.1)  # 10% padding height (tighter)
                
                # Adjust coordinates to full frame with minimal padding
                face_x1 = max(x1, x1 + fx - padding_w)
                face_y1 = max(y1, y1 + fy - padding_h)
                face_x2 = min(x2, x1 + fx + fw + padding_w)
                face_y2 = min(y2, y1 + fy + fh + padding_h)
                
                # Ensure we don't go beyond person bounding box (face should be within person)
                face_x1 = max(x1, face_x1)
                face_y1 = max(y1, face_y1)
                face_x2 = min(x2, face_x2)
                face_y2 = min(y2, face_y2)
                
                # Ensure minimum size for face crop
                if (face_x2 - face_x1) < 40 or (face_y2 - face_y1) < 40:
                    # Face too small, expand slightly
                    center_x = (face_x1 + face_x2) // 2
                    center_y = (face_y1 + face_y2) // 2
                    face_x1 = max(x1, center_x - 40)
                    face_y1 = max(y1, center_y - 40)
                    face_x2 = min(x2, center_x + 40)
                    face_y2 = min(y2, center_y + 40)
                
                return (face_x1, face_y1, face_x2, face_y2)
        except:
            pass
        
        # Fallback: estimate face region (upper center of person, tight crop)
        face_width = int((x2 - x1) * 0.5)   # 50% width (tighter)
        face_height = int((y2 - y1) * 0.25)  # 25% height (tighter)
        face_x1 = x1 + int((x2 - x1 - face_width) / 2)
        face_y1 = y1 + int((y2 - y1) * 0.08)  # Start 8% from top (tighter)
        face_x2 = face_x1 + face_width
        face_y2 = face_y1 + face_height
        return (face_x1, face_y1, face_x2, face_y2)
    except:
        return None

def analyze_person_attributes(frame, person_bbox, face_bbox=None):
    """Analyze person attributes: gender, expression, accessories, clothes color"""
    attributes = {
        "gender": "Unknown",
        "expression": "calm",
        "accessories": [],
        "clothes_color": "Unknown",
        "facial_hair": "None",
    }
    
    try:
        x1, y1, x2, y2 = int(person_bbox['x1']), int(person_bbox['y1']), int(person_bbox['x2']), int(person_bbox['y2'])
        height, width = frame.shape[:2]
        x1 = max(0, min(x1, width - 1))
        y1 = max(0, min(y1, height - 1))
        x2 = max(x1 + 1, min(x2, width))
        y2 = max(y1 + 1, min(y2, height))
        
        person_crop = frame[y1:y2, x1:x2]
        person_height = y2 - y1
        person_width = x2 - x1
        
        # Use DeepFace for gender and expression if available (optional)
        if DEEPFACE_AVAILABLE and face_bbox:
            try:
                fx1, fy1, fx2, fy2 = face_bbox
                face_crop = frame[fy1:fy2, fx1:fx2]
                if face_crop.size > 0 and face_crop.shape[0] > 30 and face_crop.shape[1] > 30:
                    # Analyze with DeepFace (may be slow, so we catch exceptions)
                    with suppress_stderr():
                        analysis = DeepFace.analyze(face_crop, 
                                                   actions=['gender', 'emotion'], 
                                                   enforce_detection=False,
                                                   silent=True)
                    if isinstance(analysis, list):
                        analysis = analysis[0]
                    
                    # Extract gender
                    if 'gender' in analysis:
                        gender_data = analysis['gender']
                        attributes["gender"] = max(gender_data.items(), key=lambda x: x[1])[0]
                    
                    # Extract expression (map emotion to expression)
                    if 'emotion' in analysis:
                        emotion_data = analysis['emotion']
                        # Map emotions to requested expressions
                        emotion_mapping = {
                            'happy': 'happy',
                            'sad': 'sad',
                            'angry': 'angry',
                            'fear': 'anxious',
                            'surprise': 'anxious',
                            'neutral': 'calm',
                            'disgust': 'angry'
                        }
                        dominant_emotion = max(emotion_data.items(), key=lambda x: x[1])[0]
                        attributes["expression"] = emotion_mapping.get(dominant_emotion, 'calm')
            except:
                pass  # Fallback to heuristic methods
        
        # Enhanced heuristic-based gender detection (works without DeepFace)
        if face_bbox and (not DEEPFACE_AVAILABLE or attributes["gender"] == "Unknown"):
            try:
                fx1, fy1, fx2, fy2 = face_bbox
                face_crop = frame[fy1:fy2, fx1:fx2]
                if face_crop.size > 0 and face_crop.shape[0] > 30 and face_crop.shape[1] > 30:
                    face_height = fy2 - fy1
                    face_width = fx2 - fx1
                    
                    # Gender detection using multiple facial features
                    gray_face = cv2.cvtColor(face_crop, cv2.COLOR_BGR2GRAY)
                    
                    # Feature 1: Face width-to-height ratio
                    face_ratio = face_width / face_height if face_height > 0 else 1.0
                    
                    # Feature 2: Jawline analysis (lower face region)
                    lower_face_y_start = int(face_height * 0.5)
                    lower_face = face_crop[lower_face_y_start:, :]
                    lower_variance = 0
                    if lower_face.size > 0:
                        lower_gray = cv2.cvtColor(lower_face, cv2.COLOR_BGR2GRAY)
                        lower_variance = np.var(lower_gray)
                    
                    # Feature 3: Cheekbone analysis (middle face region)
                    cheek_region_y1 = int(face_height * 0.3)
                    cheek_region_y2 = int(face_height * 0.6)
                    cheek_region = face_crop[cheek_region_y1:cheek_region_y2, :]
                    cheek_width = 0
                    if cheek_region.size > 0:
                        cheek_gray = cv2.cvtColor(cheek_region, cv2.COLOR_BGR2GRAY)
                        # Detect cheek width (brightness pattern)
                        cheek_edges = cv2.Canny(cheek_gray, 50, 150)
                        cheek_width = np.sum(cheek_edges > 0)
                    
                    # Feature 4: Forehead analysis (upper face region)
                    forehead_region = face_crop[:int(face_height * 0.4), :]
                    forehead_smoothness = 0
                    if forehead_region.size > 0:
                        forehead_gray = cv2.cvtColor(forehead_region, cv2.COLOR_BGR2GRAY)
                        # Females typically have smoother foreheads
                        forehead_smoothness = 1.0 / (np.var(forehead_gray) + 1)
                    
                    # Feature 5: Overall face structure (square vs oval)
                    face_aspect = face_width / face_height
                    
                    # Feature 6: Hair length analysis (important for gender detection)
                    # Check region above face for hair length
                    hair_length_score = 0  # 0-4 score: higher = longer hair = more likely female
                    px1, py1, px2, py2 = int(person_bbox['x1']), int(person_bbox['y1']), int(person_bbox['x2']), int(person_bbox['y2'])
                    
                    # Analyze hair region: above face, extending to sides
                    height, width = frame.shape[:2]
                    hair_region_y1 = max(0, fy1 - int(face_height * 0.5))
                    hair_region_y2 = fy1
                    hair_region_x1 = max(0, fx1 - int(face_width * 0.3))
                    hair_region_x2 = min(width, fx2 + int(face_width * 0.3))
                    hair_region = frame[hair_region_y1:hair_region_y2, hair_region_x1:hair_region_x2]
                    
                    if hair_region.size > 0 and hair_region.shape[0] > 10:
                        hair_gray = cv2.cvtColor(hair_region, cv2.COLOR_BGR2GRAY)
                        
                        # Analyze hair characteristics
                        # Long hair typically extends more vertically and has more texture
                        # Short hair is more uniform and compact
                        
                        # 1. Vertical extent: long hair extends further down
                        hair_vertical_ratio = (fy1 - hair_region_y1) / face_height if face_height > 0 else 0
                        
                        # 2. Texture analysis: long hair has more variation (edges)
                        hair_edges = cv2.Canny(hair_gray, 30, 100)
                        hair_texture_density = np.sum(hair_edges > 0) / (hair_region.shape[0] * hair_region.shape[1])
                        
                        # 3. Brightness variation: long hair often has more highlights/shadows
                        hair_brightness_std = np.std(hair_gray)
                        
                        # 4. Check for hair extending beyond face width (longer hair flows more)
                        hair_width_ratio = (hair_region_x2 - hair_region_x1) / face_width if face_width > 0 else 1.0
                        
                        # Scoring for hair length (higher = longer = more likely female)
                        if hair_vertical_ratio > 0.4:  # Hair extends well above face
                            hair_length_score += 1
                        if hair_texture_density > 0.12:  # High texture (longer hair has more detail)
                            hair_length_score += 1
                        if hair_brightness_std > 25:  # High variation (highlights/shadows)
                            hair_length_score += 1
                        if hair_width_ratio > 1.4:  # Hair extends beyond face width
                            hair_length_score += 1
                    
                    # Scoring system for gender detection (including hair length)
                    male_score = 0
                    female_score = 0
                    
                    # Male indicators
                    if face_ratio > 0.78:  # Wider face
                        male_score += 2
                    elif face_ratio < 0.72:  # Narrower face
                        female_score += 2
                    
                    if lower_variance > 500:  # Defined jawline
                        male_score += 2
                    elif lower_variance < 300:  # Softer jawline
                        female_score += 1
                    
                    if face_aspect > 0.85:  # Square face shape
                        male_score += 1
                    elif face_aspect < 0.75:  # Oval face shape
                        female_score += 1
                    
                    if cheek_width < face_width * 0.3:  # Narrower cheekbones
                        female_score += 1
                    
                    if forehead_smoothness > 0.01:  # Smoother forehead
                        female_score += 1
                    
                    # Hair length indicators (important for gender detection)
                    if hair_length_score <= 1:  # Short hair (typical male)
                        male_score += 2
                    elif hair_length_score >= 3:  # Long hair (typical female)
                        female_score += 2
                    
                    # Determine gender based on scores
                    if male_score > female_score and male_score >= 2:
                        attributes["gender"] = "Male"
                    elif female_score > male_score and female_score >= 2:
                        attributes["gender"] = "Female"
                    else:
                        # If scores are close, use hair length as strong tiebreaker
                        if hair_length_score <= 1:
                            attributes["gender"] = "Male"
                        elif hair_length_score >= 3:
                            attributes["gender"] = "Female"
                        # Otherwise use face ratio as final tiebreaker
                        elif face_ratio > 0.75:
                            attributes["gender"] = "Male"
                        elif face_ratio < 0.70:
                            attributes["gender"] = "Female"
                        else:
                            attributes["gender"] = "Unknown"
                    
                    # Expression detection using facial features (heuristic)
                    # Analyze mouth region for expression
                    mouth_region = face_crop[int(face_height * 0.6):int(face_height * 0.85), 
                                            int(face_width * 0.2):int(face_width * 0.8)]
                    if mouth_region.size > 0:
                        mouth_gray = cv2.cvtColor(mouth_region, cv2.COLOR_BGR2GRAY)
                        
                        # Detect mouth corners (for smile detection)
                        edges = cv2.Canny(mouth_gray, 30, 100)
                        edge_points = np.where(edges > 0)
                        
                        if len(edge_points[0]) > 0:
                            # Check if mouth corners curve upward (happy) or downward (sad)
                            mouth_center_y = mouth_region.shape[0] // 2
                            upper_edges = np.sum(edge_points[0] < mouth_center_y)
                            lower_edges = np.sum(edge_points[0] > mouth_center_y)
                            
                            if upper_edges > lower_edges * 1.5:
                                attributes["expression"] = "happy"
                            elif lower_edges > upper_edges * 1.5:
                                attributes["expression"] = "sad"
                            else:
                                # Check eyebrow region for angry/anxious
                                eyebrow_region = face_crop[int(face_height * 0.15):int(face_height * 0.4), :]
                                if eyebrow_region.size > 0:
                                    eyebrow_gray = cv2.cvtColor(eyebrow_region, cv2.COLOR_BGR2GRAY)
                                    eyebrow_edges = cv2.Canny(eyebrow_gray, 40, 120)
                                    eyebrow_density = np.sum(eyebrow_edges > 0) / (eyebrow_region.shape[0] * eyebrow_region.shape[1])
                                    
                                    if eyebrow_density > 0.2:
                                        attributes["expression"] = "angry"
                                    else:
                                        attributes["expression"] = "calm"
                                else:
                                    attributes["expression"] = "calm"
                        else:
                            attributes["expression"] = "calm"
            except:
                pass  # Keep default values
        
        # Improved accessories detection (more accurate)
        if face_bbox:
            fx1, fy1, fx2, fy2 = face_bbox
            face_crop = frame[fy1:fy2, fx1:fx2]
            if face_crop.size > 0 and face_crop.shape[0] > 30 and face_crop.shape[1] > 30:
                face_height = fy2 - fy1
                face_width = fx2 - fx1
                
                # Improved mask detection (lower face region - mouth/nose area)
                lower_face_y1 = int(face_height * 0.55)
                lower_face = face_crop[lower_face_y1:, :]
                if lower_face.size > 0 and lower_face.shape[0] > 10:
                    lower_face_gray = cv2.cvtColor(lower_face, cv2.COLOR_BGR2GRAY)
                    avg_brightness = np.mean(lower_face_gray)
                    brightness_std = np.std(lower_face_gray)
                    
                    # Stricter mask detection: dark area with low variance (uniform dark color)
                    # Mask should cover center region (nose/mouth) uniformly
                    if avg_brightness < 65 and brightness_std < 20:  # Stricter thresholds
                        # Additional check: mask covers nose/mouth region uniformly
                        center_region = lower_face[:, int(lower_face.shape[1] * 0.25):int(lower_face.shape[1] * 0.75)]
                        if center_region.size > 0:
                            center_gray = cv2.cvtColor(center_region, cv2.COLOR_BGR2GRAY)
                            center_brightness = np.mean(center_gray)
                            center_std = np.std(center_gray)
                            # Mask should be uniformly dark in center region
                            if center_brightness < 55 and center_std < 18:
                                # Verify it's not just shadow (mask should cover most of lower face)
                                coverage_ratio = np.sum(center_gray < 70) / center_gray.size
                                if coverage_ratio > 0.6:  # At least 60% of center region is dark (mask coverage)
                                    attributes["accessories"].append("mask")
                
                # Stricter glasses detection (eye region - upper 60% of face)
                upper_face_y2 = int(face_height * 0.65)
                upper_face = face_crop[:upper_face_y2, :]
                if upper_face.size > 0 and upper_face.shape[0] > 15:
                    gray = cv2.cvtColor(upper_face, cv2.COLOR_BGR2GRAY)
                    
                    # Look for rectangular/oval shapes typical of glasses frames
                    edges = cv2.Canny(gray, 50, 150)
                    
                    # Check eye region specifically (middle horizontal band where glasses sit)
                    eye_region_y1 = int(upper_face.shape[0] * 0.35)
                    eye_region_y2 = int(upper_face.shape[0] * 0.65)
                    eye_region = edges[eye_region_y1:eye_region_y2, :]
                    eye_edge_density = np.sum(eye_region > 0) / (eye_region.shape[0] * eye_region.shape[1])
                    
                    # Stricter glasses detection: Must have high edge density AND frame-like patterns
                    # Glasses typically create two oval/rectangular regions (lenses)
                    if eye_edge_density > 0.18:  # Higher threshold to reduce false positives
                        # Check for frame patterns (horizontal lines at top/bottom of eye region)
                        eye_region_gray = gray[eye_region_y1:eye_region_y2, :]
                        avg_brightness = np.mean(eye_region_gray)
                        
                        # Look for distinct frame edges (strong horizontal lines)
                        horizontal_lines = cv2.HoughLinesP(edges[eye_region_y1:eye_region_y2, :], 1, np.pi/180, 
                                                          threshold=int(eye_region.shape[1] * 0.3), 
                                                          minLineLength=int(eye_region.shape[1] * 0.4), 
                                                          maxLineGap=5)
                        
                        # Glasses should have frame structure (horizontal lines for top/bottom of frames)
                        has_frame_structure = horizontal_lines is not None and len(horizontal_lines) >= 2
                        
                        # Additional check: brightness contrast (glasses often have darker frames)
                        brightness_std = np.std(eye_region_gray)
                        
                        if has_frame_structure or (eye_edge_density > 0.25 and brightness_std > 15):
                            # Dark area = sunglasses, lighter with strong edges = eyeglasses
                            if avg_brightness < 70 and brightness_std < 20:  # Dark and uniform = sunglasses
                                attributes["accessories"].append("sunglasses")
                            elif eye_edge_density > 0.22 and brightness_std > 20:  # Strong edges with contrast = eyeglasses
                                attributes["accessories"].append("eyeglasses")
        
        # Stricter hat/cap detection (top of head region, more accurate)
        if face_bbox:
            fx1, fy1, fx2, fy2 = face_bbox
            # Check region above detected face for hat/cap (wider region for better detection)
            face_height = fy2 - fy1
            head_region_y1 = max(0, fy1 - int(face_height * 0.4))
            head_region_y2 = fy1
            head_region_width = fx2 - fx1
            head_region_x1 = max(0, fx1 - int(head_region_width * 0.2))
            head_region_x2 = min(width, fx2 + int(head_region_width * 0.2))
            head_region = frame[head_region_y1:head_region_y2, head_region_x1:head_region_x2]
        else:
            head_region_y1 = max(0, y1 - int(person_height * 0.2))
            head_region_y2 = min(height, y1 + int(person_height * 0.25))
            head_region = frame[head_region_y1:head_region_y2, x1:x2]
        
        if head_region.size > 0 and head_region.shape[0] > 8:
            head_gray = cv2.cvtColor(head_region, cv2.COLOR_BGR2GRAY)
            avg_brightness = np.mean(head_gray)
            
            # Hat/cap typically creates strong horizontal edge (brim) and covers top of head
            edges = cv2.Canny(head_gray, 40, 120)
            
            # Check for horizontal brim line (strong horizontal edge in middle-bottom of region)
            brim_region_y1 = int(head_region.shape[0] * 0.5)
            brim_region_y2 = int(head_region.shape[0] * 0.9)
            brim_region = edges[brim_region_y1:brim_region_y2, :]
            horizontal_edges = np.sum(brim_region > 0)
            
            # Stricter hat detection: Must have strong horizontal edge (brim) AND darker area
            # Also check for hat shape (circular/rectangular contour covering significant area)
            contours, _ = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
            has_hat_shape = False
            if len(contours) > 0:
                largest_contour = max(contours, key=cv2.contourArea)
                contour_area = cv2.contourArea(largest_contour)
                # Hat should cover at least 20% of head region
                if contour_area > head_region.size * 0.2:
                    # Check if contour is roughly horizontal/rectangular (hat brim)
                    x, y, w, h = cv2.boundingRect(largest_contour)
                    aspect_ratio = w / h if h > 0 else 0
                    if aspect_ratio > 1.5:  # Horizontal/rectangular shape (brim)
                        has_hat_shape = True
            
            # Hat detection: strong horizontal brim edge + darker area + hat-like shape
            brim_density = horizontal_edges / (brim_region.shape[0] * brim_region.shape[1]) if brim_region.size > 0 else 0
            
            if brim_density > 0.15 and avg_brightness < 130 and has_hat_shape:
                attributes["accessories"].append("hat")
        
        # Detect clothes - improved accuracy by excluding background (focus on center of person)
        # Shrink bounding box to exclude edges where background might leak in
        person_width = x2 - x1
        person_height = y2 - y1
        
        # Use only center 70% width and height to avoid background contamination
        shrink_factor_x = 0.15  # Exclude 15% from each side (30% total)
        shrink_factor_y = 0.10  # Exclude 10% from top/bottom
        center_x1 = x1 + int(person_width * shrink_factor_x)
        center_x2 = x2 - int(person_width * shrink_factor_x)
        center_y1 = y1 + int(person_height * shrink_factor_y)
        center_y2 = y2 - int(person_height * shrink_factor_y)
        
        # Ensure valid coordinates
        center_x1 = max(x1, center_x1)
        center_x2 = min(x2, center_x2)
        center_y1 = max(y1, center_y1)
        center_y2 = min(y2, center_y2)
        
        # Check upper body (chest/shoulder area, excluding face) - USE CENTER REGION ONLY
        upper_body_y1 = center_y1 + int((center_y2 - center_y1) * 0.15)  # Start below face region
        upper_body_y2 = center_y1 + int((center_y2 - center_y1) * 0.50)  # Mid-torso
        upper_body_region = frame[upper_body_y1:upper_body_y2, center_x1:center_x2]
        
        # Check lower body (waist down) - USE CENTER REGION ONLY
        lower_body_y1 = center_y1 + int((center_y2 - center_y1) * 0.50)
        lower_body_y2 = center_y2
        lower_body_region = frame[lower_body_y1:lower_body_y2, center_x1:center_x2]
        
        has_upper_clothes = False
        has_lower_clothes = False
        clothes_color = "Unknown"
        
        # Improved skin tone detection range (HSV) - more accurate
        # Skin tones: Hue 0-30 or 160-180, Saturation 25-255, Value 40-255
        def is_skin_pixel(h, s, v):
            """Check if pixel is skin-colored (excluding background colors)"""
            if v < 40 or v > 250:  # Too dark or too bright (exclude pure white)
                return False
            if s < 25:  # Too desaturated (gray/white/black - likely not skin or clothes)
                return False
            if (h < 30) or (h > 160):  # Red/orange/yellow range (skin tones)
                return True
            return False
        
        def is_likely_clothes_color(h, s, v, region_hsv):
            """Check if color is likely from clothes, not background
            Clothes colors are typically more saturated and have distinct hues"""
            # Exclude very dark (shadows) and very bright (overexposed)
            if v < 50 or v > 240:
                return False
            # Exclude very low saturation (grays, whites, blacks)
            if s < 30:
                return False
            # Exclude skin tones (already filtered but double-check)
            if (h < 30) or (h > 160):
                if 50 < v < 200:  # Valid skin tone range
                    return False
            return True
        
        # Check upper body for clothes (not skin-colored, excluding background)
        if upper_body_region.size > 0 and upper_body_region.shape[0] > 10 and upper_body_region.shape[1] > 10:
            hsv_upper = cv2.cvtColor(upper_body_region, cv2.COLOR_BGR2HSV)
            pixels_upper = hsv_upper.reshape(-1, 3)
            
            # Count non-skin pixels that are likely clothes (not background)
            non_skin_clothes_count = 0
            likely_clothes_pixels = []
            
            for pixel in pixels_upper:
                h, s, v = pixel[0], pixel[1], pixel[2]
                if not is_skin_pixel(h, s, v) and is_likely_clothes_color(h, s, v, hsv_upper):
                    non_skin_clothes_count += 1
                    likely_clothes_pixels.append(pixel)
            
            non_skin_ratio = non_skin_clothes_count / len(pixels_upper) if len(pixels_upper) > 0 else 0
            
            # Much higher threshold: at least 45% non-skin pixels that look like clothes (very strict to avoid background)
            if non_skin_ratio > 0.45:
                has_upper_clothes = True
                
                # Get dominant color from likely clothes pixels only
                if len(likely_clothes_pixels) > 150:  # Need sufficient pixels for accurate color detection
                    clothes_array = np.array(likely_clothes_pixels)
                    
                    # Use median for more robust color detection (less affected by outliers/background)
                    median_hue = np.median(clothes_array[:, 0])
                    median_sat = np.median(clothes_array[:, 1])
                    
                    # Additional validation: ensure sufficient saturation for color to be meaningful
                    if median_sat > 40:  # Must have decent saturation to be a valid color
                        # Improved color mapping
                        if median_hue < 5 or median_hue > 175:
                            clothes_color = "Red"
                        elif 5 <= median_hue < 20:
                            clothes_color = "Orange"
                        elif 20 <= median_hue < 30:
                            clothes_color = "Yellow"
                        elif 30 <= median_hue < 70:
                            clothes_color = "Green"
                        elif 70 <= median_hue < 95:
                            clothes_color = "Cyan"
                        elif 95 <= median_hue < 120:
                            clothes_color = "Blue"
                        elif 120 <= median_hue < 145:
                            clothes_color = "Purple"
                        elif 145 <= median_hue < 175:
                            clothes_color = "Pink"
                        else:
                            clothes_color = "Unknown"
                    else:
                        # Low saturation = grayish, not a clear color
                        clothes_color = "Unknown"
                else:
                    # Not enough clothes pixels detected
                    clothes_color = "Unknown"
        
        # Check lower body for clothes (using same background-exclusion logic)
        if lower_body_region.size > 0 and lower_body_region.shape[0] > 10:
            hsv_lower = cv2.cvtColor(lower_body_region, cv2.COLOR_BGR2HSV)
            pixels_lower = hsv_lower.reshape(-1, 3)
            
            # Count non-skin pixels that are likely clothes (not background)
            non_skin_clothes_count_lower = 0
            likely_clothes_pixels_lower = []
            
            for pixel in pixels_lower:
                h, s, v = pixel[0], pixel[1], pixel[2]
                if not is_skin_pixel(h, s, v) and is_likely_clothes_color(h, s, v, hsv_lower):
                    non_skin_clothes_count_lower += 1
                    likely_clothes_pixels_lower.append(pixel)
            
            non_skin_ratio_lower = non_skin_clothes_count_lower / len(pixels_lower) if len(pixels_lower) > 0 else 0
            
            if non_skin_ratio_lower > 0.50:  # At least 50% non-skin pixels that look like clothes (very strict)
                has_lower_clothes = True
                # Update color if upper body didn't have clothes
                if not has_upper_clothes and clothes_color == "Unknown":
                    if len(likely_clothes_pixels_lower) > 150:
                        clothes_array_lower = np.array(likely_clothes_pixels_lower)
                        median_hue_lower = np.median(clothes_array_lower[:, 0])
                        median_sat_lower = np.median(clothes_array_lower[:, 1])
                        
                        # Ensure sufficient saturation
                        if median_sat_lower > 40:
                            # Same color mapping as above
                            if median_hue_lower < 5 or median_hue_lower > 175:
                                clothes_color = "Red"
                            elif 5 <= median_hue_lower < 20:
                                clothes_color = "Orange"
                            elif 20 <= median_hue_lower < 30:
                                clothes_color = "Yellow"
                            elif 30 <= median_hue_lower < 70:
                                clothes_color = "Green"
                            elif 70 <= median_hue_lower < 95:
                                clothes_color = "Cyan"
                            elif 95 <= median_hue_lower < 120:
                                clothes_color = "Blue"
                            elif 120 <= median_hue_lower < 145:
                                clothes_color = "Purple"
                            elif 145 <= median_hue_lower < 175:
                                clothes_color = "Pink"
        
        # Determine clothes description with improved logic
        if not has_upper_clothes and not has_lower_clothes:
            attributes["clothes_color"] = "none"  # No clothes at all
        elif not has_upper_clothes:
            attributes["clothes_color"] = "topless"  # No top clothes
        elif clothes_color != "Unknown":
            attributes["clothes_color"] = clothes_color
        else:
            attributes["clothes_color"] = "Unknown"
        
        # Format accessories - must be "None" string if empty
        if not attributes["accessories"]:
            attributes["accessories"] = "None"  # Explicitly set to "None" string when no accessories
        else:
            attributes["accessories"] = ", ".join(attributes["accessories"]).lower()

        # Facial hair heuristic from lower face region
        if face_bbox:
            fx1, fy1, fx2, fy2 = face_bbox
            fh = max(1, fy2 - fy1)
            beard_y1 = fy1 + int(fh * 0.55)
            beard_region = frame[beard_y1:fy2, fx1:fx2]
            if beard_region.size > 0:
                gray_beard = cv2.cvtColor(beard_region, cv2.COLOR_BGR2GRAY)
                dark_ratio = float(np.mean(gray_beard < 75))
                if dark_ratio > 0.35:
                    attributes["facial_hair"] = "Beard"
    except:
        pass
    
    return attributes

def phone_near_person(phone_bbox, person_bbox):
    cx, cy = bbox_center(phone_bbox)
    if person_bbox['x1'] <= cx <= person_bbox['x2'] and person_bbox['y1'] <= cy <= person_bbox['y2']:
        return True
    return bbox_iou(phone_bbox, person_bbox) >= 0.05

<<<<<<< HEAD
def object_near_person(object_bbox, person_bbox):
    return phone_near_person(object_bbox, person_bbox)

def mark_suspicious_detections(detections):
    """Flag crowds, groups, and luggage for suspicious-activity monitoring."""
    persons = [d for d in detections if d.get('category') == 'person' and d.get('bbox')]

    for det in detections:
        category = det.get('category')
        if category == 'crowd':
            count = det.get('people_count') or det.get('group_size') or 0
            det['suspicious'] = True
            det['suspicious_reason'] = f'Crowd of {count} people — possible suspicious gathering'
        elif category == 'group':
            count = det.get('people_count') or det.get('group_size') or 0
            det['suspicious'] = True
            det['suspicious_reason'] = f'Group of {count} people — monitor for suspicious activity'
        elif category == 'backpack':
            bag_bbox = det.get('bbox')
            near_person = any(object_near_person(bag_bbox, p['bbox']) for p in persons) if bag_bbox else False
            det['suspicious'] = True
            det['suspicious_reason'] = (
                'Backpack on/near person — monitor for suspicious activity'
                if near_person else
                'Unattended backpack — possible suspicious activity'
            )
        elif category == 'suitcase':
            bag_bbox = det.get('bbox')
            near_person = any(object_near_person(bag_bbox, p['bbox']) for p in persons) if bag_bbox else False
            det['suspicious'] = True
            det['suspicious_reason'] = (
                'Suitcase on/near person — monitor for suspicious activity'
                if near_person else
                'Unattended suitcase/luggage — possible suspicious activity'
            )
        else:
            det['suspicious'] = False
            det.pop('suspicious_reason', None)

    return detections

def enrich_detections_for_display(detections):
    """Normalize person fields for the Detected Objects card UI."""
    phones = [d for d in detections if d.get('category') == 'phone' and d.get('bbox')]
    bags = [d for d in detections if d.get('category') in ('backpack', 'suitcase') and d.get('bbox')]
=======
def enrich_detections_for_display(detections):
    """Normalize person fields for the Detected Objects card UI."""
    phones = [d for d in detections if d.get('category') == 'phone' and d.get('bbox')]
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69

    for det in detections:
        category = det.get('category')
        if category == 'person':
            acc = str(det.get('accessories', 'None') or 'None').lower()
            det['mask'] = 'Yes' if 'mask' in acc else 'No'
            det['earrings'] = 'Yes' if 'earring' in acc else 'No'
            det['facial_hair'] = det.get('facial_hair') or 'None'

            gender = det.get('gender', 'Unknown')
            if gender == 'Male':
                det['gender'] = 'Man'
            elif gender == 'Female':
                det['gender'] = 'Woman'

            expr = str(det.get('expression', 'calm') or 'calm')
            det['expression'] = expr.capitalize()

            clothes = str(det.get('clothes_color', 'Unknown') or 'Unknown')
            if clothes.lower() in ('none', 'unknown'):
                det['clothes_color'] = clothes.capitalize() if clothes.lower() == 'none' else 'Unknown'
            else:
                det['clothes_color'] = clothes.capitalize()

            items = []
            weapon = det.get('weapon')
            if weapon:
                items.append(str(weapon.get('class', 'weapon')))
            person_bbox = det.get('bbox')
            if person_bbox:
                for phone in phones:
                    if phone_near_person(phone['bbox'], person_bbox):
                        items.append('cell phone')
                        break
<<<<<<< HEAD
                for bag in bags:
                    if object_near_person(bag['bbox'], person_bbox):
                        label = 'backpack' if bag.get('category') == 'backpack' else 'suitcase'
                        items.append(label)
=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
            det['items_detected'] = ', '.join(dict.fromkeys(items)) if items else 'None'
            continue

        if category in ('group', 'crowd'):
            count = det.get('people_count') or det.get('group_size') or 0
            det['items_detected'] = f'{count} people'
<<<<<<< HEAD
            if det.get('suspicious_reason'):
                det['activity'] = 'Suspicious'
            continue

        if category in ('backpack', 'suitcase'):
            det['items_detected'] = det.get('class') or category
            if det.get('suspicious_reason'):
                det['activity'] = 'Suspicious'
=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
            continue

        det['items_detected'] = det.get('class') or category or 'None'

    return detections

def save_detected_object_image(frame, bbox, detection_id, category="person", face_bbox=None):
    """Crop and save detected object image - for persons, extract FACE ONLY (no body parts)"""
    try:
        # Create directory if it doesn't exist
        os.makedirs(DETECTED_OBJECTS_DIR, exist_ok=True)
        
        # For persons, ALWAYS extract face only - never show body parts
        if category == "person":
            if face_bbox:
                # Use detected face bounding box
                fx1, fy1, fx2, fy2 = face_bbox
                x1, y1, x2, y2 = fx1, fy1, fx2, fy2
            else:
                # Face bbox not provided - detect face from person bbox
                face_bbox_detected = detect_face_in_bbox(frame, bbox)
                if face_bbox_detected:
                    fx1, fy1, fx2, fy2 = face_bbox_detected
                    x1, y1, x2, y2 = fx1, fy1, fx2, fy2
                else:
                    # Fallback: estimate face region (upper 25% of person, centered, tight crop)
                    person_x1, person_y1 = int(bbox['x1']), int(bbox['y1'])
                    person_x2, person_y2 = int(bbox['x2']), int(bbox['y2'])
                    person_width = person_x2 - person_x1
                    person_height = person_y2 - person_y1
                    # Face is typically in upper 25% of person, centered horizontally, tight crop
                    face_height = int(person_height * 0.25)  # Upper 25% only (tighter)
                    face_width = int(person_width * 0.5)     # 50% width, centered (tighter)
                    face_x_center = person_x1 + person_width // 2
                    x1 = face_x_center - face_width // 2
                    y1 = person_y1 + int(person_height * 0.08)  # Start 8% down from top
                    x2 = x1 + face_width
                    y2 = y1 + face_height
                    
                    # Ensure coordinates are valid
                    x1 = max(person_x1, x1)
                    y1 = max(person_y1, y1)
                    x2 = min(person_x2, x2)
                    y2 = min(person_y2, y2)
        else:
            # For non-person objects (weapons, vehicles, animals), use full bbox
            x1, y1, x2, y2 = int(bbox['x1']), int(bbox['y1']), int(bbox['x2']), int(bbox['y2'])
        
        # Ensure coordinates are within frame bounds
        height, width = frame.shape[:2]
        x1 = max(0, min(x1, width - 1))
        y1 = max(0, min(y1, height - 1))
        x2 = max(x1 + 1, min(x2, width))
        y2 = max(y1 + 1, min(y2, height))
        
        # Crop the object/face region
        object_crop = frame[y1:y2, x1:x2]
        
        if object_crop.size > 0 and object_crop.shape[0] > 10 and object_crop.shape[1] > 10:
            crop_height, crop_width = object_crop.shape[:2]
            
            # For persons (face only), ensure square aspect ratio for better display
            if category == "person":
                target_size = 200  # Standard size for face crops
                # Make it square (use larger dimension to preserve detail)
                max_dim = max(crop_width, crop_height)
                if max_dim < target_size:
                    # Upscale to target size
                    new_size = target_size
                    object_crop = cv2.resize(object_crop, (new_size, new_size), interpolation=cv2.INTER_CUBIC)
                elif max_dim > target_size:
                    # Downscale to target size
                    new_size = target_size
                    object_crop = cv2.resize(object_crop, (new_size, new_size), interpolation=cv2.INTER_AREA)
                else:
                    # Already close to target, make square
                    new_size = max_dim
                    if crop_width != crop_height:
                        # Make square by resizing to max dimension
                        object_crop = cv2.resize(object_crop, (new_size, new_size), interpolation=cv2.INTER_CUBIC)
            else:
                # For other objects (weapons, vehicles, animals), resize to reasonable size
                if crop_width < 50:
                    scale = 50 / crop_width
                    new_width = 50
                    new_height = int(crop_height * scale)
                    object_crop = cv2.resize(object_crop, (new_width, new_height), interpolation=cv2.INTER_CUBIC)
                elif crop_width > 300:
                    scale = 300 / crop_width
                    new_width = 300
                    new_height = int(crop_height * scale)
                    object_crop = cv2.resize(object_crop, (new_width, new_height), interpolation=cv2.INTER_AREA)
            
            # Save cropped image
            image_path = os.path.join(DETECTED_OBJECTS_DIR, f"object_{detection_id}.jpg")
            cv2.imwrite(image_path, object_crop, [cv2.IMWRITE_JPEG_QUALITY, 95])  # High quality for accurate object shots
            return f"detected_objects/object_{detection_id}.jpg"
    except Exception as e:
        # Silently fail - object image is optional
        pass
    return None

def cleanup_old_object_images(max_count=MAX_DETECTED_OBJECT_IMAGES):
    """Keep only the most recent object images"""
    try:
        if not os.path.exists(DETECTED_OBJECTS_DIR):
            return
        
        # Get all object image files sorted by modification time
        object_files = []
        for filename in os.listdir(DETECTED_OBJECTS_DIR):
            if filename.startswith("object_") and filename.endswith(".jpg"):
                filepath = os.path.join(DETECTED_OBJECTS_DIR, filename)
                mtime = os.path.getmtime(filepath)
                object_files.append((mtime, filepath))
        
        # Sort by modification time (newest first)
        object_files.sort(reverse=True)
        
        # Delete old files if we exceed the limit
        if len(object_files) > max_count:
            for mtime, filepath in object_files[max_count:]:
                try:
                    os.remove(filepath)
                except:
                    pass
    except Exception:
        pass

def is_ground_motion(bbox, frame_height, ground_threshold=0.12):
    """Check if detection is in the ground area (bottom portion of frame) - filter out false positives"""
    # Calculate bottom Y coordinate of bounding box
    bottom_y = bbox['y2']
    
    # Ground area is typically bottom 12% of frame (where shadows, reflections, ground-level noise occur)
    ground_area_start = frame_height * (1 - ground_threshold)
    
    # If detection's bottom edge is in ground area, it's likely ground motion
    if bottom_y >= ground_area_start:
        # Additional check: if detection is very small and at ground level, likely false positive
        detection_height = bbox['y2'] - bbox['y1']
        if detection_height < frame_height * 0.05:  # Very small detections at ground level
            return True
    return False

def process_detections(results, frame):
    """Process YOLO detection results and extract object images"""
    detections = []
    detection_id_counter = int(time.time() * 1000)  # Unique ID based on timestamp
    
    # Get frame dimensions for ground filtering
    frame_height = frame.shape[0]
    
    if USE_ULTRALYTICS:
        # Ultralytics YOLOv8 format
        for result in results:
            boxes = result.boxes
            if boxes is not None:
                for box in boxes:
                    cls_id = int(box.cls[0])
                    conf = float(box.conf[0])
                    
                    # Get class name
                    class_name = result.names[cls_id]
                    category = map_class_to_category(cls_id, class_name)
                    min_conf = confidence_threshold_for_category(category)
                    if conf < min_conf:
                        continue
                    
                    if category and category in TARGET_CLASSES + ["weapon"]:
                        # Get bounding box coordinates
                        x1, y1, x2, y2 = box.xyxy[0].tolist()
                        
                        bbox = tighten_detection_bbox(frame, {
                            "x1": int(x1),
                            "y1": int(y1),
                            "x2": int(x2),
                            "y2": int(y2)
                        }, category)
                        x1, y1, x2, y2 = bbox['x1'], bbox['y1'], bbox['x2'], bbox['y2']
                        
                        # Filter out ground motion (false positives at ground level); keep plants/phones/bags
                        if category not in ("plant", "phone", "backpack", "suitcase") and is_ground_motion(bbox, frame_height):
                            continue  # Skip this detection - it's ground motion/noise
                        
                        # Generate unique ID for this detection
                        detection_id = detection_id_counter + len(detections)
                        
                        # For persons, detect face and analyze attributes
                        face_bbox = None
                        attributes = {}
                        if category == "person":
                            face_bbox = detect_face_in_bbox(frame, bbox)
                            if face_bbox:
                                attributes = analyze_person_attributes(frame, bbox, face_bbox)
                        
                        # Check if person has weapon nearby - if yes, include weapon in detection
                        weapon_detected = None
                        if category == "person" and boxes is not None:
                            # Look for weapons near the person (within person's bounding box area)
                            for weapon_box in boxes:
                                weapon_cls_id = int(weapon_box.cls[0])
                                weapon_conf = float(weapon_box.conf[0])
                                weapon_class = result.names[weapon_cls_id]
                                
                                # Check if this is a weapon (skip if it's the same box as person)
                                if (any(w in weapon_class.lower() for w in WEAPON_CLASSES) and 
                                    weapon_conf >= CONFIDENCE_THRESHOLD and
                                    weapon_box is not box):
                                    wx1, wy1, wx2, wy2 = weapon_box.xyxy[0].tolist()
                                    # Check if weapon is within or near person's bounding box
                                    person_width = x2 - x1
                                    person_height = y2 - y1
                                    distance_threshold_x = person_width * 0.8
                                    distance_threshold_y = person_height * 0.8
                                    if (wx1 >= x1 - distance_threshold_x and wx2 <= x2 + distance_threshold_x and
                                        wy1 >= y1 - distance_threshold_y and wy2 <= y2 + distance_threshold_y):
                                        weapon_detected = {
                                            "class": weapon_class,
                                            "confidence": round(weapon_conf, 2),
                                            "bbox": tighten_detection_bbox(frame, {
                                                "x1": int(wx1),
                                                "y1": int(wy1),
                                                "x2": int(wx2),
                                                "y2": int(wy2)
                                            }, "weapon")
                                        }
                                        break
                        
                        # Extract and save object image (face only for persons, full object for weapons)
                        image_path = save_detected_object_image(frame, bbox, detection_id, category, face_bbox)
                        
                        detection = {
                            "id": detection_id,
                            "category": category,
                            "class": class_name,
                            "confidence": round(conf, 2),
                            "bbox": bbox,
                            "image": image_path,  # Path to cropped object/face image
                            "timestamp": datetime.now().isoformat()
                        }
                        
                        # Add person attributes if available
                        if category == "person" and attributes:
                            detection.update(attributes)
                        
                        # Add weapon info if person has weapon
                        if category == "person" and weapon_detected:
                            detection["weapon"] = weapon_detected
                            # Also save weapon image separately
                            weapon_image_path = save_detected_object_image(
                                frame, 
                                weapon_detected["bbox"], 
                                detection_id + 1000000,  # Offset ID for weapon
                                "weapon",
                                None
                            )
                            detection["weapon"]["image"] = weapon_image_path
                        
                        detections.append(detection)
    else:
        # YOLOv5 format
        pred = results.pred[0]
        if pred is not None and len(pred) > 0:
            for det in pred:
                x1, y1, x2, y2, conf, cls_id = det.tolist()
                cls_id = int(cls_id)
                class_name = results.names[cls_id]
                category = map_class_to_category(cls_id, class_name)
                min_conf = confidence_threshold_for_category(category)
                if conf < min_conf:
                    continue
                
                if category and category in TARGET_CLASSES + ["weapon"]:
                    bbox = tighten_detection_bbox(frame, {
                        "x1": int(x1),
                        "y1": int(y1),
                        "x2": int(x2),
                        "y2": int(y2)
                    }, category)
                    
                    # Filter out ground motion (false positives at ground level); keep plants/phones
                    if category not in ("plant", "phone", "backpack", "suitcase") and is_ground_motion(bbox, frame_height):
                        continue  # Skip this detection - it's ground motion/noise
                    
                    # Generate unique ID for this detection
                    detection_id = detection_id_counter + len(detections)
                    
                    # For persons, detect face and analyze attributes
                    face_bbox = None
                    attributes = {}
                    if category == "person":
                        face_bbox = detect_face_in_bbox(frame, bbox)
                        if face_bbox:
                            attributes = analyze_person_attributes(frame, bbox, face_bbox)
                    
                    # Extract and save object image (face only for persons)
                    image_path = save_detected_object_image(frame, bbox, detection_id, category, face_bbox)
                    
                    detection = {
                        "id": detection_id,
                        "category": category,
                        "class": class_name,
                        "confidence": round(conf, 2),
                        "bbox": bbox,
                        "image": image_path,  # Path to cropped object/face image
                        "timestamp": datetime.now().isoformat()
                    }
                    
                    # Add person attributes if available
                    if category == "person" and attributes:
                        detection.update(attributes)
                    
                    detections.append(detection)
    
    # Keep enough people for crowd/group analysis; trim other classes
    persons = [d for d in detections if d.get('category') == 'person']
    others = [d for d in detections if d.get('category') != 'person']
    persons = sorted(persons, key=lambda x: x.get('confidence', 0), reverse=True)[:MAX_PERSON_DETECTIONS]
    others = sorted(others, key=lambda x: x.get('confidence', 0), reverse=True)[:MAX_OTHER_DETECTIONS]
    return persons + others

def bbox_center(bbox):
    return (
        (bbox['x1'] + bbox['x2']) / 2.0,
        (bbox['y1'] + bbox['y2']) / 2.0,
    )

def people_are_nearby(a, b, distance_scale=1.6):
    """True when two person boxes are close enough to count as the same group."""
    ax, ay = bbox_center(a)
    bx, by = bbox_center(b)
    aw = max(1, a['x2'] - a['x1'])
    ah = max(1, a['y2'] - a['y1'])
    bw = max(1, b['x2'] - b['x1'])
    bh = max(1, b['y2'] - b['y1'])
    avg_w = (aw + bw) / 2.0
    avg_h = (ah + bh) / 2.0
    # Allow a bit more horizontal spacing (side-by-side people) than vertical
    return abs(ax - bx) <= avg_w * distance_scale * 1.4 and abs(ay - by) <= avg_h * distance_scale

def cluster_person_detections(person_detections):
    """Group nearby person detections with simple connected-component clustering."""
    n = len(person_detections)
    if n == 0:
        return []

    parent = list(range(n))

    def find(i):
        while parent[i] != i:
            parent[i] = parent[parent[i]]
            i = parent[i]
        return i

    def union(i, j):
        ri, rj = find(i), find(j)
        if ri != rj:
            parent[rj] = ri

    for i in range(n):
        for j in range(i + 1, n):
            if people_are_nearby(person_detections[i]['bbox'], person_detections[j]['bbox']):
                union(i, j)

    clusters = {}
    for i in range(n):
        root = find(i)
        clusters.setdefault(root, []).append(person_detections[i])
    return list(clusters.values())

def analyze_crowds_and_groups(frame, detections):
    """
    Detect groups/crowds from individual person boxes.
    - 2–3 nearby people => group
    - 4+ nearby people => crowd
    Also tags each person with group_size / crowd_id when applicable.
    """
    persons = [d for d in detections if d.get('category') == 'person' and d.get('bbox')]
    person_count = len(persons)

    # Always expose a live people count for the UI / API consumers
    for det in detections:
        if det.get('category') == 'person':
            det['people_in_frame'] = person_count

    if person_count < GROUP_MIN_PEOPLE:
        return detections

    clusters = cluster_person_detections(persons)
    crowd_id = 0

    for cluster in clusters:
        size = len(cluster)
        if size < GROUP_MIN_PEOPLE:
            continue

        crowd_id += 1
        xs1 = [p['bbox']['x1'] for p in cluster]
        ys1 = [p['bbox']['y1'] for p in cluster]
        xs2 = [p['bbox']['x2'] for p in cluster]
        ys2 = [p['bbox']['y2'] for p in cluster]
        group_bbox = {
            'x1': int(min(xs1)),
            'y1': int(min(ys1)),
            'x2': int(max(xs2)),
            'y2': int(max(ys2)),
        }

        category = 'crowd' if size >= CROWD_MIN_PEOPLE else 'group'
        class_name = f'{size} people'
        avg_conf = sum(p.get('confidence', 0) for p in cluster) / size

        for person in cluster:
            person['group_id'] = crowd_id
            person['group_size'] = size
            person['in_crowd'] = category == 'crowd'

        detection_id = int(time.time() * 1000) + len(detections) + crowd_id
        image_path = save_detected_object_image(frame, group_bbox, detection_id, category, None)
        detections.append({
            'id': detection_id,
            'category': category,
            'class': class_name,
            'confidence': round(avg_conf, 2),
            'bbox': group_bbox,
            'image': image_path,
            'timestamp': datetime.now().isoformat(),
            'people_count': size,
            'group_id': crowd_id,
            'people_in_frame': person_count,
            'source': 'crowd_analysis',
        })

    return detections

def draw_detections_on_frame(frame, detections):
    """Draw cached detection overlays so boxes stay stable between inference frames."""
    for detection in detections:
        bbox = detection.get('bbox', {})
        if not bbox:
            continue
        x1 = bbox.get('x1', 0)
        y1 = bbox.get('y1', 0)
        x2 = bbox.get('x2', 0)
        y2 = bbox.get('y2', 0)
        category = detection.get('category', 'unknown')
        class_name = detection.get('class', category)
        conf = detection.get('confidence', 0)
        color = get_color_for_category(category)
        thickness = 2 if category in ("plant", "phone", "group", "crowd") else 2
        cv2.rectangle(frame, (int(x1), int(y1)), (int(x2), int(y2)), color, thickness)
        if category in ("group", "crowd"):
            people_count = detection.get('people_count', detection.get('group_size', 0))
            label = f"{category}: {people_count} people"
<<<<<<< HEAD
        elif detection.get('suspicious'):
            label = f"! {category}: {class_name} {conf:.2f}"
=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
        else:
            label = f"{category}: {class_name} {conf:.2f}"
        font_scale = 0.7 if category in ("plant", "phone", "backpack", "suitcase", "group", "crowd") else 0.55
        font_thickness = 2
        (tw, th), baseline = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, font_scale, font_thickness)
        label_y = max(int(y1) - 8, th + 8)
        cv2.rectangle(
            frame,
            (int(x1), label_y - th - 6),
            (int(x1) + tw + 8, label_y + baseline),
            color,
            -1
        )
        cv2.putText(
            frame,
            label,
            (int(x1) + 4, label_y - 2),
            cv2.FONT_HERSHEY_SIMPLEX,
            font_scale,
            (0, 0, 0) if category in ("plant", "phone", "backpack", "suitcase", "group", "crowd") else (255, 255, 255),
            font_thickness
        )

def get_color_for_category(category):
    """Get color for drawing bounding boxes"""
    colors = {
        "person": (0, 255, 0),      # Green
        "vehicle": (255, 165, 0),   # Orange
        "animal": (0, 255, 255),    # Yellow
        "plant": (72, 187, 120),    # Light green
        "phone": (59, 130, 246),    # Blue
        "backpack": (168, 85, 247), # Purple
        "suitcase": (245, 158, 11), # Amber
        "group": (168, 85, 247),    # Purple
        "crowd": (236, 72, 153),    # Pink / magenta
        "weapon": (0, 0, 255)       # Red
    }
    return colors.get(category, (255, 255, 255))

def save_detections(detections):
    """Save detections to JSON file atomically with better Windows file lock handling"""
    temp_file = DETECTIONS_FILE + ".tmp"
    
    try:
        detection_data = {
            "timestamp": datetime.now().isoformat(),
            "detections": detections,
            "count": len(detections),
            "people_count": sum(1 for d in detections if d.get('category') == 'person'),
            "group_count": sum(1 for d in detections if d.get('category') == 'group'),
            "crowd_count": sum(1 for d in detections if d.get('category') == 'crowd'),
<<<<<<< HEAD
            "backpack_count": sum(1 for d in detections if d.get('category') == 'backpack'),
            "suitcase_count": sum(1 for d in detections if d.get('category') == 'suitcase'),
            "suspicious_count": sum(1 for d in detections if d.get('suspicious')),
=======
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
            "status": "active"
        }
        
        # Write to temporary file first
        with open(temp_file, 'w') as f:
            json.dump(detection_data, f, indent=2)
        
        # Atomic replace with retry logic for Windows file locks
        max_retries = 5
        saved = False
        
        for attempt in range(max_retries):
            try:
                # Try to remove old file if it exists
                if os.path.exists(DETECTIONS_FILE):
                    try:
                        # Check if file is readable (not locked)
                        with open(DETECTIONS_FILE, 'r') as test_f:
                            test_f.read(1)
                        # If readable, try to remove it
                        os.remove(DETECTIONS_FILE)
                        time.sleep(0.05)
                    except (PermissionError, OSError):
                        # File is locked, try to rename it
                        try:
                            backup_name = DETECTIONS_FILE + ".old"
                            if os.path.exists(backup_name):
                                os.remove(backup_name)
                            os.rename(DETECTIONS_FILE, backup_name)
                            time.sleep(0.05)
                        except:
                            pass  # Continue anyway
                
                # Now try to move/copy the new file
                if os.path.exists(DETECTIONS_FILE):
                    os.replace(temp_file, DETECTIONS_FILE)
                else:
                    shutil.move(temp_file, DETECTIONS_FILE)
                saved = True
                break
                
            except (PermissionError, OSError) as e:
                if attempt < max_retries - 1:
                    time.sleep(0.1 * (attempt + 1))  # Increasing delay
                    continue
                else:
                    # Final fallback: direct write
                    try:
                        with open(temp_file, 'r') as src:
                            data = src.read()
                        if os.path.exists(DETECTIONS_FILE):
                            os.remove(DETECTIONS_FILE)
                            time.sleep(0.1)
                        with open(DETECTIONS_FILE, 'w') as dst:
                            dst.write(data)
                        if os.path.exists(temp_file):
                            os.remove(temp_file)
                        saved = True
                        break
                    except:
                        pass
        
        # Clean up temp file if still exists
        if not saved and os.path.exists(temp_file):
            try:
                os.remove(temp_file)
            except:
                pass
                
    except Exception as e:
        # Only log errors occasionally to avoid spam
        pass  # Silently fail - detections will be saved on next attempt

def save_live_frame(frame, frame_count):
    """Fast ping-pong JPEG save for the web live view (avoids Windows file-lock delays)."""
    if frame is None or frame.size == 0:
        return False

    use_alt = (frame_count // max(FRAME_SAVE_INTERVAL, 1)) % 2
    target = FRAME_FILE_ALT if use_alt else FRAME_FILE
    fallback = FRAME_FILE if use_alt else FRAME_FILE_ALT
    params = [cv2.IMWRITE_JPEG_QUALITY, LIVE_JPEG_QUALITY]

    try:
        if cv2.imwrite(target, frame, params):
            return True
        return cv2.imwrite(fallback, frame, params)
    except Exception:
        try:
            return cv2.imwrite(fallback, frame, params)
        except Exception:
            return False


def read_latest_rtsp_frame(cap, flush_grabs=RTSP_FLUSH_GRABS):
    """Drop buffered RTSP frames and return the newest decodable one."""
    ret = False
    frame = None
    with open(os.devnull, 'w') as devnull:
        old_stderr = sys.stderr
        try:
            sys.stderr = devnull
            for _ in range(max(1, flush_grabs)):
                if not cap.grab():
                    break
            for _ in range(3):
                ret, frame = cap.retrieve()
                if ret and frame is not None and is_valid_frame(frame):
                    break
                if not cap.grab():
                    break
            if not ret or frame is None or not is_valid_frame(frame):
                ret, frame = cap.read()
        finally:
            sys.stderr = old_stderr
    return ret, frame


def save_frame_atomic(frame, filepath):
    """Save frame atomically to prevent corruption"""
    root, ext = os.path.splitext(filepath)
    temp_file = f"{root}.tmp{ext or '.jpg'}"
    
    try:
        # Ensure frame is valid before saving
        if frame is None:
            return False
        
        if frame.size == 0:
            return False
        
        # Validate frame dimensions
        try:
            height, width = frame.shape[:2]
            if width < 100 or height < 100 or width > 10000 or height > 10000:
                return False
        except (AttributeError, IndexError):
            return False
        
        # Ensure frame is in correct format (BGR for OpenCV)
        if len(frame.shape) != 3 or frame.shape[2] != 3:
            return False
        
        # Convert to uint8 if needed
        if frame.dtype != np.uint8:
            frame = np.clip(frame, 0, 255).astype(np.uint8)
        
        # Use higher quality for better image clarity
        encode_params = [
            cv2.IMWRITE_JPEG_QUALITY, 95,
        ]
        
        # Save to temporary file first
        success = cv2.imwrite(temp_file, frame, encode_params)
        
        if not success:
            # Clean up failed temp file
            if os.path.exists(temp_file):
                try:
                    os.remove(temp_file)
                except:
                    pass
            return False
        
        # Verify the temp file was created and has reasonable size
        if not os.path.exists(temp_file):
            return False
        
        # Brief wait to ensure file is fully flushed on disk
        time.sleep(0.002)
        
        # Verify file is complete by checking size and trying to read it back
        try:
            file_size = os.path.getsize(temp_file)
            if file_size < 1000:  # Too small to be a valid JPEG
                os.remove(temp_file)
                return False
            
            # Try to decode the JPEG to verify it's valid
            test_img = cv2.imread(temp_file, cv2.IMREAD_COLOR)
            if test_img is None or test_img.size == 0:
                os.remove(temp_file)
                return False
        except:
            if os.path.exists(temp_file):
                try:
                    os.remove(temp_file)
                except:
                    pass
            return False
        
        # Check file size - for 640x360 image, minimum should be around 5KB
        # But be lenient for low-quality/dark images
        file_size = os.path.getsize(temp_file)
        if file_size < 100:  # Very lenient - at least 100 bytes
            try:
                os.remove(temp_file)
            except:
                pass
            return False
        
        # Atomic replace - try multiple methods with better Windows handling
        max_retries = 5
        last_error = None
        
        for attempt in range(max_retries):
            try:
                # On Windows, file may be locked by web server reading it
                # Try to delete old file first if it exists
                if os.path.exists(filepath):
                    try:
                        # Check if file is readable (not locked)
                        with open(filepath, 'rb') as test_f:
                            test_f.read(1)
                        # If readable, try to remove it
                        os.remove(filepath)
                        time.sleep(0.1)  # Brief pause
                    except (PermissionError, OSError):
                        # File is locked, try to rename it first
                        try:
                            backup_name = filepath + ".old"
                            if os.path.exists(backup_name):
                                os.remove(backup_name)
                            os.rename(filepath, backup_name)
                            time.sleep(0.1)
                        except:
                            pass  # Continue anyway
                
                # Now try to move/copy the new file
                try:
                    if os.path.exists(filepath):
                        # If still exists, try direct replace
                        os.replace(temp_file, filepath)
                    else:
                        # File doesn't exist, just move it
                        shutil.move(temp_file, filepath)
                    return True
                except PermissionError:
                    # Still locked, wait and retry
                    if attempt < max_retries - 1:
                        time.sleep(0.2 * (attempt + 1))  # Increasing delay
                        continue
                
            except Exception as e:
                last_error = e
                if attempt < max_retries - 1:
                    time.sleep(0.1)
                    continue
        
        # All retries failed, try final fallback methods
        try:
            # Final method: Direct binary copy
            with open(temp_file, 'rb') as src:
                data = src.read()
            
            # Try to write with exclusive access
            try:
                # Try to remove old file one more time
                if os.path.exists(filepath):
                    os.remove(filepath)
                    time.sleep(0.2)
            except:
                pass
            
            with open(filepath, 'wb') as dst:
                dst.write(data)
            
            if os.path.exists(temp_file):
                os.remove(temp_file)
            return True
            
        except Exception as e:
            last_error = e
            # Clean up temp file
            if os.path.exists(temp_file):
                try:
                    os.remove(temp_file)
                except:
                    pass
            return False
        
    except Exception as e:
        # Clean up on any error
        if os.path.exists(temp_file):
            try:
                os.remove(temp_file)
            except:
                pass
        # Only print error occasionally to avoid spam
        return False

def get_recording_bucket_start(dt=None):
    """Floor a timestamp to the current 5-minute recording bucket."""
    dt = dt or datetime.now()
    bucket_minute = (dt.minute // (RECORDING_BUCKET_SECONDS // 60)) * (RECORDING_BUCKET_SECONDS // 60)
    return dt.replace(minute=bucket_minute, second=0, microsecond=0)


def get_recording_filename(dt=None):
    """Generate filename for the current 5-minute recording bucket."""
    bucket = get_recording_bucket_start(dt)
    timestamp = bucket.strftime("%Y%m%d_%H%M%S")
    filename = f"recording_{timestamp}{RECORDING_EXTENSION}"
    return os.path.join(RECORDINGS_DIR, filename)


def estimate_recording_duration(filepath, frame_count=0, start_time=None, fps=None):
    """Estimate clip duration. Prefer wall-clock for min-length checks."""
    if start_time:
        wall = max(0.0, time.time() - start_time)
        if wall > 0:
            return wall
    write_fps = fps if fps and fps > 0 else RECORDING_FPS
    if frame_count and frame_count > 0:
        return frame_count / write_fps
    try:
        cap = cv2.VideoCapture(filepath)
        if cap.isOpened():
            file_fps = cap.get(cv2.CAP_PROP_FPS) or write_fps
            frames = cap.get(cv2.CAP_PROP_FRAME_COUNT) or 0
            cap.release()
            if file_fps > 0 and frames > 0:
                return frames / file_fps
    except Exception:
        pass
    return 0.0


def remux_recording_faststart(filepath):
    """Rewrite MP4 with moov at front for browser playback (requires ffmpeg)."""
    ffmpeg = shutil.which('ffmpeg')
    if not ffmpeg or not os.path.isfile(filepath):
        return False

    tmp_path = filepath + '.faststart.mp4'
    try:
        result = subprocess.run(
            [
                ffmpeg, '-y', '-i', filepath,
                '-c', 'copy', '-movflags', '+faststart',
                tmp_path,
            ],
            capture_output=True,
            text=True,
            timeout=120,
        )
        if result.returncode != 0 or not os.path.isfile(tmp_path) or os.path.getsize(tmp_path) < 1024:
            if os.path.exists(tmp_path):
                os.remove(tmp_path)
            return False
        os.replace(tmp_path, filepath)
        return True
    except Exception as e:
        if os.path.exists(tmp_path):
            try:
                os.remove(tmp_path)
            except OSError:
                pass
        print(f"⚠ Faststart remux skipped: {e}")
        return False


def recording_has_moov(filepath):
    """True when the MP4 container was finalized (required for browser play)."""
    try:
        with open(filepath, 'rb') as fh:
            head = fh.read(min(1048576, os.path.getsize(filepath)))
            if b'moov' in head:
                return True
            if os.path.getsize(filepath) > 1048576:
                fh.seek(max(0, os.path.getsize(filepath) - 1048576))
                return b'moov' in fh.read()
    except OSError:
        pass
    return False


def finalize_video_writer(writer_info, discard_short=True):
    """Release writer and discard fragments that are too short."""
    if writer_info is None:
        return

    writer, filename, start_time, frame_count, write_fps = writer_info
    try:
        if writer is not None:
            writer.release()
    except Exception as e:
        print(f"✗ Error closing video writer: {e}")

    if not filename or not os.path.exists(filename):
        return

    size = os.path.getsize(filename)
    if size < 1024:
        try:
            os.remove(filename)
            print(f"✓ Removed empty recording: {os.path.basename(filename)}")
        except OSError as e:
            print(f"✗ Error removing empty recording: {e}")
        return

    duration = estimate_recording_duration(
        filename, frame_count=frame_count, start_time=start_time, fps=write_fps
    )
    if discard_short and duration < MIN_RECORDING_DURATION:
        try:
            os.remove(filename)
            print(f"✓ Discarded short fragment ({duration:.0f}s wall): {os.path.basename(filename)}")
        except OSError as e:
            print(f"✗ Error removing short recording: {e}")
        return

    if remux_recording_faststart(filename):
        print(f"✓ Faststart remux done: {os.path.basename(filename)}")
    elif not recording_has_moov(filename):
        print(f"⚠ Warning: {os.path.basename(filename)} may not play in browsers (missing moov)")

    print(f"✓ Completed recording: {filename} ({duration:.0f}s wall-clock, {frame_count} frames @ {write_fps:.2f} fps)")


def cleanup_short_recordings(min_duration=MIN_RECORDING_DURATION):
    """Remove old tiny recording fragments left by restarts or failed sessions."""
    if not os.path.exists(RECORDINGS_DIR):
        return

    removed = 0
    for filename in os.listdir(RECORDINGS_DIR):
        if not (filename.startswith("recording_") and filename.endswith(RECORDING_EXTENSION)):
            continue
        filepath = os.path.join(RECORDINGS_DIR, filename)
        if not os.path.isfile(filepath):
            continue
        if os.path.getsize(filepath) < 1024:
            try:
                os.remove(filepath)
                removed += 1
            except OSError:
                pass
            continue
        duration = estimate_recording_duration(filepath)
        if duration > 0 and duration < min_duration:
            try:
                os.remove(filepath)
                removed += 1
                print(f"✓ Cleaned short recording ({duration:.0f}s): {filename}")
            except OSError as e:
                print(f"✗ Error cleaning short recording {filename}: {e}")

    if removed:
        print(f"✓ Removed {removed} short recording fragment(s)")


def create_video_writer(filename, width, height, fps=RECORDING_FPS):
    """Create a VideoWriter using a browser-compatible codec when possible."""
    for codec in ('avc1', 'H264', 'mp4v'):
        fourcc = cv2.VideoWriter_fourcc(*codec)
        writer = cv2.VideoWriter(filename, fourcc, fps, (width, height))
        if writer.isOpened():
            if codec != RECORDING_CODEC:
                print(f"  Recording codec: {codec}")
            return writer
        writer.release()
    return None

def init_video_writer(width, height, fps=None):
    """Initialize VideoWriter for recording"""
    if not ENABLE_RECORDING:
        return None

    write_fps = float(fps if fps is not None else RECORDING_FPS)
    if write_fps <= 0:
        write_fps = RECORDING_FPS

    try:
        # Ensure recordings directory exists
        os.makedirs(RECORDINGS_DIR, exist_ok=True)

        # Get filename for new recording
        filename = get_recording_filename()

        # Overwrite any incomplete in-progress file for this bucket
        if os.path.exists(filename) and not recording_has_moov(filename):
            try:
                os.remove(filename)
            except OSError:
                pass

        writer = create_video_writer(filename, width, height, write_fps)

        if writer is not None:
            print(f"✓ Started recording ({write_fps:.2f} fps, 5-min bucket): {os.path.basename(filename)}")
            return writer, filename, time.time(), 0, write_fps
        else:
            print(f"✗ Failed to initialize video writer for: {filename}")
            return None
    except Exception as e:
        print(f"✗ Error initializing video writer: {e}")
        return None

def scale_frame_for_display(frame):
    """Keep native resolution when possible; downscale only if above display cap. Never upscale."""
    height, width = frame.shape[:2]
    if width <= 0 or height <= 0:
        return frame

    if width <= MAX_DISPLAY_WIDTH and height <= MAX_DISPLAY_HEIGHT:
        return frame

    scale = min(MAX_DISPLAY_WIDTH / width, MAX_DISPLAY_HEIGHT / height)
    new_width = max(1, int(width * scale))
    new_height = max(1, int(height * scale))
    return cv2.resize(frame, (new_width, new_height), interpolation=cv2.INTER_AREA)


def should_rotate_video(writer_info, chunk_duration=RECORDING_CHUNK_DURATION):
    """Check if video chunk should be rotated."""
    if writer_info is None:
        return False
    _, filename, start_time, _frame_count = writer_info[:4]
    if (time.time() - start_time) >= chunk_duration:
        return True
    expected_filename = get_recording_filename()
    return os.path.basename(filename) != os.path.basename(expected_filename)


def rotate_video_writer(writer_info, width, height, fps=None):
    """Close current video and start a new chunk."""
    write_fps = fps
    if write_fps is None and writer_info is not None:
        write_fps = writer_info[4] if len(writer_info) > 4 else RECORDING_FPS
    if writer_info is None:
        return init_video_writer(width, height, write_fps)

    finalize_video_writer(writer_info, discard_short=True)
    cleanup_old_recordings()
    return init_video_writer(width, height, write_fps)

def get_recording_file_age_seconds(filepath):
    """Return age of a recording file in seconds (from filename timestamp or mtime)."""
    name = os.path.basename(filepath)
    if name.startswith("recording_") and name.endswith(RECORDING_EXTENSION):
        stamp = name[len("recording_"):-len(RECORDING_EXTENSION)]
        if len(stamp) == 15 and "_" in stamp:
            try:
                recorded_at = datetime.strptime(stamp, "%Y%m%d_%H%M%S")
                return max(0.0, (datetime.now() - recorded_at).total_seconds())
            except ValueError:
                pass
    try:
        return max(0.0, time.time() - os.path.getmtime(filepath))
    except OSError:
        return None


def cleanup_old_recordings(retention_days=RECORDING_RETENTION_DAYS):
    """Remove recording files older than the retention window."""
    if not os.path.exists(RECORDINGS_DIR):
        return

    cutoff_seconds = max(1, int(retention_days)) * 86400
    removed = 0

    try:
        for filename in os.listdir(RECORDINGS_DIR):
            if not (filename.startswith("recording_") and filename.endswith(RECORDING_EXTENSION)):
                continue
            filepath = os.path.join(RECORDINGS_DIR, filename)
            if not os.path.isfile(filepath):
                continue

            try:
                size = os.path.getsize(filepath)
            except OSError:
                continue

            age_seconds = get_recording_file_age_seconds(filepath)
            expired = age_seconds is not None and age_seconds > cutoff_seconds
            empty = size < 1024

            if expired or empty:
                try:
                    os.remove(filepath)
                    removed += 1
                    if expired:
                        days_old = int(age_seconds // 86400)
                        print(f"✓ Removed expired recording ({days_old}d old): {filename}")
                    else:
                        print(f"✓ Removed empty recording: {filename}")
                except Exception as e:
                    print(f"✗ Error removing old recording {filepath}: {e}")

        if removed:
            print(f"✓ Retention cleanup complete ({removed} file(s) removed, keep {retention_days} days)")
    except Exception as e:
        print(f"✗ Error during recording cleanup: {e}")

def is_valid_frame(frame):
    """Validate frame before processing - detect corruption patterns like vertical striping"""
    if frame is None:
        return False
    if frame.size == 0:
        return False
    
    try:
        height, width = frame.shape[:2]
        if width < 100 or height < 100:
            return False
        
        # Check if frame has valid dimensions
        if width > 10000 or height > 10000:  # Unrealistic dimensions
            return False
        
        # Check if frame is mostly black (corrupted or no signal)
        mean_brightness = np.mean(frame)
        if mean_brightness < 5:
            return False
        
        # Check if frame is completely uniform (likely corrupted)
        std_dev = np.std(frame)
        if std_dev < 1.0:  # Very low variance means uniform/corrupted
            return False
        
        # Check for NaN or Inf values (corruption indicators)
        if np.any(np.isnan(frame)) or np.any(np.isinf(frame)):
            return False
        
        # Detect vertical striping/banding (common H.264 corruption pattern)
        # Check vertical variance - corrupted frames often have very high vertical variance
        # Convert to grayscale for analysis
        if len(frame.shape) == 3:
            gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        else:
            gray = frame
        
        # Calculate column-wise variance
        # If many columns have very high variance, likely vertical striping
        col_variances = np.var(gray, axis=0)
        high_var_cols = np.sum(col_variances > (np.mean(col_variances) * 3))
        
        # If more than 30% of columns have abnormally high variance, likely corrupted
        if high_var_cols > (width * 0.3):
            return False
        
        # Check for horizontal stripes (another corruption pattern)
        row_variances = np.var(gray, axis=1)
        high_var_rows = np.sum(row_variances > (np.mean(row_variances) * 3))
        if high_var_rows > (height * 0.3):
            return False
        
        # Check for excessive edge artifacts (corruption indicator)
        # Calculate edges and check if there are too many artificial edges
        edges = cv2.Canny(gray, 50, 150)
        edge_density = np.sum(edges > 0) / (width * height)
        
        # Edge density too high (>60%) usually indicates corruption artifacts
        if edge_density > 0.6:
            return False
        
        # Check for excessive brightness variation (common in corrupted H.264 frames)
        brightness_variation = np.std(gray)
        if brightness_variation > 100:  # Unusually high variation
            # Check if it's natural variation or corruption
            # Corrupted frames often have sudden brightness jumps
            diff = np.abs(np.diff(gray.astype(np.float32)))
            large_jumps = np.sum(diff > 100)
            if large_jumps > (width * height * 0.1):  # More than 10% large jumps
                return False
            
    except Exception as e:
        # If any validation fails, frame is invalid
        return False
    
    return True

def try_http_snapshot_fallback():
    """Try HTTP snapshot as fallback when RTSP fails - REOLINK cameras work better with HTTP"""
    if not REQUESTS_AVAILABLE:
        return None

    camera_ip, username, password = get_active_camera_credentials()
    
    # REOLINK HTTP snapshot URLs (prioritize known working format)
    # Tested: http://172.22.0.187/cgi-bin/api.cgi?cmd=Snap&channel=0&rs=wuuPhkmUCeI9WP7T&user=admin&password=admin123
    snapshot_urls = [
        # REOLINK API format (CONFIRMED WORKING - tested)
        f"http://{camera_ip}/cgi-bin/api.cgi?cmd=Snap&channel=0&rs=wuuPhkmUCeI9WP7T&user={username}&password={password}",
        # Alternative REOLINK API formats
        f"http://{username}:{password}@{camera_ip}/cgi-bin/api.cgi?cmd=Snap&channel=0",
        f"http://{camera_ip}/cgi-bin/api.cgi?cmd=Snap&channel=0&user={username}&pwd={password}",
        # REOLINK snapshot.cgi format
        f"http://{username}:{password}@{camera_ip}/cgi-bin/snapshot.cgi?channel=0",
        f"http://{camera_ip}/cgi-bin/snapshot.cgi?channel=0&user={username}&pwd={password}",
        # Direct snapshot endpoints
        f"http://{username}:{password}@{camera_ip}/snapshot.jpg",
        f"http://{camera_ip}/snapshot.jpg?user={username}&pwd={password}",
        # Alternative formats
        f"http://{username}:{password}@{camera_ip}/tmpfs/auto.jpg",
        f"http://{camera_ip}/tmpfs/auto.jpg?user={username}&pwd={password}",
    ]
    
    for snap_url in snapshot_urls:
        try:
            url_display = snap_url.split('@')[-1] if '@' in snap_url else snap_url.split('?')[0] if '?' in snap_url else snap_url
            # Try without auth first (for URLs with auth in query string)
            if 'user=' in snap_url and 'password=' in snap_url:
                try:
                    response = requests.get(snap_url, timeout=5)
                    if response.status_code == 200 and len(response.content) > 1000:
                        img_array = np.frombuffer(response.content, np.uint8)
                        img = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
                        if img is not None and img.size > 0:
                            return snap_url, img
                except:
                    pass
            
            # Try with digest auth
            try:
                response = requests.get(snap_url, auth=HTTPDigestAuth(username, password), timeout=5)
                if response.status_code == 200 and len(response.content) > 1000:
                    img_array = np.frombuffer(response.content, np.uint8)
                    img = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
                    if img is not None and img.size > 0:
                        return snap_url, img
            except:
                pass
            
            # Try with basic auth
            try:
                response = requests.get(snap_url, auth=HTTPBasicAuth(username, password), timeout=5)
                if response.status_code == 200 and len(response.content) > 1000:
                    img_array = np.frombuffer(response.content, np.uint8)
                    img = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
                    if img is not None and img.size > 0:
                        return snap_url, img
            except:
                pass
                
        except Exception as e:
            continue
    
    return None

def connect_to_stream(url, timeout=STREAM_TIMEOUT):
    """Connect to RTSP stream with optimized settings for HEVC/H.264"""
    print(f"Connecting to camera stream: {url}")
    print("  Trying RTSP first for lowest live-view latency...")
    
    # Build comprehensive list of REOLINK RTSP URL variants
    # REOLINK cameras support multiple URL formats - try all common ones
    # Note: Some formats may need authentication in URL, others may need separate auth
    url_variants = []
    base_rtsp, base_rtsp_no_auth = get_rtsp_bases(url)
    
    # REOLINK common formats (try most common first)
    # Try URLs without auth in URL first (some cameras prefer auth via RTSP DESCRIBE)
    if PREFER_SUB_STREAM:
        url_variants = [
            # Try without auth in URL first (auth via RTSP)
            f"{base_rtsp_no_auth}/Preview_01_sub?transport=tcp",  # From ONVIF test
            f"{base_rtsp_no_auth}/Preview_01_sub",
            # REOLINK cam/realmonitor format (most common for newer models)
            f"{base_rtsp}/cam/realmonitor?channel=1&subtype=1&transport=tcp",  # Sub-stream with TCP
            f"{base_rtsp}/cam/realmonitor?channel=1&subtype=1",  # Sub-stream without TCP
            f"{base_rtsp_no_auth}/cam/realmonitor?channel=1&subtype=1&transport=tcp",
            f"{base_rtsp_no_auth}/cam/realmonitor?channel=1&subtype=1",
            # REOLINK Streaming/Channels format
            f"{base_rtsp}/Streaming/Channels/102?transport=tcp",  # Sub-stream (channel 102)
            f"{base_rtsp}/Streaming/Channels/102",  # Sub-stream without TCP
            f"{base_rtsp_no_auth}/Streaming/Channels/102?transport=tcp",
            f"{base_rtsp_no_auth}/Streaming/Channels/102",
            # REOLINK Preview format (from ONVIF - these should work)
            f"{base_rtsp}/Preview_01_sub?transport=tcp",
            f"{base_rtsp}/Preview_01_sub",
            # REOLINK h264Preview format (older models)
            f"{base_rtsp}/h264Preview_01_sub?transport=tcp",
            f"{base_rtsp}/h264Preview_01_sub",
            # Provided URL
            url,
            f"{url}?transport=tcp",
            # Main stream as last resort
            f"{base_rtsp_no_auth}/Preview_01_main?transport=tcp",
            f"{base_rtsp_no_auth}/Preview_01_main",
            f"{base_rtsp}/cam/realmonitor?channel=1&subtype=0&transport=tcp",  # Main stream with TCP
            f"{base_rtsp}/cam/realmonitor?channel=1&subtype=0",
            f"{base_rtsp}/Streaming/Channels/101?transport=tcp",  # Main stream (channel 101)
            f"{base_rtsp}/Streaming/Channels/101",
            f"{base_rtsp}/Preview_01_main?transport=tcp",
            f"{base_rtsp}/Preview_01_main",
            f"{base_rtsp}/h264Preview_01_main?transport=tcp",
            f"{base_rtsp}/h264Preview_01_main",
        ]
    else:
        # Try main stream FIRST for higher quality
        url_variants = [
            # Try ONVIF-discovered formats FIRST (these are confirmed to exist)
            f"{base_rtsp_no_auth}/Preview_01_main?transport=tcp",  # From ONVIF test - main stream with TCP
            f"{base_rtsp_no_auth}/Preview_01_main",  # From ONVIF test - main stream
            f"{base_rtsp}/Preview_01_main?transport=tcp",  # With auth in URL
            f"{base_rtsp}/Preview_01_main",  # With auth in URL
            # REOLINK cam/realmonitor format (most common for newer models)
            f"{base_rtsp_no_auth}/cam/realmonitor?channel=1&subtype=0&transport=tcp",  # No auth, TCP
            f"{base_rtsp_no_auth}/cam/realmonitor?channel=1&subtype=0",  # No auth
            f"{base_rtsp}/cam/realmonitor?channel=1&subtype=0&transport=tcp",  # Main stream with TCP
            f"{base_rtsp}/cam/realmonitor?channel=1&subtype=0",  # Main stream without TCP
            # REOLINK Streaming/Channels format
            f"{base_rtsp_no_auth}/Streaming/Channels/101?transport=tcp",  # No auth, TCP
            f"{base_rtsp_no_auth}/Streaming/Channels/101",  # No auth
            f"{base_rtsp}/Streaming/Channels/101?transport=tcp",  # Main stream (channel 101)
            f"{base_rtsp}/Streaming/Channels/101",  # Main stream without TCP
            # REOLINK h264Preview format
            f"{base_rtsp}/h264Preview_01_main?transport=tcp",
            f"{base_rtsp}/h264Preview_01_main",
            # Provided URL
            url,
            f"{url}?transport=tcp",
            # Sub-stream as fallback
            f"{base_rtsp}/cam/realmonitor?channel=1&subtype=1&transport=tcp",  # Sub-stream with TCP
            f"{base_rtsp}/cam/realmonitor?channel=1&subtype=1",
            f"{base_rtsp}/Streaming/Channels/102?transport=tcp",
            f"{base_rtsp}/Streaming/Channels/102",
            f"{base_rtsp}/Preview_01_sub?transport=tcp",
            f"{base_rtsp}/Preview_01_sub",
            f"{base_rtsp}/h264Preview_01_sub?transport=tcp",
            f"{base_rtsp}/h264Preview_01_sub",
        ]
    
    # Set environment variable for FFmpeg options (optimized for REOLINK cameras)
    # Use TCP transport for reliability, longer timeouts for connection
    os.environ['OPENCV_FFMPEG_CAPTURE_OPTIONS'] = (
        'rtsp_transport;tcp|'
        'timeout;5000000|'
        'stimeout;5000000|'
        'max_delay;0|'
        'fflags;nobuffer|'
        'flags;low_delay|'
        'err_detect;ignore_err|'
        'error;0|'
        'thread_queue_size;64|'
        'reorder_queue_size;0|'
        'probesize;32768|'
        'analyzeduration;0'
    )
    
    # Suppress FFmpeg verbose output
    os.environ['OPENCV_FFMPEG_READ_ATTEMPTS'] = '3'  # Try 3 times
    os.environ['OPENCV_FFMPEG_READ_ATTEMPT_MS'] = '2000'  # 2 seconds per attempt
    
    # Try connecting with FFmpeg backend - try multiple URL formats
    # Suppress stderr to hide H.264 decoding errors during connection
    cap = None
    working_url = None
    
    print("  Trying different RTSP URL formats (VLC and OpenCV may need different paths)...")
    for idx, test_url in enumerate(url_variants, 1):
        try:
            print(f"  [{idx}/{len(url_variants)}] Trying: {test_url}")
            with suppress_stderr():
                # Try with FFmpeg backend
                cap = cv2.VideoCapture(test_url, cv2.CAP_FFMPEG)
                
                # For URLs without auth, try to set credentials via environment or properties
                if "@" not in test_url:
                    _, rtsp_user, rtsp_pass = get_active_camera_credentials()
                    try:
                        cap.set(cv2.CAP_PROP_USERNAME, rtsp_user)
                        cap.set(cv2.CAP_PROP_PASSWORD, rtsp_pass)
                    except Exception:
                        pass  # Not all backends support this
            
            # Set properties for absolute lowest latency
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)  # Minimal buffer for lowest latency (1 frame)
            cap.set(cv2.CAP_PROP_FPS, TARGET_FPS)  # Set to 120 FPS
            # Additional low-latency optimizations
            try:
                cap.set(cv2.CAP_PROP_FOURCC, cv2.VideoWriter_fourcc(*'H264'))  # Use H264 codec for efficiency
            except:
                pass
            # Disable auto-focus and auto-exposure to prevent zoom/focus changes
            try:
                cap.set(cv2.CAP_PROP_AUTOFOCUS, 0)  # Disable auto-focus
                cap.set(cv2.CAP_PROP_AUTO_EXPOSURE, 0.25)  # Disable auto-exposure (prevents zoom)
            except:
                pass  # Some cameras don't support these properties
            
            # Quick test read (with timeout and stderr suppression)
            if cap.isOpened():
                # Try reading a frame (aggressively suppress ALL H.264 decoding errors)
                # Give it a few attempts to read a valid frame
                ret = False
                test_frame = None
                for read_attempt in range(3):
                    with open(os.devnull, 'w') as devnull:
                        old_stderr = sys.stderr
                        try:
                            sys.stderr = devnull
                            ret, test_frame = cap.read()
                            if ret and test_frame is not None and is_valid_frame(test_frame):
                                break
                            time.sleep(0.2)  # Brief pause between read attempts
                        except:
                            pass
                        finally:
                            sys.stderr = old_stderr
                
                if ret and test_frame is not None and is_valid_frame(test_frame):
                    print(f"  ✓ Found working URL: {test_url}")
                    print(f"  ✓ Using RTSP live stream (low latency)")
                    working_url = test_url
                    break
                else:
                    if cap:
                        cap.release()
                    cap = None
            else:
                if cap:
                    cap.release()
                cap = None
            time.sleep(0.3)  # Brief pause between URL attempts
        except Exception as e:
            if cap:
                cap.release()
            cap = None
            continue
    
    if cap is None or not cap.isOpened() or working_url is None:
        print(f"\n  ✗ Failed to connect with any RTSP URL variant")
        print(f"  Tried {len(url_variants)} different RTSP formats")
        
        # Try HTTP snapshot as fallback
        http_result = try_http_snapshot_fallback()
        if http_result:
            snap_url, test_img = http_result
            print(f"\n  ✓ Using HTTP snapshot fallback mode")
            print(f"  Note: ~{HTTP_SNAPSHOT_INTERVAL:.2f}s between frames (RTSP unavailable)")
            # Return a special marker to indicate HTTP mode
            return "HTTP_SNAPSHOT_MODE"
        
        print(f"\n  ✗ All connection methods failed (RTSP and HTTP)")
        print(f"\n  DEBUG: Creating placeholder frame...")
        # Create a placeholder frame so web interface shows something
        placeholder = np.zeros((FRAME_HEIGHT, FRAME_WIDTH, 3), dtype=np.uint8)
        cv2.putText(placeholder, "Camera Connection Failed", (50, FRAME_HEIGHT//2 - 30), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 255), 2)
        cv2.putText(placeholder, "Check script output for errors", (50, FRAME_HEIGHT//2 + 10), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
        cv2.putText(placeholder, "Tried RTSP and HTTP methods", (50, FRAME_HEIGHT//2 + 40), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)
        save_frame_atomic(placeholder, FRAME_FILE)
        save_detections([])
        parsed = urlparse(RTSP_URL)
        host = parsed.hostname or "camera-host"
        user = parsed.username or "username"
        password = parsed.password or "password"
        print(f"\n  Possible solutions:")
        print(f"  1. Check camera IP: {host}")
        print(f"  2. Verify credentials: {user} / {password}")
        print(f"  3. Test in VLC: rtsp://{user}:{password}@{host}:554/cam/realmonitor?channel=1&subtype=1")
        print(f"  4. Check camera web interface: http://{host}")
        print(f"  5. Verify camera is powered on and connected to network")
        print(f"  6. Check firewall settings")
        return None
    
    # Properties already set during connection test
    
    # Set buffer to minimum for real-time feed
    cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    
    # Try to read and discard initial frames (they may be corrupted or outdated)
    print("Initializing stream (discarding first few frames to get fresh data)...")
    start_time = time.time()
    connected = False
    frames_discarded = 0
    valid_frames_found = 0
    
    while time.time() - start_time < timeout:
        # Suppress H.264 errors during frame read
        with open(os.devnull, 'w') as devnull:
            old_stderr = sys.stderr
            try:
                sys.stderr = devnull
                ret, test_frame = cap.read()
            finally:
                sys.stderr = old_stderr
        
        if ret:
            frames_discarded += 1
            
            # Discard first 5-10 frames as they often have HEVC reference frame errors
            if frames_discarded < 5:
                continue
                
            # Check if frame is valid (not corrupted)
            if is_valid_frame(test_frame):
                valid_frames_found += 1
                # Need at least 2 valid frames to confirm stable connection
                if valid_frames_found >= 2:
                    connected = True
                    print(f"✓ Stream connected successfully")
                    print(f"  (Discarded {frames_discarded} initial frames, found {valid_frames_found} valid frames)")
                    break
            elif frames_discarded > 20:
                # If we've read 20+ frames and none are valid, something is wrong
                print("  Warning: Many corrupted frames detected, but continuing...")
                break
        else:
            # If read fails, wait and retry
            time.sleep(0.3)
            
    if not connected:
        cap.release()
        print("✗ Failed to establish stable stream connection")
        print("  Troubleshooting steps:")
        print("  1. Check camera is powered on and accessible")
        print("  2. Verify RTSP URL is correct:", RTSP_URL)
        print("  3. Test RTSP stream in VLC: File > Open Network Stream")
        print("  4. Camera may need H.264 encoding instead of HEVC/H.265")
        print("  5. Check network connection stability")
        print("  6. See TROUBLESHOOTING_HEVC.md for more solutions")
        return None
    
    return cap

def check_and_create_lock():
    """Check if another instance is running, create lock file if not"""
    lock_path = Path(LOCK_FILE)
    
    # Check if lock file exists
    if lock_path.exists():
        try:
            # Read PID from lock file
            with open(lock_path, 'r') as f:
                old_pid = int(f.read().strip())
            
            # Check if process is still running (Windows)
            if sys.platform == 'win32':
                try:
                    # Try to check if process exists (signal 0 doesn't kill)
                    os.kill(old_pid, 0)
                    # Process exists - another instance is running
                    print(f"✗ Another instance of detect.py is already running (PID: {old_pid})")
                    print("Please stop the existing instance before starting a new one.")
                    print("You can use: stop_detection.bat")
                    return False
                except (ProcessLookupError, OSError):
                    # Process doesn't exist - stale lock file, remove it
                    lock_path.unlink()
            else:
                # Unix-like systems
                try:
                    os.kill(old_pid, 0)
                    print(f"✗ Another instance of detect.py is already running (PID: {old_pid})")
                    return False
                except (ProcessLookupError, OSError):
                    lock_path.unlink()
        except (ValueError, IOError):
            # Lock file is corrupted, remove it
            lock_path.unlink()
    
    # Create lock file with current PID
    try:
        with open(lock_path, 'w') as f:
            f.write(str(os.getpid()))
        return True
    except Exception as e:
        print(f"Warning: Could not create lock file: {e}")
        return True  # Continue anyway

def remove_lock():
    """Remove lock file on exit"""
    lock_path = Path(LOCK_FILE)
    try:
        if lock_path.exists():
            lock_path.unlink()
    except Exception:
        pass

def main():
    """Main detection loop with robust error handling and auto-reconnection"""
    global FRAME_WIDTH, FRAME_HEIGHT
    print("=" * 60)
    print("YOLO Object Detection System")
    print("=" * 60)
    
    # Check for existing instance
    if not check_and_create_lock():
        sys.exit(1)
    
    # Register cleanup function to remove lock on exit
    atexit.register(remove_lock)
    
    print("Initializing...")
    configure_camera_source()
    
    # Load model (offline after first download)
    model = load_yolo_model()
    
    # Initialize empty detections file
    save_detections([])
    
    # Create directory for detected object images
    try:
        os.makedirs(DETECTED_OBJECTS_DIR, exist_ok=True)
        print(f"✓ Detected objects directory ready: {DETECTED_OBJECTS_DIR}")
    except Exception as e:
        print(f"Warning: Could not create detected objects directory: {e}")
    
    # Create directory for recordings
    if ENABLE_RECORDING:
        try:
            os.makedirs(RECORDINGS_DIR, exist_ok=True)
            print(f"✓ Recordings directory ready: {RECORDINGS_DIR}")
            cleanup_short_recordings()
            cleanup_old_recordings()
            print(f"✓ Recording policy: {RECORDING_CHUNK_DURATION // 60}-min segments, min {MIN_RECORDING_DURATION // 60} min kept")
            print(f"✓ Retention policy: auto-delete after {RECORDING_RETENTION_DAYS} days")
            print(f"  HTTP recording: {HTTP_RECORDING_FPS:.2f} fps (snapshot every {HTTP_SNAPSHOT_INTERVAL:.1f}s)")
            print(f"  RTSP recording: {RECORDING_FPS} fps")
        except Exception as e:
            print(f"Warning: Could not create recordings directory: {e}")
            print("Recording will be disabled")
    
    # Initialize video writer (will be created when stream connects)
    video_writer_info = None
    
    # Create initial placeholder frames (ensure they exist for web interface)
    placeholder = np.zeros((FRAME_HEIGHT, FRAME_WIDTH, 3), dtype=np.uint8)
    cv2.putText(placeholder, "Connecting to camera...", (50, FRAME_HEIGHT//2), 
               cv2.FONT_HERSHEY_SIMPLEX, 1, (255, 255, 255), 2)
    
    # Create initial frames using direct save (bypass atomic for initial)
    try:
        if not os.path.exists(FRAME_FILE):
            cv2.imwrite(FRAME_FILE, placeholder, [cv2.IMWRITE_JPEG_QUALITY, 95])
        if not os.path.exists(FRAME_FILE_ALT):
            cv2.imwrite(FRAME_FILE_ALT, placeholder, [cv2.IMWRITE_JPEG_QUALITY, 95])
    except Exception as e:
        print(f"Warning: Could not create initial placeholder frames: {e}")
    
    reconnect_count = 0
    frame_count = 0
    last_detection_save = time.time()
    last_frame_time = time.time()
    last_overlay_detections = []
    consecutive_failures = 0
    
    http_snapshot_url = None
    http_mode = False
    
    while True:
        # Connect to stream
        cap = connect_to_stream(RTSP_URL)
        
        # Check if we're using HTTP snapshot fallback
        if cap == "HTTP_SNAPSHOT_MODE":
            http_result = try_http_snapshot_fallback()
            if http_result:
                http_snapshot_url, test_img = http_result
                http_mode = True
                cap = None  # No VideoCapture object for HTTP mode
                print("Starting HTTP snapshot detection loop...")
                print("Press Ctrl+C to stop")
                print("-" * 60)
            else:
                cap = None
        
        if cap is None and not http_mode:
            reconnect_count += 1
            if reconnect_count >= MAX_RECONNECT_ATTEMPTS:
                print(f"\n✗ Maximum reconnection attempts ({MAX_RECONNECT_ATTEMPTS}) reached.")
                print("Please check:")
                print("  1. Camera is powered on and accessible")
                print("  2. RTSP URL is correct:", RTSP_URL)
                print("  3. Camera credentials are correct")
                print("  4. Network connection to camera")
                print("\nWaiting 30 seconds before retrying...")
                time.sleep(30)
                reconnect_count = 0
                continue
            else:
                print(f"Reconnection attempt {reconnect_count}/{MAX_RECONNECT_ATTEMPTS}...")
                time.sleep(RECONNECT_DELAY)
                continue
        
        if not http_mode:
            print("Starting RTSP detection loop...")
            print("Press Ctrl+C to stop")
            print("-" * 60)
        
        reconnect_count = 0
        consecutive_failures = 0
        consecutive_decode_failures = 0
        
        # Initialize video recording on first valid frame (uses actual display size)
        video_writer_info = None
        
        try:
            while True:
                if http_mode:
                    # HTTP snapshot mode - fetch snapshot periodically with proper validation
                    ret = False
                    frame = None
                    _, http_user, http_pass = get_active_camera_credentials()
                    try:
                        # Try without auth first (for URLs with auth in query string)
                        if 'user=' in http_snapshot_url and 'password=' in http_snapshot_url:
                            response = requests.get(http_snapshot_url, timeout=5)
                        else:
                            response = requests.get(http_snapshot_url, auth=HTTPDigestAuth(http_user, http_pass), timeout=5)
                        
                        if response.status_code == 200 and len(response.content) > 1000:
                            # Validate JPEG data before decoding
                            if response.content[:2] == b'\xff\xd8':  # JPEG magic bytes
                                img_array = np.frombuffer(response.content, np.uint8)
                                frame = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
                                # Validate decoded frame
                                if frame is not None and frame.size > 0:
                                    # Additional validation
                                    if is_valid_frame(frame):
                                        ret = True
                                        consecutive_failures = 0  # Reset on success
                                    else:
                                        ret = False
                                        frame = None
                                else:
                                    ret = False
                                    frame = None
                            else:
                                ret = False
                                frame = None
                        else:
                            ret = False
                            frame = None
                    except Exception as e:
                        # Try with basic auth as fallback
                        try:
                            if 'user=' not in http_snapshot_url or 'password=' not in http_snapshot_url:
                                response = requests.get(http_snapshot_url, auth=HTTPBasicAuth(http_user, http_pass), timeout=5)
                                if response.status_code == 200 and len(response.content) > 1000:
                                    if response.content[:2] == b'\xff\xd8':  # JPEG magic bytes
                                        img_array = np.frombuffer(response.content, np.uint8)
                                        frame = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
                                        if frame is not None and frame.size > 0 and is_valid_frame(frame):
                                            ret = True
                                            consecutive_failures = 0
                                        else:
                                            ret = False
                                            frame = None
                                    else:
                                        ret = False
                                        frame = None
                                else:
                                    ret = False
                                    frame = None
                            else:
                                ret = False
                                frame = None
                        except:
                            ret = False
                            frame = None
                    
                    if not ret:
                        consecutive_failures += 1
                        if consecutive_failures > 5:
                            print("HTTP snapshot connection lost. Reconnecting...")
                            break
                        time.sleep(1)
                        continue
                    
                    # Pace snapshots so recorded FPS matches real elapsed time
                    time.sleep(HTTP_SNAPSHOT_INTERVAL)
                else:
                    # RTSP mode - read newest frame (flush stale buffer for lower latency)
                    ret, frame = read_latest_rtsp_frame(cap)
                
                # Also suppress any Python warnings that might appear
                with warnings.catch_warnings():
                    warnings.simplefilter("ignore")
                
                # Check if frame read was successful and valid
                if not ret:
                    consecutive_failures += 1
                    if consecutive_failures > 10:
                        print(f"Too many consecutive frame read failures ({consecutive_failures}). Reconnecting...")
                        break
                    time.sleep(0.1)
                    continue
                
                # Validate frame quality (skip corrupted frames from HEVC/H.264 errors)
                if not is_valid_frame(frame):
                    consecutive_failures += 1
                    consecutive_decode_failures += 1
                    if consecutive_decode_failures >= MAX_DECODE_FAILURES_BEFORE_RECONNECT:
                        print(f"Too many decode errors ({consecutive_decode_failures}). Reconnecting RTSP...")
                        break
                    if consecutive_failures > 15:
                        print(f"Received {consecutive_failures} corrupted frames. Reconnecting...")
                        break
                    continue
                
                # Reset failure count on valid frame
                consecutive_failures = 0
                consecutive_decode_failures = 0
                
                # Scale for web display — preserve sharpness, never upscale low-res streams
                try:
                    frame = scale_frame_for_display(frame)
                    current_height, current_width = frame.shape[:2]
                    if current_width <= 0 or current_height <= 0:
                        print(f"Invalid frame dimensions: {current_width}x{current_height}")
                        consecutive_failures += 1
                        continue
                    FRAME_WIDTH = current_width
                    FRAME_HEIGHT = current_height
                except Exception as e:
                    print(f"Error scaling frame: {e}")
                    consecutive_failures += 1
                    continue
                
                # Skip timestamp overlay for absolute lowest latency (uncomment if needed)
                # Timestamp adds ~1-2ms processing time per frame
                # timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                # cv2.putText(frame, timestamp, (10, 30), 
                #            cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
                
                current_time = time.time()

                # Live web view: save immediately before detection/recording (lowest latency)
                if save_live_frame(frame, frame_count):
                    last_frame_time = current_time
                
                # Write frame to video recording (before saving for display)
                if ENABLE_RECORDING:
                    try:
                        recording_fps = HTTP_RECORDING_FPS if http_mode else RECORDING_FPS
                        if video_writer_info is None:
                            video_writer_info = init_video_writer(FRAME_WIDTH, FRAME_HEIGHT, recording_fps)
                            if video_writer_info is None:
                                print("⚠ Warning: Video recording initialization failed, continuing without recording")
                        if video_writer_info is not None:
                            if should_rotate_video(video_writer_info, RECORDING_CHUNK_DURATION):
                                video_writer_info = rotate_video_writer(
                                    video_writer_info, FRAME_WIDTH, FRAME_HEIGHT, recording_fps
                                )
                            writer, filename, start_time, recorded_frames, write_fps = video_writer_info
                            if writer is not None and writer.isOpened():
                                writer.write(frame)
                                video_writer_info = (writer, filename, start_time, recorded_frames + 1, write_fps)
                    except Exception as e:
                        if frame_count % 100 == 0:
                            print(f"⚠ Warning: Error writing to video: {e}")
                
                # Run YOLO periodically; reuse last overlay on every frame to avoid box flicker.
                detections = []
                if ENABLE_DETECTION and frame_count % DETECTION_INTERVAL == 0:
                    try:
                        if USE_ULTRALYTICS:
                            results = model(
                                frame,
                                verbose=False,
<<<<<<< HEAD
                                conf=min(CONFIDENCE_THRESHOLD, PLANT_CONFIDENCE_THRESHOLD, PHONE_CONFIDENCE_THRESHOLD, BAG_CONFIDENCE_THRESHOLD),
=======
                                conf=min(CONFIDENCE_THRESHOLD, PLANT_CONFIDENCE_THRESHOLD, PHONE_CONFIDENCE_THRESHOLD),
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                                classes=YOLO_TARGET_CLASS_IDS,
                                imgsz=640,
                            )
                        else:
                            results = model(frame)
                        detections = process_detections(results, frame)
                        detections = enhance_phone_detections(frame, model, detections)
<<<<<<< HEAD
                        detections = enhance_bag_detections(frame, model, detections)
                        detections = analyze_crowds_and_groups(frame, detections)
                        detections = mark_suspicious_detections(detections)
=======
                        detections = analyze_crowds_and_groups(frame, detections)
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
                        detections = enrich_detections_for_display(detections)
                        last_overlay_detections = detections
                        # Always refresh detections.json so the UI stays in sync
                        if current_time - last_detection_save >= 0.5:
                            try:
                                save_detections(detections)
                                if detections:
                                    cleanup_old_object_images()
                                    print(f"[{datetime.now().strftime('%H:%M:%S')}] ✓ Detected {len(detections)} object(s)")
                                last_detection_save = current_time
                            except Exception:
                                pass
                    except Exception as e:
                        print(f"Error running detection: {e}")

                if last_overlay_detections:
                    draw_detections_on_frame(frame, last_overlay_detections)
                    cv2.putText(frame, f"Objects: {len(last_overlay_detections)}", (10, 60),
                               cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
                    # Refresh live view with detection overlay (non-blocking for next frame)
                    save_live_frame(frame, frame_count + 1)

                frame_count += 1
                
                # No delay - process frames as fast as possible for lowest latency
                # Only add minimal delay if processing is too fast (prevents CPU overload)
                if FRAME_PROCESS_DELAY > 0:
                    time.sleep(FRAME_PROCESS_DELAY)
                
                # Periodic status update (don't reset frame_count - it's used for file alternation)
                if frame_count % 100 == 0:
                    elapsed_time = time.time() - last_frame_time
                    if elapsed_time > 0:
                        fps = 100 / elapsed_time
                        print(f"[{datetime.now().strftime('%H:%M:%S')}] Status: {frame_count} frames processed, ~{fps:.1f} FPS")
                    last_frame_time = time.time()
                    # Don't reset frame_count - keep it incrementing for proper file alternation
                    
        except KeyboardInterrupt:
            print("\n\nStopping detection...")
            break
        except Exception as e:
            print(f"\n✗ Error in detection loop: {e}")
            # Close video writer before reconnecting
            if video_writer_info is not None:
                finalize_video_writer(video_writer_info, discard_short=True)
                video_writer_info = None
            print("Reconnecting in 3 seconds...")
            time.sleep(RECONNECT_DELAY)
        finally:
            if video_writer_info is not None:
                finalize_video_writer(video_writer_info, discard_short=True)
                video_writer_info = None
            
            if cap is not None:
                cap.release()
                print("Stream connection closed")
    
    # Cleanup
    print("\n" + "=" * 60)
    print("Detection stopped. Cleaning up...")
    
    # Save final empty detections
    save_detections([])
    
    # Create a stopped message frame
    stopped_frame = np.zeros((FRAME_HEIGHT, FRAME_WIDTH, 3), dtype=np.uint8)
    cv2.putText(stopped_frame, "Detection Stopped", (FRAME_WIDTH//2 - 200, FRAME_HEIGHT//2 - 30), 
               cv2.FONT_HERSHEY_SIMPLEX, 1.2, (0, 0, 255), 2)
    cv2.putText(stopped_frame, "Please restart the detection script", (FRAME_WIDTH//2 - 250, FRAME_HEIGHT//2 + 30), 
               cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
    save_frame_atomic(stopped_frame, FRAME_FILE)
    
    cv2.destroyAllWindows()
    
    # Remove lock file
    remove_lock()
    
    print("Cleanup complete.")
    print("=" * 60)

if __name__ == "__main__":
    main()

