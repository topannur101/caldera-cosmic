@echo off
title Laravel Command Manager
echo Starting Laravel Command Manager...
echo.

REM Change to Laravel root directory
cd /d "%~dp0..\.."

REM Start the Python application
python py/command_manager/main.py

pause