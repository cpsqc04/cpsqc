@echo off
echo ========================================
echo Remove IP Camera Detection Autostart
echo ========================================

set "TASK_NAME=CPSQC IP Camera YOLO Detection"
set "STARTUP_LINK=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\%TASK_NAME%.lnk"

if exist "%STARTUP_LINK%" (
    del /f "%STARTUP_LINK%"
    echo Removed Startup shortcut.
) else (
    echo Startup shortcut not found.
)

schtasks /Query /TN "%TASK_NAME%" >nul 2>&1
if not errorlevel 1 (
    schtasks /Delete /TN "%TASK_NAME%" /F >nul
    echo Removed Task Scheduler entry.
)

echo.
echo Autostart removed.
pause
