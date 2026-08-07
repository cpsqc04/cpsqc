@echo off
echo ========================================
echo AlertaraQC CCTV Detection Agent
echo ========================================
echo.
echo Keep this window open once on the on-site PC.
echo Open Surveillance will AUTO-START and AUTO-STOP detect.py.
echo No need to click start_detection.bat or stop_detection.bat.
echo.
cd /d "%~dp0"
set "PATH=%~dp0;%PATH%"
py detection_agent.py
echo.
echo Agent stopped. Detection was also stopped.
pause
