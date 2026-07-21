@echo Open
echo ========================================
echo Install IP Camera Detection Autostart
echo ========================================
cd /d "%~dp0"

set "TASK_NAME=CPSQC IP Camera YOLO Detection"
set "RUNNER=%~dp0run_detection_silent.bat"
set "STARTUP_LINK=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\%TASK_NAME%.lnk"

where py >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python launcher "py" was not found. Install Python first.
    pause
    exit /b 1
)

if not exist "%RUNNER%" (
    echo ERROR: Missing %RUNNER%
    pause
    exit /b 1
)

echo Installing autostart for current user...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$startup = [Environment]::GetFolderPath('Startup');" ^
  "$link = Join-Path $startup '%TASK_NAME%.lnk';" ^
  "$w = New-Object -ComObject WScript.Shell;" ^
  "$s = $w.CreateShortcut($link);" ^
  "$s.TargetPath = '%RUNNER%';" ^
  "$s.WorkingDirectory = '%~dp0';" ^
  "$s.WindowStyle = 7;" ^
  "$s.Description = 'Auto-start YOLO IP camera detection for Open Surveillance';" ^
  "$s.Save();" ^
  "if (Test-Path $link) { Write-Host 'OK' } else { exit 1 }"

if errorlevel 1 (
    echo ERROR: Could not create Startup shortcut.
    pause
    exit /b 1
)

echo.
echo Autostart installed successfully.
echo IP camera detection will start when you log in to Windows.
echo Shortcut: %STARTUP_LINK%
echo.
echo To remove later, run: uninstall_detection_autostart.bat
echo.
pause
