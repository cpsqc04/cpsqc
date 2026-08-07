@echo off
REM Background launcher — no console window.
cd /d "%~dp0"
cscript //nologo "%~dp0start_detection_agent_silent.vbs"
exit /b 0
