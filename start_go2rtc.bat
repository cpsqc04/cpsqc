@echo off
echo ========================================
echo AlertaraQC — install / start go2rtc
echo ========================================
echo.
echo Downloads go2rtc (if needed) and starts low-latency WebRTC live view.
echo Detection agent also auto-starts go2rtc; this script is for manual use.
echo.
cd /d "%~dp0"
py -3 go2rtc_manager.py ensure
if errorlevel 1 (
  echo Failed. Ensure Python is installed and cameras.json has credentials.
  pause
  exit /b 1
)
echo.
echo Open: http://127.0.0.1:1984/stream.html?src=alertara_live
echo.
pause
