@echo off
REM Run all cron jobs for the Coin Dashboard project in an infinite loop
cd /d "%~dp0"

:loop
timeout /t 3 >nul
goto loop
