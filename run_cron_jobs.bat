@echo off
REM Run all cron jobs for the Coin Dashboard project
cd /d "%~dp0"

php cron\cron_wallet_usd.php
php cron\cron_process_orders.php
