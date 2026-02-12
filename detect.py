#!/usr/bin/env python3
"""
YOLO Object Detection Script for RTSP Camera Stream
Detects: person, vehicle, animal, and weapon
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
RTSP_URL = "rtsp://admin:admin123@10.245.53.187:554/Preview_01_sub"
PREFER_SUB_STREAM = True  # Use sub-stream for stability (H.264, lower resolution, more stable)
DETECTIONS_FILE = "detections.json"
FRAME_FILE = "current_frame.jpg"  # Frame saved for web display
FRAME_FILE_ALT = "current_frame_alt.jpg"  # Alternate file to avoid locks
FRAME_FILE_TEMP = "current_frame_temp.jpg"  # Temporary file for atomic writes
DETECTED_OBJECTS_DIR = "detected_objects"  # Directory for cropped detected object images
LOCK_FILE = "detect.lock"  # Lock file to prevent multiple instances
RECORDINGS_DIR = "recordings"  # Directory for recorded video files
# Sub-stream Resolution for stability and smooth playback
FRAME_WIDTH = 640   # Sub-stream width (matches camera sub-stream)
FRAME_HEIGHT = 360  # Sub-stream height (matches camera sub-stream)
# Video recording settings
ENABLE_RECORDING = True  # Set to False to disable recording
RECORDING_FPS = 30  # FPS for recorded video (30 FPS is standard for CCTV)
RECORDING_CHUNK_DURATION = 3600  # Record in 1-hour chunks (seconds)
RECORDING_CODEC = 'mp4v'  # Use 'mp4v' for .mp4, 'XVID' for .avi
RECORDING_EXTENSION = '.mp4'  # File extension for recordings
MAX_RECORDINGS_TO_KEEP = 168  # Keep 7 days of 1-hour recordings (168 hours)
CONFIDENCE_THRESHOLD = 0.5
MAX_RECONNECT_ATTEMPTS = 10
RECONNECT_DELAY = 3  # seconds
FRAME_READ_TIMEOUT = 5  # seconds
STREAM_TIMEOUT = 30  # seconds for initial connection
MAX_DETECTED_OBJECT_IMAGES = 20  # Maximum number of object images to keep
FRAME_PROCESS_DELAY = 0.0  # No delay for lowest latency
DETECTION_INTERVAL = 30  # Run detection every N frames (higher = faster frame saving for real-time)
TARGET_FPS = 30  # Target frame rate for smooth real-time video (matches recording FPS)
ENABLE_DETECTION = True  # Set to False to disable detection for absolute lowest latency
FRAME_SAVE_INTERVAL = 1  # Save EVERY frame - CRITICAL for real-time viewing
# Prioritize frame saving over detection for real-time performance
PRIORITIZE_FRAME_SAVING = True  # Save frame BEFORE detection to minimize latency

# Class mapping for YOLO
# COCO dataset classes: 0=person, 2=car, 3=motorcycle, 5=bus, 7=truck (vehicles)
# 14=bird, 15=cat, 16=dog, 17=horse, 18=sheep, 19=cow, 20=elephant, 21=bear, 22=zebra, 23=giraffe (animals)
# Custom model needed for weapon detection, or use general "object" class
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
}

# Target classes we want to detect
TARGET_CLASSES = ["person", "vehicle", "animal"]

# For weapon detection, we'll use a separate approach or custom model
# For now, we'll detect weapons as "knife", "gun", etc. if available in model
WEAPON_CLASSES = ["knife", "gun", "pistol", "rifle", "weapon"]

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
        "clothes_color": "Unknown"
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
    except:
        pass
    
    return attributes

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
                    if conf < CONFIDENCE_THRESHOLD:
                        continue
                    
                    # Get class name
                    class_name = result.names[cls_id]
                    category = map_class_to_category(cls_id, class_name)
                    
                    if category and category in TARGET_CLASSES + ["weapon"]:
                        # Get bounding box coordinates
                        x1, y1, x2, y2 = box.xyxy[0].tolist()
                        
                        bbox = {
                            "x1": int(x1),
                            "y1": int(y1),
                            "x2": int(x2),
                            "y2": int(y2)
                        }
                        
                        # Filter out ground motion (false positives at ground level)
                        if is_ground_motion(bbox, frame_height):
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
                                            "bbox": {"x1": int(wx1), "y1": int(wy1), "x2": int(wx2), "y2": int(wy2)}
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
                if conf < CONFIDENCE_THRESHOLD:
                    continue
                
                cls_id = int(cls_id)
                class_name = results.names[cls_id]
                category = map_class_to_category(cls_id, class_name)
                
                if category and category in TARGET_CLASSES + ["weapon"]:
                    bbox = {
                        "x1": int(x1),
                        "y1": int(y1),
                        "x2": int(x2),
                        "y2": int(y2)
                    }
                    
                    # Filter out ground motion (false positives at ground level)
                    if is_ground_motion(bbox, frame_height):
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
    
    # Limit to top 5 detections by confidence (for real-time performance and clarity)
    if len(detections) > 5:
        detections = sorted(detections, key=lambda x: x.get('confidence', 0), reverse=True)[:5]
    
    # Draw bounding boxes only for top 5 detections (reduced size for clarity)
    for detection in detections:
        bbox = detection.get('bbox', {})
        if bbox:
            x1 = bbox.get('x1', 0)
            y1 = bbox.get('y1', 0)
            x2 = bbox.get('x2', 0)
            y2 = bbox.get('y2', 0)
            category = detection.get('category', 'unknown')
            conf = detection.get('confidence', 0)
            
            color = get_color_for_category(category)
            # Use thinner line (1 instead of 2) and smaller font
            cv2.rectangle(frame, (int(x1), int(y1)), (int(x2), int(y2)), color, 1)
            label = f"{category}: {conf:.2f}"
            # Smaller font scale (0.4 instead of 0.5) and thinner text (1 instead of 2)
            cv2.putText(frame, label, (int(x1), int(y1) - 5),
                       cv2.FONT_HERSHEY_SIMPLEX, 0.4, color, 1)
    
    return detections

def get_color_for_category(category):
    """Get color for drawing bounding boxes"""
    colors = {
        "person": (0, 255, 0),      # Green
        "vehicle": (255, 165, 0),   # Orange
        "animal": (0, 255, 255),    # Yellow
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

def save_frame_atomic(frame, filepath):
    """Save frame atomically to prevent corruption"""
    temp_file = filepath + ".tmp"
    
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
            cv2.IMWRITE_JPEG_QUALITY, 95,  # High quality for accurate 4K video
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
        
        # Wait a moment to ensure file is fully written
        time.sleep(0.01)
        
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

def get_recording_filename():
    """Generate filename for current recording chunk based on timestamp"""
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    filename = f"recording_{timestamp}{RECORDING_EXTENSION}"
    return os.path.join(RECORDINGS_DIR, filename)

def init_video_writer(width, height, fps=RECORDING_FPS):
    """Initialize VideoWriter for recording"""
    if not ENABLE_RECORDING:
        return None
    
    try:
        # Ensure recordings directory exists
        os.makedirs(RECORDINGS_DIR, exist_ok=True)
        
        # Get filename for new recording
        filename = get_recording_filename()
        
        # Define codec and create VideoWriter
        fourcc = cv2.VideoWriter_fourcc(*RECORDING_CODEC)
        writer = cv2.VideoWriter(filename, fourcc, fps, (width, height))
        
        if writer.isOpened():
            print(f"✓ Started recording: {filename}")
            return writer, filename, time.time()
        else:
            print(f"✗ Failed to initialize video writer for: {filename}")
            return None
    except Exception as e:
        print(f"✗ Error initializing video writer: {e}")
        return None

def should_rotate_video(writer_info, chunk_duration=RECORDING_CHUNK_DURATION):
    """Check if video chunk should be rotated"""
    if writer_info is None:
        return False
    _, _, start_time = writer_info
    return (time.time() - start_time) >= chunk_duration

def rotate_video_writer(writer_info, width, height, fps=RECORDING_FPS):
    """Close current video and start a new chunk"""
    if writer_info is None:
        return init_video_writer(width, height, fps)
    
    writer, old_filename, _ = writer_info
    
    try:
        if writer is not None:
            writer.release()
            # Verify file was created and has content
            if os.path.exists(old_filename) and os.path.getsize(old_filename) > 0:
                print(f"✓ Completed recording chunk: {old_filename}")
            else:
                print(f"⚠ Warning: Recording chunk may be empty: {old_filename}")
    except Exception as e:
        print(f"✗ Error closing video writer: {e}")
    
    # Cleanup old recordings
    cleanup_old_recordings()
    
    # Start new recording
    return init_video_writer(width, height, fps)

def cleanup_old_recordings(max_keep=MAX_RECORDINGS_TO_KEEP):
    """Remove old recording files, keeping only the most recent ones"""
    if not os.path.exists(RECORDINGS_DIR):
        return
    
    try:
        # Get all recording files
        recordings = []
        for filename in os.listdir(RECORDINGS_DIR):
            if filename.startswith("recording_") and filename.endswith(RECORDING_EXTENSION):
                filepath = os.path.join(RECORDINGS_DIR, filename)
                if os.path.isfile(filepath):
                    recordings.append((filepath, os.path.getmtime(filepath)))
        
        # Sort by modification time (newest first)
        recordings.sort(key=lambda x: x[1], reverse=True)
        
        # Remove old files beyond max_keep
        if len(recordings) > max_keep:
            for filepath, _ in recordings[max_keep:]:
                try:
                    os.remove(filepath)
                    print(f"✓ Removed old recording: {os.path.basename(filepath)}")
                except Exception as e:
                    print(f"✗ Error removing old recording {filepath}: {e}")
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
    
    camera_ip = "10.245.53.187"
    username = "admin"
    password = "admin123"
    
    # REOLINK HTTP snapshot URLs (prioritize known working format)
    # Tested: http://10.245.53.187/cgi-bin/api.cgi?cmd=Snap&channel=0&rs=wuuPhkmUCeI9WP7T&user=admin&password=admin123
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
    
    # For REOLINK cameras, try HTTP snapshot FIRST (more reliable than RTSP)
    # RTSP often has authentication issues with OpenCV
    print("  Trying HTTP snapshot first (more reliable for REOLINK cameras)...")
    http_result = try_http_snapshot_fallback()
    if http_result:
        snap_url, test_img = http_result
        print(f"\n  ✓ HTTP snapshot mode working - using this method")
        print(f"  Note: Updates every 1-2 seconds (stable and reliable)")
        try:
            save_frame_atomic(test_img, FRAME_FILE)
        except:
            pass
        return "HTTP_SNAPSHOT_MODE"
    
    print("  HTTP snapshot failed, trying RTSP stream...")
    
    # Build comprehensive list of REOLINK RTSP URL variants
    # REOLINK cameras support multiple URL formats - try all common ones
    # Note: Some formats may need authentication in URL, others may need separate auth
    url_variants = []
    base_rtsp = "rtsp://admin:admin123@10.245.53.187:554"
    base_rtsp_no_auth = "rtsp://10.245.53.187:554"  # Try without auth in URL (auth via DESCRIBE)
    
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
        'timeout;10000000|'  # 10 second timeout for connection
        'stimeout;10000000|'  # 10 second socket timeout
        'max_delay;500000|'
        'fflags;nobuffer|'
        'flags;low_delay|'
        'err_detect;ignore_err|'
        'error;0|'
        'thread_queue_size;512|'
        'skip_frame;default|'
        'skip_idct;default|'
        'skip_loop_filter;default'
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
                if "@" not in test_url and "admin" not in test_url:
                    # Some cameras need auth set via OpenCV properties (if supported)
                    try:
                        cap.set(cv2.CAP_PROP_USERNAME, "admin")
                        cap.set(cv2.CAP_PROP_PASSWORD, "admin123")
                    except:
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
            print(f"  Note: This is slower than RTSP but will work for basic monitoring")
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
        print(f"\n  Possible solutions:")
        print(f"  1. Check camera IP: 10.245.53.187")
        print(f"  2. Verify credentials: admin / admin123")
        print(f"  3. Test in VLC: rtsp://admin:admin123@10.245.53.187:554/cam/realmonitor?channel=1&subtype=1")
        print(f"  4. Check camera web interface: http://10.245.53.187")
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
    print("=" * 60)
    print("YOLO Object Detection System")
    print("=" * 60)
    
    # Check for existing instance
    if not check_and_create_lock():
        sys.exit(1)
    
    # Register cleanup function to remove lock on exit
    atexit.register(remove_lock)
    
    print("Initializing...")
    
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
        
        # Initialize video recording
        if ENABLE_RECORDING:
            video_writer_info = init_video_writer(FRAME_WIDTH, FRAME_HEIGHT, RECORDING_FPS)
            if video_writer_info is None:
                print("⚠ Warning: Video recording initialization failed, continuing without recording")
        else:
            video_writer_info = None
        
        try:
            while True:
                if http_mode:
                    # HTTP snapshot mode - fetch snapshot periodically with proper validation
                    ret = False
                    frame = None
                    try:
                        # Try without auth first (for URLs with auth in query string)
                        if 'user=' in http_snapshot_url and 'password=' in http_snapshot_url:
                            response = requests.get(http_snapshot_url, timeout=5)
                        else:
                            response = requests.get(http_snapshot_url, auth=HTTPDigestAuth("admin", "admin123"), timeout=5)
                        
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
                                response = requests.get(http_snapshot_url, auth=HTTPBasicAuth("admin", "admin123"), timeout=5)
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
                    
                    # Add delay between HTTP snapshot requests (1-2 seconds)
                    time.sleep(1.5)
                else:
                    # RTSP mode - read from video stream
                    # Aggressively suppress ALL FFmpeg H.264 decoding errors during frame read
                    # Complete stderr redirection to devnull eliminates all error spam
                    with open(os.devnull, 'w') as devnull:
                        old_stderr = sys.stderr
                        try:
                            sys.stderr = devnull
                            ret, frame = cap.read()
                        finally:
                            sys.stderr = old_stderr
                
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
                
                # Validate frame quality (skip corrupted frames from HEVC errors)
                if not is_valid_frame(frame):
                    consecutive_failures += 1
                    if consecutive_failures > 15:  # Allow more failures due to HEVC errors
                        print(f"Received {consecutive_failures} corrupted frames. Reconnecting...")
                        break
                    # Skip corrupted frame - HEVC errors are common but we continue
                    # No sleep - process immediately for lowest latency
                    continue
                
                # Reset failure count on valid frame
                consecutive_failures = 0
                
                # Resize frame for better display quality (optimized for speed)
                try:
                    current_height, current_width = frame.shape[:2]
                    # Only resize if dimensions don't match (skip if already correct size)
                    if current_width != FRAME_WIDTH or current_height != FRAME_HEIGHT:
                        if current_width > 0 and current_height > 0:
                            # Use faster interpolation for lower latency
                            if current_width < FRAME_WIDTH or current_height < FRAME_HEIGHT:
                                # Upscaling sub-stream - use INTER_LINEAR for speed (sub-stream is already small)
                                frame = cv2.resize(frame, (FRAME_WIDTH, FRAME_HEIGHT), interpolation=cv2.INTER_LINEAR)
                            else:
                                # Downscaling - use area interpolation
                                frame = cv2.resize(frame, (FRAME_WIDTH, FRAME_HEIGHT), interpolation=cv2.INTER_AREA)
                        else:
                            print(f"Invalid frame dimensions: {current_width}x{current_height}")
                            consecutive_failures += 1
                            continue
                except Exception as e:
                    print(f"Error resizing frame: {e}")
                    consecutive_failures += 1
                    continue
                
                # Skip timestamp overlay for absolute lowest latency (uncomment if needed)
                # Timestamp adds ~1-2ms processing time per frame
                # timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                # cv2.putText(frame, timestamp, (10, 30), 
                #            cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
                
                # Save frame IMMEDIATELY for lowest latency (before detection)
                # This ensures web interface gets latest frame without waiting for detection
                saved = False
                current_time = time.time()
                
                # Write frame to video recording (before saving for display)
                if ENABLE_RECORDING and video_writer_info is not None:
                    try:
                        # Check if we need to rotate to a new video chunk
                        if should_rotate_video(video_writer_info, RECORDING_CHUNK_DURATION):
                            video_writer_info = rotate_video_writer(video_writer_info, FRAME_WIDTH, FRAME_HEIGHT, RECORDING_FPS)
                        
                        # Write frame to current video
                        if video_writer_info is not None:
                            writer, _, _ = video_writer_info
                            if writer is not None and writer.isOpened():
                                writer.write(frame)
                    except Exception as e:
                        # Log error but don't stop processing
                        if frame_count % 100 == 0:  # Only log occasionally
                            print(f"⚠ Warning: Error writing to video: {e}")
                
                # CRITICAL: Save frame IMMEDIATELY for real-time viewing
                # Save EVERY frame - this is essential for second-by-second updates
                if frame_count % FRAME_SAVE_INTERVAL == 0:
                    # Alternate between files based on frame count (prevents file locks)
                    # This ensures web interface can always read fresh frames
                    use_alt = (frame_count // FRAME_SAVE_INTERVAL) % 2
                    if use_alt:
                        target_file = FRAME_FILE_ALT
                        alt_file = FRAME_FILE
                    else:
                        target_file = FRAME_FILE
                        alt_file = FRAME_FILE_ALT
                    
                    # Direct save - optimized for REAL-TIME performance with sub-stream
                    try:
                        # Quality 95: High quality for accurate feed display (minimal compression artifacts)
                        # Higher quality ensures accurate representation of camera feed
                        encode_params = [cv2.IMWRITE_JPEG_QUALITY, 95]
                        
                        # Try to save to target file first
                        try:
                            saved = cv2.imwrite(target_file, frame, encode_params)
                        except Exception:
                            saved = False
                        
                        # If that failed, try alternate file immediately
                        if not saved:
                            try:
                                saved = cv2.imwrite(alt_file, frame, encode_params)
                            except Exception:
                                saved = False
                    except Exception:
                        saved = False
                    
                    if saved:
                        last_frame_time = current_time
                        # Frame saved - web interface will pick it up immediately
                
                # Run YOLO detection only on every Nth frame to reduce latency
                # Detection happens AFTER frame is saved, so it doesn't delay display
                detections = []
                if ENABLE_DETECTION and frame_count % DETECTION_INTERVAL == 0:
                    # Run detection on this frame
                    try:
                        if USE_ULTRALYTICS:
                            results = model(frame, verbose=False)
                        else:
                            results = model(frame)
                        # Process detections and draw bounding boxes
                        detections = process_detections(results, frame)
                        
                        # Update frame with detection info and save again with boxes
                        if detections:
                            cv2.putText(frame, f"Objects: {len(detections)}", (10, 60), 
                                       cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
                            # Save frame again with bounding boxes (optional - for better visualization)
                            # This is a second save, so it doesn't delay the initial display
                            try:
                                cv2.imwrite(target_file, frame, encode_params)
                            except:
                                pass
                    except Exception as e:
                        print(f"Error running detection: {e}")
                        # Continue - frame already saved
                
                # Save detections more frequently for faster updates
                if detections and (current_time - last_detection_save >= 0.5):  # Save every 0.5s when detections exist
                    try:
                        save_detections(detections)
                        cleanup_old_object_images()
                        last_detection_save = current_time
                        print(f"[{datetime.now().strftime('%H:%M:%S')}] ✓ Detected {len(detections)} objects")
                    except Exception:
                        pass
                
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
                try:
                    writer, filename, _ = video_writer_info
                    if writer is not None:
                        writer.release()
                except:
                    pass
                video_writer_info = None
            print("Reconnecting in 3 seconds...")
            time.sleep(RECONNECT_DELAY)
        finally:
            # Close video writer if recording
            if video_writer_info is not None:
                try:
                    writer, filename, _ = video_writer_info
                    if writer is not None:
                        writer.release()
                        if os.path.exists(filename) and os.path.getsize(filename) > 0:
                            print(f"✓ Finalized recording: {filename}")
                except Exception as e:
                    print(f"✗ Error closing video writer: {e}")
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

