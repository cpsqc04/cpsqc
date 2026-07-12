@echo off
REM Background launcher with auto-restart for IP camera detection.
cd /d "%~dp0"
set "PATH=%~dp0;%PATH%"

:loop
echo [%date% %time%] Starting YOLO IP camera detection...
py detect.py
echo [%date% %time%] Detection stopped. Restarting in 3 seconds...
timeout /t 3 /nobreak >nul
goto loop
