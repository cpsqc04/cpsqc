@echo off
REM Manual wrapper kept for compatibility.
REM Preferred: start_detection_agent.bat (keeps detection running 24/7).
cd /d "%~dp0"
echo ========================================
echo AlertaraQC — use the Detection Agent
echo ========================================
echo.
echo Do NOT run detect.py manually.
echo Prefer start_detection_agent.bat for continuous always-on monitoring.
echo.
echo Starting detection_agent.py now...
echo Leave this window open.
echo.
py detection_agent.py
if errorlevel 1 (
  echo.
  echo Agent exited with an error. Check .env CCTV settings.
  pause
)
