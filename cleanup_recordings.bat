@echo off
REM Daily retention cleanup — removes recordings older than 30 days.
REM Schedule this in Windows Task Scheduler (e.g. once per day at 2:00 AM).
cd /d "%~dp0"

set "PHP=c:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=php"

"%PHP%" -r "require 'api/recordings_helpers.php'; $n = cleanupExpiredRecordings(); echo 'Retention cleanup removed ' . $n . ' file(s).' . PHP_EOL;"
