#!/usr/bin/env python3
"""
GUI Application for YOLO Object Detection System
Provides a user-friendly interface to control and monitor the detection system
"""

import tkinter as tk
from tkinter import ttk, scrolledtext, messagebox, filedialog
import threading
import subprocess
import os
import json
import time
from datetime import datetime
from pathlib import Path
import queue
import sys
import io
from PIL import Image, ImageTk
import cv2
import numpy as np
import requests
from requests.auth import HTTPDigestAuth, HTTPBasicAuth
import base64
import re
from onvif import ONVIFCamera

try:
    from cairosvg import svg2png
except ImportError:
    svg2png = None

# Dark mode color scheme
COLORS = {
    'primary': '#4c8a89',
    'primary_hover': '#5aa5a3',
    'secondary': '#3a506b',
    'tertiary': '#1c2541',
    'dark': '#0b132b',
    'text': '#f5f5f5',
    'text_secondary': '#a1a1aa',
    'text_muted': '#71717a',
    'bg': '#0a0a0a',
    'bg_secondary': '#121212',
    'bg_tertiary': '#1a1a1a',
    'border': '#27272a',
    'border_light': '#3f3f46',
    'card_bg': '#18181b',
    'card_hover': '#1f1f23',
    'sidebar_bg': '#0f0f0f',
    'sidebar_hover': '#1a1a1a',
    'sidebar_active': '#4c8a89',
    'success': '#10b981',
    'error': '#ef4444',
    'warning': '#f59e0b',
    'info': '#3b82f6',
}

