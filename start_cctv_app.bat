@echo off
echo ========================================
echo Starting CCTV Application
echo ========================================
echo.
cd /d C:\xampp\htdocs\cps
echo Current directory: %CD%
echo.
echo Starting CCTV application with Python 3.13...
echo.
py -3.13 cctv.py
if errorlevel 1 (
    echo.
    echo Error: Failed to start CCTV application.
    echo Please ensure:
    echo 1. Python 3.13 is installed
    echo 2. PyAudio is installed: py -3.13 -m pip install pyaudio
    echo 3. All dependencies are installed: py -3.13 -m pip install -r requirements.txt
    echo.
    pause
)









