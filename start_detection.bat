@echo Open
echo ========================================
echo Starting YOLO IP Camera Detection
echo ========================================
echo.
cd /d "%~dp0"
set "PATH=%~dp0;%PATH%"
echo Current directory: %CD%
echo.
echo Starting IP camera detection script...
echo Press Ctrl+C to stop
echo.
py detect.py
pause

















