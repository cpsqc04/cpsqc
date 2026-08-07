@echo off
echo ========================================
echo AlertaraQC CCTV Detection Agent
echo ========================================
echo.
echo This agent keeps detect.py running so Open Surveillance
echo shows the live camera feed immediately when opened.
echo It also handles LAN camera scans from Camera Management.
echo.
cd /d "%~dp0"
set "PATH=%~dp0;%PATH%"
py detection_agent.py
pause
