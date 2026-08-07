@echo off
setlocal
cd /d "%~dp0"
echo ========================================
echo Install Detection Agent Autostart
echo ========================================
echo.
echo Creates a Windows logon task so detection_agent.py starts automatically.
echo Open Surveillance will then start/stop detect.py with no manual clicks.
echo.

set "TASK_NAME=AlertaraQC_DetectionAgent"
set "AGENT_BAT=%~dp0start_detection_agent.bat"

schtasks /Create /TN "%TASK_NAME%" /TR "\"%AGENT_BAT%\"" /SC ONLOGON /RL LIMITED /F >nul 2>&1
if errorlevel 1 (
  echo Failed to create scheduled task. Try running this bat as Administrator.
  pause
  exit /b 1
)

echo Task installed: %TASK_NAME%
echo Starting agent now...
start "" "%AGENT_BAT%"
echo.
echo Done. You can close this window.
echo Open Surveillance will auto-start/stop the camera feed.
pause
exit /b 0
