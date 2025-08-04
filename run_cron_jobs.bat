@echo off
REM Run all cron jobs for the Coin Dashboard project in an infinite loop
cd /d "%~dp0"

:loop
php cron\cron_process_orders.php
php cron\update_open_trades.php
timeout /t 3 >nul
goto loop
