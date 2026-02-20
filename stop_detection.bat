@echo off
echo ========================================
echo Stopping CCTV Detection Processes
echo ========================================
echo.

REM Kill all Python processes running detect.py
echo Stopping all detection processes...
taskkill /F /FI "WINDOWTITLE eq detect.py*" /T 2>nul
taskkill /F /IM python.exe /FI "COMMANDLINE eq *detect.py*" 2>nul
taskkill /F /IM pythonw.exe /FI "COMMANDLINE eq *detect.py*" 2>nul

REM Also try to find and kill by process command line
for /f "tokens=2" %%a in ('tasklist /FI "IMAGENAME eq python.exe" /FO LIST ^| findstr /I "PID"') do (
    wmic process where "ProcessId=%%a" get CommandLine 2>nul | findstr /I "detect.py" >nul
    if not errorlevel 1 (
        echo Stopping process %%a...
        taskkill /F /PID %%a 2>nul
    )
)

echo.
echo ✓ Detection processes stopped.
echo.
echo To start again, run: start_detection.bat
echo Or use the auto-start feature.
echo.
pause














