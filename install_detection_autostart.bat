@echo off
setlocal
cd /d "%~dp0"
echo ========================================
echo Install Detection Agent Autostart
echo ========================================
echo.
echo Creates a Windows logon task that starts the agent HIDDEN
echo (no blank Terminal window). Keeps detect.py running 24/7.
echo.

set "TASK_NAME=AlertaraQC_DetectionAgent"
set "AGENT_VBS=%~dp0start_detection_agent_silent.vbs"

schtasks /Create /TN "%TASK_NAME%" /TR "wscript.exe \"%AGENT_VBS%\"" /SC ONLOGON /RL LIMITED /F >nul 2>&1
if errorlevel 1 (
  echo Failed to create scheduled task. Try running this bat as Administrator.
  pause
  exit /b 1
)

echo Task installed: %TASK_NAME%
echo Starting agent silently now...
cscript //nologo "%AGENT_VBS%"
echo.
echo Done. No Terminal window should open for detection.
pause
exit /b 0