class LoginWindow:
    def __init__(self):
        self.root = tk.Tk()
        self.root.title("CCTV - Login")
        self.root.geometry("1200x700")
        self.root.resizable(False, False)
        self.root.configure(bg='#f5f7fa')
        
        # Set favicon
        try:
            if os.path.exists('images/favicon.ico'):
                self.root.iconbitmap('images/favicon.ico')
        except:
            pass
        
        # Center window
        self.center_window()
        
        # Login credentials (in production, use database)
        self.credentials = {
            'admin': 'admin123',
            'user': 'user123'
        }
        
        self.logged_in = False
        self.username = None
        
        self.create_login_ui()
        
    def center_window(self):
        """Center the window on screen"""
        self.root.update_idletasks()
        width = self.root.winfo_width()
        height = self.root.winfo_height()
        x = (self.root.winfo_screenwidth() // 2) - (width // 2)
        y = (self.root.winfo_screenheight() // 2) - (height // 2)
        self.root.geometry(f'{width}x{height}+{x}+{y}')
    
    def create_login_ui(self):
        """Create login interface matching web-based style"""
        # Main container with two columns
        main_container = tk.Frame(self.root, bg='#f5f7fa')
        main_container.pack(fill=tk.BOTH, expand=True)
        
        # Left side - Hero section with logo and tagline
        left_panel = tk.Frame(main_container, bg='#f5f7fa')
        left_panel.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=60, pady=60)
        
        # Logo and text container (text-only – no logo)
        hero_frame = tk.Frame(left_panel, bg='#f5f7fa')
        hero_frame.pack(expand=True)
        
        # AlerTaraQC title (wordmark)
        title_frame = tk.Frame(hero_frame, bg='#f5f7fa')
        title_frame.pack(anchor='w', pady=(0, 15))
        
        # "Aler" in teal
        aler_label = tk.Label(
            title_frame,
            text="Aler",
            font=('Segoe UI', 48, 'bold'),
            bg='#f5f7fa',
            fg=COLORS['primary']
        )
        aler_label.pack(side=tk.LEFT)
        
        # "TaraQC" in dark gray
        taraqc_label = tk.Label(
            title_frame,
            text="TaraQC",
            font=('Segoe UI', 48, 'bold'),
            bg='#f5f7fa',
            fg='#2a2a2a'
        )
        taraqc_label.pack(side=tk.LEFT)
        
        # Tagline under the title
        tagline_label = tk.Label(
            hero_frame,
            text="24/7 surveillance and instant alert system for potential threats.",
            font=('Segoe UI', 14),
            bg='#f5f7fa',
            fg=COLORS['text_secondary'],
            wraplength=500,
            justify='left'
        )
        tagline_label.pack(anchor='w', pady=(0, 20))
        
        # Right side - Login card
        right_panel = tk.Frame(main_container, bg='#f5f7fa')
        right_panel.pack(side=tk.RIGHT, fill=tk.BOTH, expand=False, padx=60, pady=60)
        
        # Login card with dark blue-gray background
        login_card = tk.Frame(
            right_panel,
            bg=COLORS['secondary'],  # Dark blue-gray
            relief=tk.FLAT
        )
        login_card.pack(fill=tk.BOTH, expand=True)
        login_card.configure(borderwidth=0)
        
        # Login form inside card
        form_frame = tk.Frame(login_card, bg=COLORS['secondary'], padx=40, pady=50)
        form_frame.pack(fill=tk.BOTH, expand=True)
        
        # Login title
        login_title = tk.Label(
            form_frame,
            text="Login",
            font=('Segoe UI', 24, 'bold'),
            bg=COLORS['secondary'],
            fg='#ffffff'
        )
        login_title.pack(anchor='w', pady=(0, 30))
        
        # Error label (initially hidden)
        self.error_label = tk.Label(
            form_frame,
            text="",
            font=('Segoe UI', 10),
            bg=COLORS['secondary'],
            fg='#ef4444',
            wraplength=300,
            justify='left'
        )
        self.error_label.pack(anchor='w', pady=(0, 15), fill=tk.X)
        
        # Username field
        username_label = tk.Label(
            form_frame,
            text="Username",
            font=('Segoe UI', 11),
            bg=COLORS['secondary'],
            fg='#ffffff',
            anchor='w'
        )
        username_label.pack(fill=tk.X, pady=(0, 8))
        
        username_entry_frame = tk.Frame(form_frame, bg='#ffffff')
        username_entry_frame.pack(fill=tk.X, pady=(0, 20))
        
        self.username_entry = tk.Entry(
            username_entry_frame,
            font=('Segoe UI', 12),
            relief=tk.FLAT,
            borderwidth=0,
            highlightthickness=0,
            bg='#ffffff',
            fg='#171717',
            insertbackground='#171717'
        )
        self.username_entry.pack(fill=tk.BOTH, expand=True, ipady=12, ipadx=10)
        self.username_entry.insert(0, "Enter your username")
        self.username_entry.config(fg='#999999')
        self.username_entry.bind('<FocusIn>', self.on_username_focus_in)
        self.username_entry.bind('<FocusOut>', self.on_username_focus_out)
        self.username_entry.bind('<Return>', lambda e: self.password_entry.focus())
        
        # Password field
        password_label = tk.Label(
            form_frame,
            text="Password",
            font=('Segoe UI', 11),
            bg=COLORS['secondary'],
            fg='#ffffff',
            anchor='w'
        )
        password_label.pack(fill=tk.X, pady=(0, 8))
        
        password_entry_frame = tk.Frame(form_frame, bg='#ffffff')
        password_entry_frame.pack(fill=tk.X, pady=(0, 15))
        
        self.password_entry = tk.Entry(
            password_entry_frame,
            font=('Segoe UI', 12),
            show='*',
            relief=tk.FLAT,
            borderwidth=0,
            highlightthickness=0,
            bg='#ffffff',
            fg='#171717',
            insertbackground='#171717'
        )
        self.password_entry.pack(fill=tk.BOTH, expand=True, ipady=12, ipadx=10)
        self.password_entry.insert(0, "••••••••")
        self.password_entry.config(fg='#999999', show='')
        self.password_entry.bind('<FocusIn>', self.on_password_focus_in)
        self.password_entry.bind('<FocusOut>', self.on_password_focus_out)
        self.password_entry.bind('<Return>', lambda e: self.login())
        
        # Forgot password link
        forgot_link = tk.Label(
            form_frame,
            text="Forgot password?",
            font=('Segoe UI', 10),
            bg=COLORS['secondary'],
            fg='#a1a1aa',
            cursor='hand2'
        )
        forgot_link.pack(anchor='w', pady=(0, 25))
        
        # Sign in button
        signin_btn = tk.Button(
            form_frame,
            text="Sign in",
            font=('Segoe UI', 12, 'bold'),
            bg=COLORS['primary'],
            fg='#ffffff',
            relief=tk.FLAT,
            cursor='hand2',
            command=self.login,
            padx=20,
            pady=14,
            activebackground='#4ca8a6',
            activeforeground='#ffffff'
        )
        signin_btn.pack(fill=tk.X)
        
        # Focus on username entry
        self.root.after(100, lambda: self.username_entry.focus())
    
    def on_username_focus_in(self, event):
        """Handle username field focus in"""
        if self.username_entry.get() == "Enter your username":
            self.username_entry.delete(0, tk.END)
            self.username_entry.config(fg='#171717')
    
    def on_username_focus_out(self, event):
        """Handle username field focus out"""
        if not self.username_entry.get():
            self.username_entry.insert(0, "Enter your username")
            self.username_entry.config(fg='#999999')
    
    def on_password_focus_in(self, event):
        """Handle password field focus in"""
        if self.password_entry.get() == "••••••••":
            self.password_entry.delete(0, tk.END)
            self.password_entry.config(fg='#171717', show='*')
    
    def on_password_focus_out(self, event):
        """Handle password field focus out"""
        if not self.password_entry.get():
            self.password_entry.insert(0, "••••••••")
            self.password_entry.config(fg='#999999', show='')
    
    def login(self):
        """Handle login"""
        username = self.username_entry.get().strip()
        password = self.password_entry.get().strip()
        
        # Remove placeholder text
        if username == "Enter your username":
            username = ""
        if password == "••••••••":
            password = ""
        
        if not username or not password:
            self.error_label.config(text="Please enter both username and password")
            return
        
        if username in self.credentials and self.credentials[username] == password:
            self.logged_in = True
            self.username = username
            self.root.destroy()
        else:
            self.error_label.config(text="Invalid username or password")
            self.password_entry.delete(0, tk.END)
            self.password_entry.insert(0, "••••••••")
            self.password_entry.config(fg='#999999', show='')
    
    def show(self):
        """Show login window and return login status"""
        self.root.mainloop()
        return self.logged_in, self.username

class DetectionGUI:
    def __init__(self, root, username):
        self.root = root
        self.username = username
        self.root.title("CCTV")
        
        # Launch in fullscreen/maximize
        self.root.state('zoomed')  # Windows
        try:
            self.root.attributes('-zoomed', True)  # Linux
        except:
            pass
        
        self.root.minsize(1400, 800)
        self.root.configure(bg=COLORS['bg'])
        
        # Set favicon
        try:
            if os.path.exists('images/favicon.ico'):
                self.root.iconbitmap('images/favicon.ico')
        except:
            pass
        
        # Detection process
        self.detection_process = None
        self.detection_thread = None
        self.is_running = False
        self.current_page = 'live_view'
        self.sidebar_collapsed = False
        
        # Live feed
        self.current_frame = None
        self.last_frame_time = 0
        self.frame_lock = threading.Lock()
        self.frame_update_thread = None
        self.fullscreen_window = None
        self.camera_grid_window = None
        self.active_camera_index = 0
        self.recording_active = False
        self.talk_active = False
        self.audio_alarm_active = False
        self.light_active = False
        self.microphone_process = None
        self.alarm_thread = None
        
        # Detected objects
        self.detected_objects = []
        self.last_detection_check = 0
        
        # Stats for status bar
        self.stats = {'fps': 30.0}
        
        # Control buttons state
        self.control_buttons = {}
        
        # Initialize settings variables
        self.detection_interval_var = tk.IntVar(value=30)
        self.confidence_var = tk.DoubleVar(value=0.5)
        self.enable_detection_var = tk.BooleanVar(value=True)
        self.enable_recording_var = tk.BooleanVar(value=True)
        
        # Camera API sessions (for Dahua-style cameras that need session auth)
        self.camera_sessions = {}
        
        # ONVIF camera clients
        self.onvif_clients = {}
        
        # Smart Night Mode state
        self.night_mode_active = False
        self.last_brightness_check = 0
        # Option to show raw feed without any processing (for accuracy)
        # Set to True to show completely unprocessed camera feed (most accurate)
        self.show_raw_feed = True  # Default to True for accurate feed
        
        # Frame buffering to prevent flicker
        self.last_displayed_frame_time = 0
        self.frame_buffer = None
        self.last_resize_dimensions = (0, 0, 0, 0)
        
        # Load cameras
        self.cameras = self.load_cameras()
        
        # Create UI
        self.create_sidebar()
        self.create_main_content()
        
        # Load initial page
        self.show_page('live_view')
        
        # Auto-start detection
        self.auto_start_detection()
    
    def load_cameras(self):
        """Load cameras from cameras.json"""
        try:
            if os.path.exists('cameras.json'):
                with open('cameras.json', 'r') as f:
                    cameras = json.load(f)
                    return cameras if isinstance(cameras, list) else []
        except Exception as e:
            print(f"Error loading cameras: {e}")
        return []
    
    def create_sidebar(self):
        """Create sidebar navigation"""
        self.sidebar = tk.Frame(self.root, bg=COLORS['sidebar_bg'], width=180)
        self.sidebar.pack(side=tk.LEFT, fill=tk.Y)
        self.sidebar.pack_propagate(False)
        
        # Sidebar header with logo
        header_frame = tk.Frame(self.sidebar, bg=COLORS['sidebar_bg'], pady=25)
        header_frame.pack(fill=tk.X, padx=20)
        
        # Logo - decreased size
        logo_path = 'images/tara.png'
        if os.path.exists(logo_path):
            try:
                logo_img = Image.open(logo_path)
                logo_img = logo_img.resize((60, 60), Image.Resampling.LANCZOS)
                logo_photo = ImageTk.PhotoImage(logo_img)
                logo_label = tk.Label(
                    header_frame,
                    image=logo_photo,
                    bg=COLORS['sidebar_bg']
                )
                logo_label.image = logo_photo
                logo_label.pack()
            except:
                pass
        
        # Welcome text - decreased size
        subtitle_label = tk.Label(
            header_frame,
            text=f"Welcome, {self.username}",
            font=('Segoe UI', 9),
            bg=COLORS['sidebar_bg'],
            fg=COLORS['text_secondary']
        )
        subtitle_label.pack(pady=(10, 0))
        
        # Navigation menu
        nav_frame = tk.Frame(self.sidebar, bg=COLORS['sidebar_bg'])
        nav_frame.pack(fill=tk.BOTH, expand=True, padx=15, pady=25)
        
        # Menu items matching reference
        self.menu_items = [
            ('live_view', '📹', 'Live View'),
            ('playback', '▶', 'Playback'),
            ('recording', '🔴', 'Recordings'),
            ('logs', '📋', 'Logs'),
            ('camera_management', '📷', 'Camera Control'),
        ]
        
        self.menu_buttons = {}
        for item_id, icon, label in self.menu_items:
            btn = tk.Button(
                nav_frame,
                text=f"{icon}  {label}",
                font=('Segoe UI', 10),  # Decreased from 13 to 10
                bg=COLORS['sidebar_bg'],
                fg=COLORS['text'],
                activebackground=COLORS['sidebar_hover'],
                activeforeground=COLORS['text'],
                relief=tk.FLAT,
                anchor='w',
                padx=15,  # Decreased from 20
                pady=12,  # Decreased from 18
                cursor='hand2',
                command=lambda id=item_id: self.show_page(id),
                borderwidth=0
            )
            btn.pack(fill=tk.X, pady=3)  # Decreased from 4
            self.menu_buttons[item_id] = btn
        
        # Logout button removed from sidebar as requested
        
        # Update active button
        self.update_active_menu()
    
    def update_active_menu(self):
        """Update active menu button styling"""
        for item_id, btn in self.menu_buttons.items():
            if item_id == self.current_page:
                btn.config(bg=COLORS['sidebar_active'], fg='#ffffff')
            else:
                btn.config(bg=COLORS['sidebar_bg'], fg=COLORS['text'])
    
    def show_page(self, page_id):
        """Show a specific page"""
        self.current_page = page_id
        self.update_active_menu()
        
        # Clear main content
        for widget in self.main_content.winfo_children():
            widget.destroy()
        
        # Show appropriate page
        if page_id == 'live_view':
            self.create_live_view_page()
        elif page_id == 'recording':
            self.create_recording_page()
        elif page_id == 'playback':
            self.create_playback_page()
        elif page_id == 'camera_management':
            self.create_camera_management_page()
        elif page_id == 'settings':
            self.create_settings_page()
        elif page_id == 'logs':
            self.create_logs_page()
        elif page_id == 'user_management':
            self.create_user_management_page()
        elif page_id == 'maintenance':
            self.create_maintenance_page()
    
    def create_main_content(self):
        """Create main content area with top bar"""
        # Top bar
        self.top_bar = tk.Frame(self.root, bg=COLORS['bg_secondary'], height=60)
        self.top_bar.pack(side=tk.TOP, fill=tk.X)
        self.top_bar.pack_propagate(False)
        
        # Title in top bar
        title_frame = tk.Frame(self.top_bar, bg=COLORS['bg_secondary'])
        title_frame.pack(side=tk.LEFT, padx=30, pady=15)
        
        tk.Label(
            title_frame,
            text="CCTV",
            font=('Segoe UI', 16, 'bold'),
            bg=COLORS['bg_secondary'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT)
        
        # Top bar icons (Settings, Alerts, Exit)
        icons_frame = tk.Frame(self.top_bar, bg=COLORS['bg_secondary'])
        icons_frame.pack(side=tk.RIGHT, padx=20, pady=15)
        
        # Settings icon with dropdown menu
        settings_icon = tk.Button(
            icons_frame,
            text="⚙",
            font=('Segoe UI', 18),
            bg=COLORS['bg_secondary'],
            fg=COLORS['text'],
            relief=tk.FLAT,
            cursor='hand2',
            command=self.show_settings_menu,
            width=3,
            borderwidth=0
        )
        settings_icon.pack(side=tk.LEFT, padx=5)
        
        # Alerts icon
        alerts_icon = tk.Button(
            icons_frame,
            text="🔔",
            font=('Segoe UI', 18),
            bg=COLORS['bg_secondary'],
            fg=COLORS['text'],
            relief=tk.FLAT,
            cursor='hand2',
            width=3,
            borderwidth=0
        )
        alerts_icon.pack(side=tk.LEFT, padx=5)
        
        # Exit icon removed as requested
        
        # Main content area
        self.main_content = tk.Frame(self.root, bg=COLORS['bg'])
        self.main_content.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)
    
    def create_live_view_page(self):
        """Create Live View page with feed, overlay buttons, and detected objects panel"""
        # Main container with paned window for resizable sections
        paned = tk.PanedWindow(self.main_content, orient=tk.HORIZONTAL, bg=COLORS['bg'], sashwidth=2, sashrelief=tk.FLAT)
        paned.pack(fill=tk.BOTH, expand=True)
        
        # Left side - Live feed container (increased size)
        feed_panel = tk.Frame(paned, bg='#000000')
        paned.add(feed_panel, minsize=800)  # Increased from 400 to 800
        
        # Live feed container - fills entire space
        feed_container = tk.Frame(feed_panel, bg='#000000')
        feed_container.pack(fill=tk.BOTH, expand=True)
        
        self.live_feed_label = tk.Label(
            feed_container,
            text="Loading feed...",
            font=('Segoe UI', 14),
            bg='#000000',
            fg=COLORS['text_secondary'],
            anchor=tk.CENTER
        )
        self.live_feed_label.pack(fill=tk.BOTH, expand=True)
        
        # Bind double-click for camera grid
        self.live_feed_label.bind('<Double-Button-1>', self.show_camera_grid)
        self.live_feed_label.bind('<Enter>', lambda e: self.live_feed_label.config(cursor='hand2'))
        
        # Control buttons overlay - positioned at lower-middle inside feed using place()
        self.control_buttons = {}
        buttons_container = tk.Frame(feed_container, bg='#1a1a1a', relief=tk.FLAT)
        # Place buttons container at lower-middle of feed_container (overlay on video)
        buttons_container.place(relx=0.5, rely=0.92, anchor=tk.CENTER)
        
        # Control buttons: Talk, Audio Alarm, Light, Record, Snapshot
        control_buttons = [
            ('🎤', 'Talk', self.talk_action, 'talk'),
            ('🔊', 'Audio Alarm', self.audio_alarm_action, 'audio_alarm'),
            ('💡', 'Light', self.light_action, 'light'),
            ('⏺', 'Record', self.record_action, 'record'),  # Changed to circle icon
            ('📸', 'Snapshot', self.snapshot_action, 'snapshot'),  # Changed to camera icon
        ]
        
        for icon, tooltip, command, key in control_buttons:
            btn = tk.Button(
                buttons_container,
                text=icon,
                font=('Segoe UI', 20),
                bg='#1a1a1a',
                fg='#ffffff',
                activebackground='#2a2a2a',
                activeforeground='#ffffff',
                relief=tk.FLAT,
                cursor='hand2',
                command=command,
                width=3,
                height=1,
                borderwidth=0,
                padx=12,
                pady=8
            )
            btn.pack(side=tk.LEFT, padx=6)
            self.control_buttons[key] = btn
        
        # Right side - Detected objects panel (decreased size)
        objects_panel = tk.Frame(paned, bg=COLORS['card_bg'], width=250)
        paned.add(objects_panel, minsize=200)  # Decreased from 300 to 200
        objects_panel.pack_propagate(False)
        
        # Panel header
        objects_header = tk.Frame(objects_panel, bg=COLORS['card_bg'], pady=15, padx=20)
        objects_header.pack(fill=tk.X)
        
        tk.Label(
            objects_header,
            text="Detected Objects",
            font=('Segoe UI', 14, 'bold'),
            bg=COLORS['card_bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT)
        
        # Scrollable frame for detected objects
        canvas = tk.Canvas(objects_panel, bg=COLORS['card_bg'], highlightthickness=0)
        scrollbar = tk.Scrollbar(objects_panel, orient="vertical", command=canvas.yview)
        self.objects_scrollable_frame = tk.Frame(canvas, bg=COLORS['card_bg'])
        
        self.objects_scrollable_frame.bind(
            "<Configure>",
            lambda e: canvas.configure(scrollregion=canvas.bbox("all"))
        )
        
        canvas.create_window((0, 0), window=self.objects_scrollable_frame, anchor="nw")
        canvas.configure(yscrollcommand=scrollbar.set)
        
        canvas.pack(side="left", fill="both", expand=True, padx=10, pady=10)
        scrollbar.pack(side="right", fill="y")
        
        # Start live feed update and detected objects update
        self.update_live_feed()
        self.update_status_bar()
        self.update_detected_objects()
    
    def update_live_feed(self):
        """Update live feed display with high quality and reduced flickering"""
        if self.current_page == 'live_view' and hasattr(self, 'live_feed_label'):
            try:
                # Try to load current frame
                frame_files = ['current_frame.jpg', 'current_frame_alt.jpg']
                img = None
                latest_file_time = 0
                
                for frame_file in frame_files:
                    if os.path.exists(frame_file):
                        try:
                            # Check file modification time to avoid reading same frame
                            file_time = os.path.getmtime(frame_file)
                            if file_time > self.last_frame_time and file_time > latest_file_time:
                                # Validate file size first
                                file_size = os.path.getsize(frame_file)
                                if file_size < 100:  # Too small to be valid
                                    continue
                                
                                # Try to read with PIL first (better error handling for corrupted JPEGs)
                                try:
                                    pil_img = Image.open(frame_file)
                                    pil_img.verify()  # Verify it's a valid image
                                    pil_img.close()
                                    
                                    # Now read with OpenCV
                                    temp_img = cv2.imread(frame_file, cv2.IMREAD_COLOR)
                                    if temp_img is not None and temp_img.size > 0:
                                        # Additional validation
                                        height, width = temp_img.shape[:2]
                                        if width > 0 and height > 0:
                                            img = temp_img
                                            latest_file_time = file_time
                                except (IOError, OSError, Exception) as e:
                                    # Corrupted JPEG - skip this file
                                    continue
                        except Exception as e:
                            continue
                
                # Only update if frame is actually new (prevents flicker from re-displaying same frame)
                # Only update if frame is actually new and significantly different (prevents flicker)
                # Add threshold to prevent rapid re-displays (camera updates every 1.5s for HTTP mode)
                frame_time_threshold = 0.5  # Only update if frame is at least 0.5s newer (compatible with camera rate)
                time_diff = latest_file_time - self.last_displayed_frame_time if hasattr(self, 'last_displayed_frame_time') else 999
                if img is not None and latest_file_time > self.last_frame_time and time_diff >= frame_time_threshold:
                    with self.frame_lock:
                        # Check brightness FIRST on original image before any processing
                        # This gives us accurate brightness reading
                        gray_original = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
                        avg_brightness_original = np.mean(gray_original)
                        
                        # Get display size (use cached size to avoid flicker from winfo_width/height)
                        if not hasattr(self, 'cached_feed_width') or not hasattr(self, 'cached_feed_height'):
                            self.cached_feed_width = max(1, self.live_feed_label.winfo_width())
                            self.cached_feed_height = max(1, self.live_feed_label.winfo_height())
                        
                        feed_width = self.cached_feed_width
                        feed_height = self.cached_feed_height
                        
                        # Recalculate size less frequently (every 30 frames) to reduce flicker from resize operations
                        if not hasattr(self, 'frame_count'):
                            self.frame_count = 0
                        self.frame_count += 1
                        if self.frame_count % 50 == 0:  # Even less frequent size checks = less flicker
                            try:
                                new_width = max(1, self.live_feed_label.winfo_width())
                                new_height = max(1, self.live_feed_label.winfo_height())
                                # Only update if size actually changed significantly (prevents unnecessary resizing)
                                if abs(new_width - self.cached_feed_width) > 10 or abs(new_height - self.cached_feed_height) > 10:
                                    self.cached_feed_width = new_width
                                    self.cached_feed_height = new_height
                                    feed_width = self.cached_feed_width
                                    feed_height = self.cached_feed_height
                            except:
                                pass
                        
                        if feed_width > 1 and feed_height > 1:
                            # Calculate scale to fit while maintaining aspect ratio
                            img_height, img_width = img.shape[:2]
                            
                            # Cache resized dimensions to prevent flicker from repeated calculations
                            if not hasattr(self, 'last_resize_dimensions'):
                                self.last_resize_dimensions = (0, 0, 0, 0)
                            
                            scale_w = feed_width / img_width
                            scale_h = feed_height / img_height
                            scale = min(scale_w, scale_h, 1.0)  # Don't upscale
                            
                            new_width = int(img_width * scale) if scale < 1.0 else img_width
                            new_height = int(img_height * scale) if scale < 1.0 else img_height
                            
                            # Only resize if dimensions actually changed significantly (prevents flicker)
                            # Use larger threshold to prevent micro-adjustments that cause flicker
                            last_w, last_h, last_img_w, last_img_h = self.last_resize_dimensions
                            width_diff = abs(new_width - last_w) if last_w > 0 else 999
                            height_diff = abs(new_height - last_h) if last_h > 0 else 999
                            
                            # Only resize if change is significant (more than 5 pixels) or first time
                            if (width_diff > 5 or height_diff > 5 or last_w == 0 or 
                                img_width != last_img_w or img_height != last_img_h):
                                if scale < 1.0:
                                    # Use INTER_AREA for downscaling (better quality, less flicker)
                                    img = cv2.resize(img, (new_width, new_height), interpolation=cv2.INTER_AREA)
                                self.last_resize_dimensions = (new_width, new_height, img_width, img_height)
                            
                            # Option to show completely raw feed without any processing
                            if not self.show_raw_feed:
                                # Smart Night Mode: Use original brightness reading for accurate detection
                                avg_brightness = avg_brightness_original
                                
                                # More conservative thresholds - only enhance truly dark scenes
                                # If brightness < 30: Very dark (minimal night mode enhancement)
                                # If brightness >= 30: No enhancement - show original feed
                                VERY_DARK_THRESHOLD = 30  # Much lower threshold - only truly dark
                                
                                is_very_dark = avg_brightness < VERY_DARK_THRESHOLD
                                
                                # Update night mode state (only for very dark scenes)
                                if is_very_dark and not self.night_mode_active:
                                    self.night_mode_active = True
                                elif not is_very_dark and self.night_mode_active and avg_brightness > (VERY_DARK_THRESHOLD + 10):
                                    # Turn off night mode when not very dark (with 10 unit buffer)
                                    self.night_mode_active = False
                                
                                # Only apply minimal enhancement for truly dark scenes
                                # For normal/bright scenes, show original feed without modification
                                if is_very_dark:
                                    # Very dark: Minimal night mode enhancement (preserve accuracy)
                                    lab = cv2.cvtColor(img, cv2.COLOR_BGR2LAB)
                                    l, a, b = cv2.split(lab)
                                    
                                    # Light CLAHE for very dark scenes only
                                    clahe = cv2.createCLAHE(clipLimit=1.5, tileGridSize=(8,8))
                                    l = clahe.apply(l)
                                    
                                    # Small brightness boost for very dark scenes only
                                    l = cv2.add(l, 10)  # Reduced from 30
                                    
                                    lab = cv2.merge([l, a, b])
                                    img = cv2.cvtColor(lab, cv2.COLOR_LAB2BGR)
                                    
                                    # Very light sharpening for very dark scenes only
                                    kernel = np.array([[-1,-1,-1],
                                                      [-1, 9,-1],
                                                      [-1,-1,-1]]) * 0.05  # Reduced from 0.15
                                    img = cv2.filter2D(img, -1, kernel)
                                
                                # For all other scenes (bright or moderately dark), show original feed
                                # No processing - preserve camera's original image
                            else:
                                # Raw feed mode - absolutely no processing, show camera feed as-is
                                # This ensures 100% accuracy of what the camera sees
                                pass
                            
                            # Convert BGR to RGB
                            img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
                            img_pil = Image.fromarray(img_rgb)
                            
                            # Create PhotoImage and update
                            img_tk = ImageTk.PhotoImage(image=img_pil)
                            
                            # Update in one atomic operation to reduce flicker
                            # Store reference BEFORE config to prevent flicker
                            old_image = getattr(self.live_feed_label, 'image', None)
                            self.live_feed_label.image = img_tk  # Keep reference FIRST to prevent garbage collection
                            self.live_feed_label.config(image=img_tk, text='')
                            self.current_frame = img.copy()  # Store copy for snapshot
                            self.last_frame_time = latest_file_time
                            self.last_displayed_frame_time = latest_file_time  # Track displayed frame to prevent re-display flicker
                            
                            # Clear old image reference after update to free memory
                            if old_image:
                                del old_image
            except Exception as e:
                pass
        
        # Schedule next update at camera-compatible rate
        # HTTP snapshot mode: ~1.5s between frames, so check every 500ms (2x per second)
        # RTSP mode: can be faster, but use 200ms (5 FPS check rate) to avoid flicker
        # This matches the actual camera frame rate and prevents flickering
        self.root.after(200, self.update_live_feed)  # 200ms = 5 FPS check rate (compatible with camera)
    
    def show_camera_grid(self, event=None):
        """Show camera grid view with active camera"""
        if self.camera_grid_window is None or not self.camera_grid_window.winfo_exists():
            self.create_camera_grid_window()
        else:
            self.camera_grid_window.lift()
    
    def create_camera_grid_window(self):
        """Create camera grid window with dark mode"""
        self.camera_grid_window = tk.Toplevel(self.root)
        self.camera_grid_window.title("Camera Grid")
        self.camera_grid_window.geometry("1400x900")
        self.camera_grid_window.configure(bg=COLORS['bg'])
        
        # Header
        header = tk.Frame(self.camera_grid_window, bg=COLORS['bg'], pady=25, padx=30)
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text="Camera Grid",
            font=('Segoe UI', 24, 'bold'),
            bg=COLORS['bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT)
        
        tk.Button(
            header,
            text="✕ Close",
            font=('Segoe UI', 11, 'bold'),
            bg=COLORS['error'],
            fg='#ffffff',
            relief=tk.FLAT,
            command=lambda: self.camera_grid_window.destroy(),
            padx=18,
            pady=8,
            cursor='hand2',
            activebackground='#dc2626',
            activeforeground='#ffffff'
        ).pack(side=tk.RIGHT)
        
        # Grid container
        grid_frame = tk.Frame(self.camera_grid_window, bg=COLORS['bg'], padx=25, pady=25)
        grid_frame.pack(fill=tk.BOTH, expand=True)
        
        # Create 2x2 grid
        cameras = self.load_cameras()
        grid_labels = []
        
        for i in range(4):
            row = i // 2
            col = i % 2
            
            cell = tk.Frame(grid_frame, bg='#2a2a2a', relief=tk.FLAT, borderwidth=2)
            cell.grid(row=row, col=col, padx=10, pady=10, sticky='nsew')
            grid_frame.grid_rowconfigure(row, weight=1)
            grid_frame.grid_columnconfigure(col, weight=1)
            
            if i < len(cameras):
                # Camera feed
                feed_label = tk.Label(
                    cell,
                    bg='#000000',
                    fg=COLORS['text_secondary'],
                    text="Loading..."
                )
                feed_label.pack(fill=tk.BOTH, expand=True)
                
                # Highlight active camera
                if i == self.active_camera_index:
                    cell.configure(highlightbackground=COLORS['primary'], highlightthickness=3)
                else:
                    cell.configure(highlightbackground=COLORS['border'], highlightthickness=2)
                
                grid_labels.append((feed_label, cameras[i], i == self.active_camera_index))
            else:
                # Empty slot
                empty_label = tk.Label(
                    cell,
                    bg=COLORS['bg_tertiary'],
                    fg=COLORS['text_muted'],
                    text="📷\nNo Camera",
                    font=('Segoe UI', 16)
                )
                empty_label.pack(fill=tk.BOTH, expand=True)
                cell.configure(highlightbackground=COLORS['border'], highlightthickness=2)
                grid_labels.append((empty_label, None, False))
        
        # Update grid feeds
        def update_grid():
            if self.camera_grid_window and self.camera_grid_window.winfo_exists():
                for label, camera, is_active in grid_labels:
                    if camera:
                        # Load frame for this camera
                        frame_files = ['current_frame.jpg', 'current_frame_alt.jpg']
                        for frame_file in frame_files:
                            if os.path.exists(frame_file):
                                try:
                                    img = cv2.imread(frame_file)
                                    if img is not None:
                                        # Resize to fit cell
                                        cell_width = label.winfo_width()
                                        cell_height = label.winfo_height()
                                        
                                        if cell_width > 1 and cell_height > 1:
                                            img_height, img_width = img.shape[:2]
                                            scale = min(cell_width / img_width, cell_height / img_height, 1.0)
                                            
                                            if scale < 1.0:
                                                new_width = int(img_width * scale)
                                                new_height = int(img_height * scale)
                                                img = cv2.resize(img, (new_width, new_height), interpolation=cv2.INTER_LANCZOS4)
                                            
                                            img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
                                            img_pil = Image.fromarray(img_rgb)
                                            img_tk = ImageTk.PhotoImage(image=img_pil)
                                            
                                            label.config(image=img_tk, text='')
                                            label.image = img_tk
                                            break
                                except:
                                    pass
                
                self.camera_grid_window.after(200, update_grid)
        
        update_grid()
    
    def update_status_bar(self):
        """Update status bar information"""
        if self.current_page == 'live_view' and hasattr(self, 'status_label'):
            # Update status
            status_text = "Status: Recording" if self.is_running else "Status: Stopped"
            self.status_label.config(text=status_text)
            
            # Update FPS
            fps_text = f"FPS: {self.stats['fps']:.0f}" if self.stats['fps'] > 0 else "FPS: 0"
            if hasattr(self, 'fps_label_status'):
                self.fps_label_status.config(text=fps_text)
            
            # Update night mode status
            if hasattr(self, 'night_mode_status'):
                night_mode_text = "🌙 Night Mode: ON" if self.night_mode_active else "☀️ Night Mode: OFF"
                self.night_mode_status.config(text=night_mode_text)
            
            # Update detections
            try:
                if os.path.exists('detections.json'):
                    with open('detections.json', 'r') as f:
                        data = json.load(f)
                    detections = data.get('detections', []) if isinstance(data, dict) else (data if isinstance(data, list) else [])
                    categories = set()
                    for det in detections:
                        cat = det.get('category', '').capitalize()
                        if cat:
                            categories.add(cat)
                    det_text = f"Detections: {', '.join(categories) if categories else 'None'}"
                    if hasattr(self, 'detections_status'):
                        self.detections_status.config(text=det_text)
            except:
                pass
        
        # Update every second
        self.root.after(1000, self.update_status_bar)
    
    def talk_action(self):
        """Handle talk button click - toggle talk mode (microphone to camera speaker)"""
        self.talk_active = not self.talk_active
        if self.talk_active:
            self.control_buttons['talk'].config(bg=COLORS['primary'], fg='#ffffff')
            # Start microphone capture and stream to camera
            try:
                import pyaudio
                
                # Get current camera info
                cameras = self.load_cameras()
                if not cameras or self.active_camera_index >= len(cameras):
                    messagebox.showwarning("Warning", "No camera selected for audio streaming")
                    self.talk_active = False
                    self.control_buttons['talk'].config(bg='#1a1a1a', fg='#ffffff')
                    return
                
                camera = cameras[self.active_camera_index]
                camera_ip = camera.get('ipAddress', '')
                camera_user = camera.get('username', 'admin')
                camera_pass = camera.get('password', 'admin123')
                
                if not camera_ip:
                    messagebox.showwarning("Warning", "Camera IP address not configured")
                    self.talk_active = False
                    self.control_buttons['talk'].config(bg='#1a1a1a', fg='#ffffff')
                    return
                
                # Initialize PyAudio
                audio = pyaudio.PyAudio()
                
                # Audio settings - optimized for IP camera compatibility
                CHUNK = 1024
                FORMAT = pyaudio.paInt16
                CHANNELS = 1  # Mono
                RATE = 8000  # 8kHz - standard for IP cameras (lower bandwidth)
                
                # List available input devices
                try:
                    input_device_index = None
                    for i in range(audio.get_device_count()):
                        device_info = audio.get_device_info_by_index(i)
                        if device_info['maxInputChannels'] > 0:
                            input_device_index = i
                            print(f"Using microphone: {device_info['name']}")
                            break
                except:
                    input_device_index = None
                
                # Start microphone stream
                stream = audio.open(
                    format=FORMAT,
                    channels=CHANNELS,
                    rate=RATE,
                    input=True,
                    frames_per_buffer=CHUNK,
                    input_device_index=input_device_index
                )
                
                # Store stream and camera info for later cleanup
                self.microphone_stream = stream
                self.audio_instance = audio
                self.talk_camera_ip = camera_ip
                self.talk_camera_user = camera_user
                self.talk_camera_pass = camera_pass
                
                # Stream audio to camera in background thread
                self.talk_thread = threading.Thread(target=self._stream_audio_to_camera, daemon=True)
                self.talk_thread.start()
                
                # Show success message after a brief delay to ensure stream started
                web_url = f"http://{camera_ip}"
                self.root.after(500, lambda: messagebox.showinfo(
                    "Talk Mode", 
                    f"Talk mode activated!\nYour microphone is now streaming to the camera speaker.\n\n"
                    f"Note: If audio doesn't reach the camera, the camera may require:\n"
                    f"1. Web interface access: {web_url}\n"
                    f"2. Two-way audio enabled in camera settings\n"
                    f"3. Proper API authentication\n\n"
                    f"Click the Talk button again to stop."
                ))
                print("Talk mode activated - microphone open, streaming to camera speaker")
            except ImportError:
                messagebox.showwarning("Warning", "PyAudio not installed. Talk feature requires PyAudio.\n\nInstall with:\npy -3.13 -m pip install pyaudio")
                self.talk_active = False
                self.control_buttons['talk'].config(bg='#1a1a1a', fg='#ffffff')
            except Exception as e:
                error_msg = str(e)
                messagebox.showerror("Error", f"Failed to start microphone:\n{error_msg}\n\nMake sure:\n1. Microphone is connected\n2. Microphone permissions are granted\n3. PyAudio is installed (py -3.13 -m pip install pyaudio)")
                self.talk_active = False
                self.control_buttons['talk'].config(bg='#1a1a1a', fg='#ffffff')
        else:
            # Stop microphone and close camera audio channel
            if hasattr(self, 'microphone_stream') and self.microphone_stream:
                try:
                    self.microphone_stream.stop_stream()
                    self.microphone_stream.close()
                    if hasattr(self, 'audio_instance'):
                        self.audio_instance.terminate()
                except:
                    pass
            
            # Close camera audio channel
            if hasattr(self, 'talk_camera_ip'):
                try:
                    self._close_camera_audio_channel()
                except:
                    pass
            
            self.control_buttons['talk'].config(bg='#1a1a1a', fg='#ffffff')
            print("Talk mode deactivated - microphone closed")
    
    def _get_camera_session(self, camera_ip, camera_user, camera_pass):
        """Get or create a session for camera API calls"""
        session_key = f"{camera_ip}_{camera_user}"
        
        if session_key not in self.camera_sessions:
            # Create new session - try both Digest and Basic auth
            session = requests.Session()
            # Try Digest Auth first (most common for IP cameras)
            session.auth = HTTPDigestAuth(camera_user, camera_pass)
            self.camera_sessions[session_key] = session
        
        return self.camera_sessions[session_key]
    
    def _get_onvif_client(self, camera_ip, camera_user, camera_pass, port=80):
        """Get or create ONVIF client for camera"""
        client_key = f"{camera_ip}_{camera_user}"
        
        if client_key not in self.onvif_clients:
            try:
                # Create ONVIF camera client
                # ONVIF typically uses port 80 for HTTP, 554 for RTSP
                onvif_client = ONVIFCamera(camera_ip, port, camera_user, camera_pass)
                self.onvif_clients[client_key] = onvif_client
                print(f"ONVIF client created for {camera_ip}")
            except Exception as e:
                print(f"Error creating ONVIF client: {e}")
                # Try alternative port (some cameras use 8080)
                try:
                    onvif_client = ONVIFCamera(camera_ip, 8080, camera_user, camera_pass)
                    self.onvif_clients[client_key] = onvif_client
                    print(f"ONVIF client created for {camera_ip} on port 8080")
                except Exception as e2:
                    print(f"Error creating ONVIF client on port 8080: {e2}")
                    return None
        
        return self.onvif_clients.get(client_key)
    
    def _open_camera_audio_channel(self):
        """Open audio channel on camera for two-way audio using ONVIF"""
        if not hasattr(self, 'talk_camera_ip'):
            return False
        
        try:
            # Method 1: Try ONVIF AudioOutput service
            try:
                onvif_client = self._get_onvif_client(self.talk_camera_ip, self.talk_camera_user, self.talk_camera_pass)
                if onvif_client:
                    # Get media service
                    media_service = onvif_client.create_media_service()
                    profiles = media_service.GetProfiles()
                    
                    if profiles:
                        profile_token = profiles[0].token
                        
                        # Get audio output configuration
                        audio_outputs = media_service.GetAudioOutputConfigurations()
                        if audio_outputs:
                            print("Camera audio channel opened via ONVIF AudioOutput")
                            return True
            except Exception as e:
                print(f"ONVIF AudioOutput failed: {e}")
            
            # Method 2: Fallback to HTTP API
            try:
                base_url = f"http://{self.talk_camera_ip}"
                session = self._get_camera_session(self.talk_camera_ip, self.talk_camera_user, self.talk_camera_pass)
                
                url = f"{base_url}/cgi-bin/audio.cgi?action=startAudioTalk"
                response = session.get(url, timeout=3)
                if response.status_code == 200 and 'rspCode":0' in response.text:
                    print("Camera audio channel opened (HTTP API)")
                    return True
            except Exception as e:
                print(f"HTTP API audio start failed: {e}")
            
            print("Warning: Could not open camera audio channel via ONVIF or HTTP - will attempt to send audio anyway")
            return False
        except Exception as e:
            print(f"Error opening camera audio channel: {e}")
            return False
    
    def _close_camera_audio_channel(self):
        """Close audio channel on camera"""
        if not hasattr(self, 'talk_camera_ip'):
            return
        
        try:
            base_url = f"http://{self.talk_camera_ip}"
            session = self._get_camera_session(self.talk_camera_ip, self.talk_camera_user, self.talk_camera_pass)
            
            # Try to close audio channel
            try:
                url = f"{base_url}/cgi-bin/audio.cgi?action=stopAudioTalk"
                response = session.get(url, timeout=2)
                if response.status_code == 200:
                    print("Camera audio channel closed (Dahua CGI)")
            except:
                pass
            
            try:
                url = f"{base_url}/cgi-bin/audio.cgi?action=stop"
                session.get(url, timeout=2)
            except:
                pass
            
            try:
                url = f"{base_url}/ISAPI/System/TwoWayAudio/channels/1/close"
                session.put(url, timeout=2)
            except:
                pass
        except:
            pass
    
    def _send_audio_to_camera(self, audio_data):
        """Send audio data to camera via ONVIF or HTTP API"""
        if not hasattr(self, 'talk_camera_ip') or not self.talk_camera_ip:
            return False
        
        try:
            # Method 1: Try ONVIF AudioOutput (if supported)
            # Note: ONVIF audio streaming is complex and may require RTSP
            # For now, we'll use HTTP API as primary method
            
            # Method 2: HTTP API audio upload (most reliable)
            base_url = f"http://{self.talk_camera_ip}"
            session = self._get_camera_session(self.talk_camera_ip, self.talk_camera_user, self.talk_camera_pass)
            
            # Try Dahua CGI audio data upload
            try:
                url = f"{base_url}/cgi-bin/audio.cgi?action=putAudio"
                headers = {'Content-Type': 'application/octet-stream', 'Content-Length': str(len(audio_data))}
                response = session.post(url, data=audio_data, headers=headers, timeout=2)
                if response.status_code == 200:
                    return True
            except Exception as e:
                pass
            
            # Try Dahua audio stream upload
            try:
                url = f"{base_url}/cgi-bin/audio.cgi?action=putStream&format=PCM&rate=8000"
                headers = {'Content-Type': 'application/octet-stream'}
                response = session.post(url, data=audio_data, headers=headers, timeout=2)
                if response.status_code == 200:
                    return True
            except Exception as e:
                pass
            
            # Try Hikvision ISAPI
            try:
                url = f"{base_url}/ISAPI/System/TwoWayAudio/channels/1/audioData"
                headers = {'Content-Type': 'application/octet-stream', 'Content-Length': str(len(audio_data))}
                response = session.put(url, data=audio_data, headers=headers, timeout=2)
                if response.status_code in [200, 201, 204]:
                    return True
            except Exception as e:
                pass
            
            return False
        except Exception as e:
            return False
    
    def _stream_audio_to_camera(self):
        """Stream audio from microphone to camera speaker"""
        # Open camera audio channel first
        channel_opened = self._open_camera_audio_channel()
        if not channel_opened:
            print("Note: Camera audio channel may not support direct API control. Audio will be sent anyway.")
        
        audio_buffer = []
        buffer_size = 4  # Send every 4 chunks to reduce API calls
        chunk_count = 0
        send_success_count = 0
        send_fail_count = 0
        
        while self.talk_active and hasattr(self, 'microphone_stream'):
            try:
                # Read audio data from microphone
                data = self.microphone_stream.read(1024, exception_on_overflow=False)
                audio_buffer.append(data)
                chunk_count += 1
                
                # Send buffered audio to camera periodically
                if chunk_count >= buffer_size:
                    combined_audio = b''.join(audio_buffer)
                    if self._send_audio_to_camera(combined_audio):
                        send_success_count += 1
                    else:
                        send_fail_count += 1
                        if send_fail_count > 10:
                            print("Warning: Multiple audio send failures. Check camera connection.")
                            send_fail_count = 0
                    audio_buffer = []
                    chunk_count = 0
                
                # Small delay to prevent overwhelming the camera
                time.sleep(0.01)
            except Exception as e:
                print(f"Error in audio stream: {e}")
                break
        
        # Send any remaining buffered audio
        if audio_buffer:
            try:
                combined_audio = b''.join(audio_buffer)
                self._send_audio_to_camera(combined_audio)
            except:
                pass
        
        print(f"Talk session ended. Audio packets sent: {send_success_count}")
    
    def audio_alarm_action(self):
        """Handle audio alarm button click - toggle alarm (sound on camera speaker)"""
        self.audio_alarm_active = not self.audio_alarm_active
        if self.audio_alarm_active:
            self.control_buttons['audio_alarm'].config(bg=COLORS['warning'], fg='#ffffff')
            # Start alarm sound
            if not hasattr(self, 'alarm_thread') or self.alarm_thread is None or not self.alarm_thread.is_alive():
                self.alarm_thread = threading.Thread(target=self._play_alarm, daemon=True)
                self.alarm_thread.start()
                cameras = self.load_cameras()
                camera_ip = cameras[self.active_camera_index].get('ipAddress', '') if cameras and self.active_camera_index < len(cameras) else ''
                web_url = f"http://{camera_ip}" if camera_ip else "camera web interface"
                messagebox.showinfo(
                    "Alarm", 
                    f"Audio alarm activated!\nAlarm sound is playing on camera speaker.\n\n"
                    f"Note: If alarm doesn't play, the camera may require:\n"
                    f"1. Web interface access: {web_url}\n"
                    f"2. Audio output enabled in camera settings\n"
                    f"3. Proper API authentication\n\n"
                    f"Click the Alarm button again to stop."
                )
                print("Audio alarm activated - playing on camera speaker")
            else:
                print("Alarm thread already running")
        else:
            self.control_buttons['audio_alarm'].config(bg='#1a1a1a', fg='#ffffff')
            # Alarm will stop automatically when audio_alarm_active becomes False
            print("Audio alarm deactivated")
    
    def _play_alarm(self):
        """Play alarm sound on camera speaker using ONVIF or HTTP API"""
        import numpy as np
        
        # Get camera info for alarm
        cameras = self.load_cameras()
        if not cameras or self.active_camera_index >= len(cameras):
            print("Error: No camera available for alarm")
            self.audio_alarm_active = False
            return
        
        camera = cameras[self.active_camera_index]
        camera_ip = camera.get('ipAddress', '')
        camera_user = camera.get('username', 'admin')
        camera_pass = camera.get('password', 'admin123')
        
        if not camera_ip:
            print("Error: Camera IP not configured for alarm")
            self.audio_alarm_active = False
            return
        
        # Try ONVIF RelayOutput for alarm (some cameras use relay for alarm)
        try:
            onvif_client = self._get_onvif_client(camera_ip, camera_user, camera_pass)
            if onvif_client:
                io_service = onvif_client.create_deviceio_service()
                outputs = io_service.GetRelayOutputs()
                
                if outputs and len(outputs) > 0:
                    # Use relay output for alarm
                    relay_token = outputs[0].token if len(outputs) == 1 else outputs[1].token if len(outputs) > 1 else outputs[0].token
                    
                    # Activate relay for alarm
                    io_service.SetRelayOutputState({
                        'RelayOutputToken': relay_token,
                        'LogicalState': 'active'
                    })
                    print("Alarm activated via ONVIF RelayOutput")
                    
                    # Keep alarm active
                    while self.audio_alarm_active:
                        time.sleep(0.5)
                    
                    # Deactivate relay
                    io_service.SetRelayOutputState({
                        'RelayOutputToken': relay_token,
                        'LogicalState': 'inactive'
                    })
                    print("Alarm deactivated via ONVIF RelayOutput")
                    return
        except Exception as e:
            print(f"ONVIF RelayOutput alarm failed: {e}")
        
        # Fallback: Use audio streaming for alarm
        # Open camera audio channel
        self.talk_camera_ip = camera_ip
        self.talk_camera_user = camera_user
        self.talk_camera_pass = camera_pass
        channel_opened = self._open_camera_audio_channel()
        
        # Generate and send alarm tone
        sample_rate = 8000  # 8kHz for IP camera compatibility
        frequency = 1000  # 1kHz alarm tone
        duration = 0.3  # 300ms per tone
        
        cycle_count = 0
        while self.audio_alarm_active:
            try:
                # Generate alarm tone (1000 Hz sine wave)
                t = np.linspace(0, duration, int(sample_rate * duration))
                audio_data = np.sin(2 * np.pi * frequency * t)
                
                # Add some variation for more noticeable alarm
                if cycle_count % 2 == 0:
                    audio_data = audio_data * 0.8  # Slightly quieter
                
                # Convert to 16-bit PCM format
                audio_data = (audio_data * 32767).astype(np.int16)
                audio_bytes = audio_data.tobytes()
                
                # Send to camera
                if self._send_audio_to_camera(audio_bytes):
                    print(f"Alarm tone sent to camera (cycle {cycle_count + 1})")
                else:
                    print(f"Warning: Failed to send alarm tone (cycle {cycle_count + 1})")
                
                cycle_count += 1
                time.sleep(0.5)  # Wait between tones
            except Exception as e:
                print(f"Error generating/sending alarm: {e}")
                break
        
        # Close camera audio channel
        try:
            self._close_camera_audio_channel()
        except:
            pass
        
        print("Alarm stopped")
    
    def light_action(self):
        """Handle light button click - toggle camera light on/off"""
        self.light_active = not self.light_active
        
        # Get camera info
        cameras = self.load_cameras()
        if not cameras or self.active_camera_index >= len(cameras):
            messagebox.showwarning("Warning", "No camera selected for light control")
            self.light_active = not self.light_active  # Toggle back
            return
        
        camera = cameras[self.active_camera_index]
        camera_ip = camera.get('ipAddress', '')
        camera_user = camera.get('username', 'admin')
        camera_pass = camera.get('password', 'admin123')
        
        if not camera_ip:
            messagebox.showwarning("Warning", "Camera IP address not configured")
            self.light_active = not self.light_active  # Toggle back
            return
        
        # Control camera light
        success = self._control_camera_light(camera_ip, camera_user, camera_pass, self.light_active)
        
        if self.light_active:
            if success:
                self.control_buttons['light'].config(bg=COLORS['warning'], fg='#ffffff')
                messagebox.showinfo("Success", "Camera light turned ON")
                print("Camera light ON")
            else:
                # Camera may require web interface access
                self.light_active = False
                web_url = f"http://{camera_ip}"
                messagebox.showwarning(
                    "Camera Control", 
                    f"Remote light control may not be supported by this camera.\n\n"
                    f"Please try:\n"
                    f"1. Access camera web interface: {web_url}\n"
                    f"2. Login with username: {camera_user}\n"
                    f"3. Control light from the web interface\n\n"
                    f"Or check if your camera model supports API-based light control."
                )
                self.control_buttons['light'].config(bg='#1a1a1a', fg='#ffffff')
        else:
            if success:
                self.control_buttons['light'].config(bg='#1a1a1a', fg='#ffffff')
                print("Camera light OFF")
            else:
                # Still turn off UI even if API call fails
                self.control_buttons['light'].config(bg='#1a1a1a', fg='#ffffff')
                print("Camera light OFF (API call may have failed)")
    
    def _control_camera_light(self, camera_ip, camera_user, camera_pass, turn_on):
        """Control camera light via ONVIF"""
        try:
            light_state_text = "on" if turn_on else "off"
            
            # Method 1: Try ONVIF RelayOutput service (most common for lights)
            try:
                onvif_client = self._get_onvif_client(camera_ip, camera_user, camera_pass)
                if onvif_client:
                    # Get I/O service for relay outputs
                    io_service = onvif_client.create_deviceio_service()
                    
                    # Get available relay outputs
                    outputs = io_service.GetRelayOutputs()
                    
                    if outputs and len(outputs) > 0:
                        # Use first available relay output
                        relay_token = outputs[0].token
                        
                        # Set relay state
                        io_service.SetRelayOutputState({
                            'RelayOutputToken': relay_token,
                            'LogicalState': 'active' if turn_on else 'inactive'
                        })
                        
                        print(f"Camera light controlled via ONVIF RelayOutput: {light_state_text}")
                        return True
                    else:
                        print("No relay outputs available on camera")
            except Exception as e:
                print(f"ONVIF RelayOutput control failed: {e}")
            
            # Method 2: Try ONVIF PTZ service (some cameras use PTZ for light control)
            try:
                onvif_client = self._get_onvif_client(camera_ip, camera_user, camera_pass)
                if onvif_client:
                    ptz_service = onvif_client.create_ptz_service()
                    media_service = onvif_client.create_media_service()
                    
                    # Get profiles
                    profiles = media_service.GetProfiles()
                    if profiles:
                        profile_token = profiles[0].token
                        
                        # Try to control light via PTZ preset or action
                        if turn_on:
                            # Some cameras use PTZ actions for light
                            ptz_service.ContinuousMove({
                                'ProfileToken': profile_token,
                                'Velocity': {
                                    'PanTilt': {'x': 0, 'y': 0},
                                    'Zoom': {'x': 0}
                                }
                            })
                        else:
                            ptz_service.Stop({'ProfileToken': profile_token})
                        
                        print(f"Camera light controlled via ONVIF PTZ: {light_state_text}")
                        return True
            except Exception as e:
                print(f"ONVIF PTZ control failed: {e}")
            
            # Method 3: Fallback to HTTP API (if ONVIF doesn't work)
            try:
                base_url = f"http://{camera_ip}"
                session = self._get_camera_session(camera_ip, camera_user, camera_pass)
                url = f"{base_url}/cgi-bin/light.cgi?action={light_state_text}"
                response = session.get(url, timeout=3)
                if response.status_code == 200 and 'rspCode":0' in response.text:
                    print(f"Camera light controlled via HTTP API: {light_state_text}")
                    return True
            except Exception as e:
                print(f"HTTP API light control failed: {e}")
            
            print(f"Warning: Could not control camera light via ONVIF or HTTP API. State requested: {light_state_text}")
            return False
        except Exception as e:
            print(f"Error controlling camera light: {e}")
            return False
    
    def record_action(self):
        """Handle record button click - start/stop recording"""
        self.recording_active = not self.recording_active
        if self.recording_active:
            self.control_buttons['record'].config(bg=COLORS['error'], fg='#ffffff', text='⏹')
            # Start recording
            try:
                timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                self.recording_filename = f"recording_{timestamp}.mp4"
                # In a real implementation, start recording from camera stream
                print(f"Recording started: {self.recording_filename}")
            except Exception as e:
                messagebox.showerror("Error", f"Failed to start recording: {e}")
                self.recording_active = False
                self.control_buttons['record'].config(bg='#1a1a1a', fg='#ffffff', text='⏺')
        else:
            self.control_buttons['record'].config(bg='#1a1a1a', fg='#ffffff', text='⏺')
            # Stop recording
            try:
                if hasattr(self, 'recording_filename'):
                    messagebox.showinfo("Recording", f"Recording saved as {self.recording_filename}")
                print("Recording stopped")
            except Exception as e:
                messagebox.showerror("Error", f"Failed to stop recording: {e}")
    
    def snapshot_action(self):
        """Handle snapshot button click"""
        if self.current_frame is not None:
            try:
                timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                filename = f"snapshot_{timestamp}.jpg"
                cv2.imwrite(filename, self.current_frame)
                messagebox.showinfo("Snapshot", f"Snapshot saved as {filename}")
            except Exception as e:
                messagebox.showerror("Error", f"Failed to save snapshot: {e}")
        else:
            messagebox.showwarning("Warning", "No frame available to capture")
    
    def create_logs_page(self):
        """Create Logs page"""
        header = tk.Frame(self.main_content, bg=COLORS['bg'], pady=30, padx=30)
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text="Logs",
            font=('Segoe UI', 28, 'bold'),
            bg=COLORS['bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT)
        
        content = tk.Frame(self.main_content, bg=COLORS['bg'], padx=30, pady=20)
        content.pack(fill=tk.BOTH, expand=True)
        
        # Card for content
        card = tk.Frame(content, bg=COLORS['card_bg'], padx=30, pady=30)
        card.pack(fill=tk.BOTH, expand=True)
        card.configure(borderwidth=0, highlightbackground=COLORS['border'], highlightthickness=2)
        
        tk.Label(
            card,
            text="System logs will be displayed here",
            font=('Segoe UI', 12),
            bg=COLORS['card_bg'],
            fg=COLORS['text_secondary']
        ).pack(pady=50)
    
    def update_detected_objects(self):
        """Update detected objects list with better filtering"""
        if self.current_page == 'live_view' and hasattr(self, 'objects_scrollable_frame'):
            try:
                # Load detections
                if os.path.exists('detections.json'):
                    try:
                        with open('detections.json', 'r') as f:
                            data = json.load(f)
                        
                        # Handle both dict and list formats
                        if isinstance(data, dict):
                            detections = data.get('detections', [])
                            # Also check if there are detections at root level
                            if not detections and 'detections' not in data:
                                # Try to find any list in the data
                                for key, value in data.items():
                                    if isinstance(value, list) and len(value) > 0:
                                        detections = value
                                        break
                        elif isinstance(data, list):
                            detections = data
                        else:
                            detections = []
                    except (json.JSONDecodeError, Exception) as e:
                        # If file is corrupted or locked, use empty list
                        detections = []
                else:
                    detections = []
                    
                    # Filter valid detections (only person, vehicle, animal with sufficient confidence)
                    valid_detections = []
                    for det in detections:
                        category = det.get('category', '').lower()
                        confidence = det.get('confidence', 0)
                        
                        # Lower confidence threshold to show more detections (0.25 instead of 0.3)
                        # Only include if it's a valid category and has reasonable confidence
                        if category in ['person', 'vehicle', 'animal', 'weapon'] and confidence >= 0.25:
                            # Additional validation: check if image exists and is valid
                            image_path = det.get('image', '')
                            if image_path and os.path.exists(image_path):
                                try:
                                    # Verify it's a real object, not just background
                                    img = cv2.imread(image_path)
                                    if img is not None and img.size > 0:
                                        # Lower minimum size threshold to show more objects (20x20 instead of 30x30)
                                        h, w = img.shape[:2]
                                        if h > 20 and w > 20:  # Minimum size threshold
                                            valid_detections.append(det)
                                except:
                                    # If image doesn't exist, still include the detection (might be processing)
                                    valid_detections.append(det)
                            else:
                                # Include detection even if image doesn't exist yet (might be processing)
                                valid_detections.append(det)
                    
                    # Sort by confidence (highest first) to show most confident detections
                    # Then by timestamp (newest first) for tie-breaking
                    valid_detections.sort(key=lambda x: (x.get('confidence', 0), x.get('timestamp', '')), reverse=True)
                    
                    # Keep only top 5 (by confidence, then timestamp)
                    valid_detections = valid_detections[:5]
                    
                    # Clear existing objects
                    for widget in self.objects_scrollable_frame.winfo_children():
                        widget.destroy()
                    
                    # Display objects (up to 5)
                    if valid_detections:
                        # Ensure we show exactly up to 5 objects
                        display_count = min(len(valid_detections), 5)
                        for i, det in enumerate(valid_detections[:display_count]):
                            self.create_object_card(det)
                    else:
                        # Show empty state
                        empty_label = tk.Label(
                            self.objects_scrollable_frame,
                            text="No detections yet",
                            font=('Segoe UI', 12),
                            bg=COLORS['card_bg'],
                            fg=COLORS['text_muted']
                        )
                        empty_label.pack(pady=50)
            except Exception as e:
                pass
        
        # Update every 250ms for better real-time updates (4 times per second)
        self.root.after(250, self.update_detected_objects)
    
    def create_object_card(self, detection):
        """Create a card for a detected object with modern dark mode design"""
        category = detection.get('category', 'unknown').lower()
        image_path = detection.get('image', '')
        
        # Card frame with dark mode styling
        card = tk.Frame(
            self.objects_scrollable_frame,
            bg=COLORS['bg_secondary'],
            relief=tk.FLAT,
            borderwidth=0,
            highlightbackground=COLORS['border'],
            highlightthickness=1
        )
        card.pack(fill=tk.X, pady=10, padx=5)
        
        # Image frame with dark background
        img_frame = tk.Frame(card, bg=COLORS['bg_tertiary'], width=140, height=140)
        img_frame.pack(side=tk.LEFT, padx=12, pady=12)
        img_frame.pack_propagate(False)
        
        img_label = tk.Label(img_frame, bg=COLORS['bg_tertiary'], fg=COLORS['text_muted'], text="No Image")
        img_label.pack(fill=tk.BOTH, expand=True)
        
        # Load and display image
        if image_path and os.path.exists(image_path):
            try:
                img = cv2.imread(image_path)
                if img is not None:
                    # For person, try to extract face only
                    if category == 'person':
                        # Try to detect and crop face
                        face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
                        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
                        faces = face_cascade.detectMultiScale(gray, 1.1, 4)
                        
                        if len(faces) > 0:
                            # Get largest face
                            largest_face = max(faces, key=lambda rect: rect[2] * rect[3])
                            x, y, w, h = largest_face
                            
                            # Add padding
                            padding = 20
                            x = max(0, x - padding)
                            y = max(0, y - padding)
                            w = min(img.shape[1] - x, w + padding * 2)
                            h = min(img.shape[0] - y, h + padding * 2)
                            
                            # Crop face
                            face_img = img[y:y+h, x:x+w]
                            img = face_img
                    
                    # Resize to fit
                    img = cv2.resize(img, (140, 140), interpolation=cv2.INTER_AREA)
                    img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
                    img_pil = Image.fromarray(img_rgb)
                    img_tk = ImageTk.PhotoImage(image=img_pil)
                    img_label.config(image=img_tk, text='')
                    img_label.image = img_tk
            except Exception as e:
                pass
        
        # Details frame with dark mode
        details_frame = tk.Frame(card, bg=COLORS['bg_secondary'])
        details_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(0, 12), pady=12)
        
        # Category badge with dark mode colors
        category_colors = {
            'person': '#1e3a5f',
            'vehicle': '#4a3a1e',
            'animal': '#1e3a2e'
        }
        category_text_colors = {
            'person': '#60a5fa',
            'vehicle': '#fbbf24',
            'animal': '#34d399'
        }
        category_text = category.capitalize()
        
        badge = tk.Label(
            details_frame,
            text=category_text,
            font=('Segoe UI', 9, 'bold'),
            bg=category_colors.get(category, COLORS['bg_tertiary']),
            fg=category_text_colors.get(category, COLORS['text']),
            padx=10,
            pady=4
        )
        badge.pack(anchor='w', pady=(0, 8))
        
        # Person details
        if category == 'person':
            gender = detection.get('gender', 'Unknown')
            expression = detection.get('expression', 'calm')
            accessories = detection.get('accessories', 'None')
            clothes_color = detection.get('clothes_color', 'none')
            if clothes_color == 'none':
                clothes_color = 'None' if gender.lower() != 'topless' else 'Topless'
            
            details = [
                f"Gender: {gender}",
                f"Expression: {expression.capitalize()}",
                f"Accessories: {accessories}",
                f"Clothes Color: {clothes_color.capitalize()}"
            ]
        elif category == 'vehicle':
            # Vehicle details (plate number if available)
            plate = detection.get('plate_number', 'N/A')
            details = [f"Plate: {plate}"]
        else:  # animal
            animal_type = detection.get('animal_type', 'Unknown')
            details = [f"Type: {animal_type}"]
        
        # Confidence
        confidence = detection.get('confidence', 0) * 100
        details.append(f"Confidence: {confidence:.1f}%")
        
        # Timestamp
        timestamp = detection.get('timestamp', '')
        if timestamp:
            try:
                dt = datetime.fromisoformat(timestamp.replace('Z', '+00:00'))
                time_str = dt.strftime('%I:%M:%S %p')
                details.append(f"Detected: {time_str}")
            except:
                details.append(f"Detected: {timestamp}")
        
        # Display details with dark mode
        for detail in details:
            tk.Label(
                details_frame,
                text=detail,
                font=('Segoe UI', 9),
                bg=COLORS['bg_secondary'],
                fg=COLORS['text_secondary'],
                anchor='w'
            ).pack(anchor='w', pady=2)
    
    def auto_start_detection(self):
        """Auto-start detection on launch"""
        # Get first camera's RTSP URL
        cameras = self.load_cameras()
        rtsp_url = ""
        
        if cameras and len(cameras) > 0:
            rtsp_url = cameras[0].get('rtspUrl', '')
            self.active_camera_index = 0
        
        if not rtsp_url:
            # Default RTSP URL from detect.py
            rtsp_url = "rtsp://admin:admin123@10.245.53.187:554/Preview_01_sub"
        
        # Start detection in background
        threading.Thread(target=self.start_detection_background, args=(rtsp_url,), daemon=True).start()
    
    def start_detection_background(self, rtsp_url):
        """Start detection in background thread"""
        if not os.path.exists('detect.py'):
            return
        
        try:
            self.create_modified_detect_script(rtsp_url)
            
            script_path = 'detect_temp.py' if os.path.exists('detect_temp.py') else 'detect.py'
            
            self.detection_process = subprocess.Popen(
                [sys.executable, script_path],
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                universal_newlines=True,
                bufsize=1,
                creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == 'win32' else 0
            )
            
            self.is_running = True
            
        except Exception as e:
            print(f"Error starting detection: {e}")
    
    def create_modified_detect_script(self, rtsp_url):
        """Create a temporary detect script with modified settings"""
        try:
            with open('detect.py', 'r', encoding='utf-8') as f:
                content = f.read()
        except Exception as e:
            return
        
        import re
        content = re.sub(
            r'RTSP_URL\s*=\s*"[^"]*"',
            f'RTSP_URL = "{rtsp_url}"',
            content
        )
        
        if not self.enable_detection_var.get():
            content = re.sub(
                r'ENABLE_DETECTION\s*=\s*True',
                'ENABLE_DETECTION = False',
                content
            )
        
        if not self.enable_recording_var.get():
            content = re.sub(
                r'ENABLE_RECORDING\s*=\s*True',
                'ENABLE_RECORDING = False',
                content
            )
        
        detection_interval = self.detection_interval_var.get()
        content = re.sub(
            r'DETECTION_INTERVAL\s*=\s*\d+',
            f'DETECTION_INTERVAL = {detection_interval}',
            content
        )
        
        confidence = self.confidence_var.get()
        content = re.sub(
            r'CONFIDENCE_THRESHOLD\s*=\s*[\d.]+',
            f'CONFIDENCE_THRESHOLD = {confidence}',
            content
        )
        
        try:
            with open('detect_temp.py', 'w', encoding='utf-8') as f:
                f.write(content)
        except:
            pass
    
    def create_recording_page(self):
        """Create Recording page with dark mode"""
        header = tk.Frame(self.main_content, bg=COLORS['bg'], pady=30, padx=30)
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text="Recording",
            font=('Segoe UI', 28, 'bold'),
            bg=COLORS['bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT)
        
        content = tk.Frame(self.main_content, bg=COLORS['bg'], padx=30, pady=20)
        content.pack(fill=tk.BOTH, expand=True)
        
        # Card for content
        card = tk.Frame(content, bg=COLORS['card_bg'], padx=30, pady=30)
        card.pack(fill=tk.BOTH, expand=True)
        card.configure(borderwidth=0, highlightbackground=COLORS['border'], highlightthickness=2)
        
        tk.Label(
            card,
            text="Recording management and controls will be displayed here",
            font=('Segoe UI', 12),
            bg=COLORS['card_bg'],
            fg=COLORS['text_secondary']
        ).pack(pady=50)
    
    def create_playback_page(self):
        """Create Playback page with dark mode"""
        header = tk.Frame(self.main_content, bg=COLORS['bg'], pady=30, padx=30)
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text="Playback",
            font=('Segoe UI', 28, 'bold'),
            bg=COLORS['bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT)
        
        content = tk.Frame(self.main_content, bg=COLORS['bg'], padx=30, pady=20)
        content.pack(fill=tk.BOTH, expand=True)
        
        # Card for content
        card = tk.Frame(content, bg=COLORS['card_bg'], padx=30, pady=30)
        card.pack(fill=tk.BOTH, expand=True)
        card.configure(borderwidth=0, highlightbackground=COLORS['border'], highlightthickness=2)
        
        tk.Label(
            card,
            text="Video playback interface will be displayed here",
            font=('Segoe UI', 12),
            bg=COLORS['card_bg'],
            fg=COLORS['text_secondary']
        ).pack(pady=50)
    
    def create_camera_management_page(self):
        """Create Camera Management page with dark mode"""
        header = tk.Frame(self.main_content, bg=COLORS['bg'], pady=30, padx=30)
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text="Camera Management",
            font=('Segoe UI', 28, 'bold'),
            bg=COLORS['bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT)
        
        content = tk.Frame(self.main_content, bg=COLORS['bg'], padx=30, pady=20)
        content.pack(fill=tk.BOTH, expand=True)
        
        # Camera selection panel with dark mode
        panel = tk.Frame(content, bg=COLORS['card_bg'], padx=40, pady=40)
        panel.pack(fill=tk.BOTH, expand=True)
        panel.configure(borderwidth=0, highlightbackground=COLORS['border'], highlightthickness=2)
        
        # Camera selection
        tk.Label(
            panel,
            text="Camera",
            font=('Segoe UI', 12, 'bold'),
            bg=COLORS['card_bg'],
            fg=COLORS['text']
        ).pack(anchor='w', pady=(0, 10))
        
        self.camera_var = tk.StringVar()
        self.camera_combo = ttk.Combobox(
            panel,
            textvariable=self.camera_var,
            state="readonly",
            width=50,
            font=('Segoe UI', 11)
        )
        self.camera_combo.pack(fill=tk.X, pady=(0, 25), ipady=8)
        self.camera_combo.bind('<<ComboboxSelected>>', self.on_camera_selected)
        
        # RTSP URL
        self.rtsp_var = tk.StringVar()
        tk.Label(
            panel,
            text="RTSP URL",
            font=('Segoe UI', 12, 'bold'),
            bg=COLORS['card_bg'],
            fg=COLORS['text']
        ).pack(anchor='w', pady=(0, 10))
        
        rtsp_entry = tk.Entry(
            panel,
            textvariable=self.rtsp_var,
            font=('Segoe UI', 11),
            relief=tk.FLAT,
            borderwidth=0,
            highlightthickness=2,
            highlightbackground=COLORS['border'],
            highlightcolor=COLORS['primary'],
            bg=COLORS['bg_secondary'],
            fg=COLORS['text'],
            insertbackground=COLORS['text']
        )
        rtsp_entry.pack(fill=tk.X, pady=(0, 20), ipady=10, ipadx=10)
        
        # Update camera list
        self.update_camera_list()
    
    def update_camera_list(self):
        """Update the camera dropdown list"""
        if not hasattr(self, 'camera_combo'):
            return
            
        cameras = self.load_cameras()
        if cameras:
            camera_names = [f"{cam.get('name', 'Unknown')} - {cam.get('cameraId', 'N/A')}" 
                           for cam in cameras]
            self.camera_combo['values'] = camera_names
            if camera_names:
                self.camera_combo.current(0)
                self.on_camera_selected()
        else:
            self.camera_combo['values'] = ['No cameras found']
    
    def on_camera_selected(self, event=None):
        """Handle camera selection"""
        if not hasattr(self, 'rtsp_var') or not hasattr(self, 'camera_var'):
            return
            
        selection = self.camera_var.get()
        cameras = self.load_cameras()
        
        if cameras and selection:
            for i, cam in enumerate(cameras):
                cam_name = f"{cam.get('name', 'Unknown')} - {cam.get('cameraId', 'N/A')}"
                if cam_name == selection:
                    rtsp_url = cam.get('rtspUrl', '')
                    self.rtsp_var.set(rtsp_url)
                    self.active_camera_index = i
                    break
    
    def show_settings_menu(self):
        """Show settings dropdown menu"""
        # Create menu window
        menu_window = tk.Toplevel(self.root)
        menu_window.title("Settings")
        menu_window.overrideredirect(True)  # Remove window decorations
        menu_window.configure(bg=COLORS['card_bg'])
        menu_window.attributes('-topmost', True)
        
        # Position menu near settings icon (top right area)
        try:
            # Get top bar position
            self.top_bar.update_idletasks()
            top_bar_x = self.top_bar.winfo_rootx()
            top_bar_y = self.top_bar.winfo_rooty()
            top_bar_width = self.top_bar.winfo_width()
            
            # Position menu at top right
            menu_width = 200
            menu_height = 150
            x = top_bar_x + top_bar_width - menu_width - 20
            y = top_bar_y + 60
            
            menu_window.geometry(f"{menu_width}x{menu_height}+{x}+{y}")
        except:
            # Fallback position
            menu_window.geometry("200x150+100+100")
        
        menu_window.configure(borderwidth=2, highlightbackground=COLORS['border'], highlightthickness=1)
        
        # Menu items
        menu_items = [
            ('User Management', self.show_user_management),
            ('Maintenance', self.show_maintenance),
            ('Logout', self.logout),
        ]
        
        for text, command in menu_items:
            btn = tk.Button(
                menu_window,
                text=text,
                font=('Segoe UI', 11),
                bg=COLORS['card_bg'],
                fg=COLORS['text'],
                activebackground=COLORS['sidebar_hover'],
                activeforeground=COLORS['text'],
                relief=tk.FLAT,
                anchor='w',
                padx=20,
                pady=12,
                cursor='hand2',
                command=lambda cmd=command, win=menu_window: (cmd(), win.destroy()),
                borderwidth=0
            )
            btn.pack(fill=tk.X)
        
        # Close menu when clicking outside
        def close_menu(event):
            if event.widget == menu_window:
                menu_window.destroy()
        
        menu_window.bind('<FocusOut>', lambda e: menu_window.destroy())
        menu_window.focus_set()
    
    def show_user_management(self):
        """Show user management page"""
        self.show_page('user_management')
    
    def show_maintenance(self):
        """Show maintenance page"""
        self.show_page('maintenance')
    
    def create_user_management_page(self):
        """Create User Management page"""
        header = tk.Frame(self.main_content, bg=COLORS['bg'], pady=30, padx=30)
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text="User Management",
            font=('Segoe UI', 28, 'bold'),
            bg=COLORS['bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT)
        
        content = tk.Frame(self.main_content, bg=COLORS['bg'], padx=30, pady=20)
        content.pack(fill=tk.BOTH, expand=True)
        
        # Card for content
        card = tk.Frame(content, bg=COLORS['card_bg'], padx=30, pady=30)
        card.pack(fill=tk.BOTH, expand=True)
        card.configure(borderwidth=0, highlightbackground=COLORS['border'], highlightthickness=2)
        
        tk.Label(
            card,
            text="User management interface will be displayed here",
            font=('Segoe UI', 12),
            bg=COLORS['card_bg'],
            fg=COLORS['text_secondary']
        ).pack(pady=50)
    
    def create_maintenance_page(self):
        """Create Maintenance page"""
        header = tk.Frame(self.main_content, bg=COLORS['bg'], pady=30, padx=30)
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text="Maintenance",
            font=('Segoe UI', 28, 'bold'),
            bg=COLORS['bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT)
        
        content = tk.Frame(self.main_content, bg=COLORS['bg'], padx=30, pady=20)
        content.pack(fill=tk.BOTH, expand=True)
        
        # Card for content
        card = tk.Frame(content, bg=COLORS['card_bg'], padx=30, pady=30)
        card.pack(fill=tk.BOTH, expand=True)
        card.configure(borderwidth=0, highlightbackground=COLORS['border'], highlightthickness=2)
        
        tk.Label(
            card,
            text="System maintenance and diagnostics will be displayed here",
            font=('Segoe UI', 12),
            bg=COLORS['card_bg'],
            fg=COLORS['text_secondary']
        ).pack(pady=50)
    
    def create_settings_page(self):
        """Create Settings page with dark mode"""
        header = tk.Frame(self.main_content, bg=COLORS['bg'], pady=30, padx=30)
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text="Settings",
            font=('Segoe UI', 28, 'bold'),
            bg=COLORS['bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT)
        
        content = tk.Frame(self.main_content, bg=COLORS['bg'], padx=30, pady=20)
        content.pack(fill=tk.BOTH, expand=True)
        
        # Settings panel with dark mode
        settings_panel = tk.Frame(content, bg=COLORS['card_bg'], padx=40, pady=40)
        settings_panel.pack(fill=tk.BOTH, expand=True)
        settings_panel.configure(borderwidth=0, highlightbackground=COLORS['border'], highlightthickness=2)
        
        # Detection settings
        tk.Label(
            settings_panel,
            text="Detection Settings",
            font=('Segoe UI', 18, 'bold'),
            bg=COLORS['card_bg'],
            fg=COLORS['text']
        ).pack(anchor='w', pady=(0, 30))
        
        # Detection interval
        interval_frame = tk.Frame(settings_panel, bg=COLORS['card_bg'])
        interval_frame.pack(fill=tk.X, pady=15)
        
        tk.Label(
            interval_frame,
            text="Detection Interval:",
            font=('Segoe UI', 12),
            bg=COLORS['card_bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT, padx=(0, 15))
        
        interval_spin = ttk.Spinbox(
            interval_frame,
            from_=1,
            to=100,
            textvariable=self.detection_interval_var,
            width=12,
            font=('Segoe UI', 11)
        )
        interval_spin.pack(side=tk.LEFT)
        
        # Confidence threshold
        confidence_frame = tk.Frame(settings_panel, bg=COLORS['card_bg'])
        confidence_frame.pack(fill=tk.X, pady=15)
        
        tk.Label(
            confidence_frame,
            text="Confidence Threshold:",
            font=('Segoe UI', 12),
            bg=COLORS['card_bg'],
            fg=COLORS['text']
        ).pack(side=tk.LEFT, padx=(0, 15))
        
        confidence_spin = ttk.Spinbox(
            confidence_frame,
            from_=0.1,
            to=1.0,
            increment=0.1,
            textvariable=self.confidence_var,
            width=12,
            font=('Segoe UI', 11)
        )
        confidence_spin.pack(side=tk.LEFT)
        
        # Enable detection
        ttk.Checkbutton(
            settings_panel,
            text="Enable Detection",
            variable=self.enable_detection_var,
            font=('Segoe UI', 11)
        ).pack(anchor='w', pady=15)
        
        # Enable recording
        ttk.Checkbutton(
            settings_panel,
            text="Enable Recording",
            variable=self.enable_recording_var,
            font=('Segoe UI', 11)
        ).pack(anchor='w', pady=15)
    
    def logout(self):
        """Handle logout"""
        if self.is_running:
            if messagebox.askokcancel("Logout", "Detection is running. Stop and logout?"):
                if self.detection_process:
                    try:
                        self.detection_process.terminate()
                        self.detection_process.wait(timeout=3)
                    except:
                        if self.detection_process:
                            self.detection_process.kill()
                self.root.quit()
        else:
            self.root.quit()

def main():
    """Main entry point"""
    # Show login window first
    login = LoginWindow()
    logged_in, username = login.show()
    
    if not logged_in:
        return
    
    # Create main application window
    root = tk.Tk()
    app = DetectionGUI(root, username)
    root.mainloop()

if __name__ == "__main__":
    main()
