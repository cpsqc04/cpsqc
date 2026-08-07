@echo off
echo ========================================
echo AlertaraQC CCTV Detection Agent
echo ========================================
echo.
echo This agent watches Open Surveillance on the web server.
echo When an admin opens CCTV surveillance, detect.py starts automatically.
echo.
cd /d "%~dp0"
set "PATH=%~dp0;%PATH%"
py detection_agent.py
pause
