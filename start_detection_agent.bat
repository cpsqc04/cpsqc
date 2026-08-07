@echo off
echo ========================================
echo AlertaraQC CCTV Detection Agent
echo ========================================
echo.
echo Keep this window open on the on-site PC.
echo detect.py starts ONLY when Open Surveillance is open,
echo and stops when that page is closed.
echo.
cd /d "%~dp0"
set "PATH=%~dp0;%PATH%"
py detection_agent.py
echo.
echo Agent stopped. Detection was also stopped.
pause
