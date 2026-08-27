@echo off
REM Daily retention + FIFO cleanup — removes recordings older than 7 days
REM and oldest files first when storage exceeds 2 GB.
REM Schedule this in Windows Task Scheduler (e.g. once per day at 2:00 AM).
cd /d "%~dp0"

set "PHP=c:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=php"

"%PHP%" -r "require 'api/recordings_helpers.php'; $n = cleanupExpiredRecordings(); $f = cleanupFifoRecordings(); echo 'Retention/FIFO cleanup removed ' . ($n + $f) . ' file(s).' . PHP_EOL;"
