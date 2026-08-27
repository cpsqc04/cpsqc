@echo off
setlocal
cd /d "%~dp0"
echo ========================================
echo Uninstall Detection Agent Autostart
echo ========================================
echo.
schtasks /Delete /TN "AlertaraQC_DetectionAgent" /F >nul 2>&1
echo Scheduled task removed (if it existed).
echo.
echo Tip: close any open start_detection_agent.bat window to fully stop the agent.
pause
exit /b 0
