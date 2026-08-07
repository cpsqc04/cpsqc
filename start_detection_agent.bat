@echo off
echo ========================================
echo AlertaraQC CCTV Detection Agent
echo ========================================
echo.
echo Starting HIDDEN in the background (no Terminal window).
echo Open Surveillance will auto-start/stop detect.py.
echo Logs: detection_agent.log
echo.
cd /d "%~dp0"
set "PATH=%~dp0;%PATH%"

REM Prefer silent VBS launcher so Windows Terminal never opens.
cscript //nologo "%~dp0start_detection_agent_silent.vbs"
if errorlevel 1 (
  echo Silent start failed — falling back to visible agent window.
  py detection_agent.py
  pause
  exit /b 1
)

echo Agent is running in the background.
echo Close it from Task Manager if needed ^(pythonw / detection_agent.py^).
timeout /t 4 >nul
exit /b 0
