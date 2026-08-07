@echo off
cd /d "%~dp0"
echo ========================================
echo Stopping CCTV detection (auto mode)
echo ========================================
echo.
echo Signaling viewer closed and stopping detect.py...
py -c "from detection_agent import stop_detect; stop_detect('manual stop_detection.bat')" 2>nul

powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match 'detect\\.py' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }" 2>nul
if exist detect.lock del /f /q detect.lock >nul 2>&1

echo.
echo Detection stopped.
echo The agent (if still running) will auto-start again when you open Open Surveillance.
echo.
pause
