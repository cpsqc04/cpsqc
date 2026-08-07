@echo off
echo ========================================
echo AlertaraQC CCTV Detection Agent
echo ========================================
echo.
echo Keep the camera on the router LAN; this PC on the same Wi-Fi/LAN.
echo Uses balanced stream quality from .env (CCTV_FEED_QUALITY=balanced).
echo.
cd /d "%~dp0"
set "PATH=%~dp0;%PATH%"
py detection_agent.py
pause
