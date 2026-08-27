@echo off
echo ========================================
echo Install OpenH264 for H.264 Recording
echo ========================================
echo.
cd /d "%~dp0"

where py >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python launcher "py" was not found. Install Python first.
    pause
    exit /b 1
)

py install_openh264.py
if errorlevel 1 (
    echo.
    echo Install failed. See messages above.
    pause
    exit /b 1
)

echo.
pause
