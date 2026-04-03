@echo off
REM Absence Monitoring Cron Job Runner

cd /d "%~dp0"
php cron_absence_monitor.php

REM Uncomment the line below if you want to see the output before the window closes
pause
