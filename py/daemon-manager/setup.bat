@echo off
echo Laravel Command Manager Setup
echo =============================
echo.

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python is not installed or not in PATH
    echo Please install Python 3.8+ and try again
    pause
    exit /b 1
)

echo Python found: 
python --version

echo.
echo Installing Python dependencies...
pip install -r requirements.txt

if errorlevel 1 (
    echo ERROR: Failed to install Python dependencies
    pause
    exit /b 1
)

echo.
echo Setup completed successfully!
echo.
echo To start the Laravel Command Manager:
echo   python main.py
echo.
echo The application will run in the system tray and start an API server on port 8765.
echo Access the web interface at: http://localhost:8000/admin/daemon-manage
echo.
pause