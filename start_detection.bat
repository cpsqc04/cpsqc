@echo off
REM Manual wrapper kept for compatibility.
REM Preferred: start_detection_agent.bat (auto start/stop with Open Surveillance).
cd /d "%~dp0"
echo ========================================
echo AlertaraQC — use the Detection Agent
echo ========================================
echo.
echo Do NOT run detect.py manually.
echo Open Surveillance starts and stops detection automatically.
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
