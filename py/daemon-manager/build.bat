@echo off
echo Building Laravel Command Manager Executable...
echo.

REM Change to command_manager directory
cd /d "%~dp0"

REM Check if Python is available
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python is not installed or not in PATH
    pause
    exit /b 1
)

REM Check if PyInstaller is installed
python -c "import PyInstaller" >nul 2>&1
if errorlevel 1 (
    echo Installing PyInstaller and dependencies...
    pip install -r requirements-build.txt
    if errorlevel 1 (
        echo ERROR: Failed to install dependencies
        pause
        exit /b 1
    )
)

REM Clean previous build
echo Cleaning previous build...
if exist "dist" rmdir /s /q "dist"
if exist "build" rmdir /s /q "build"

REM Create logs directory if it doesn't exist
if not exist "logs" mkdir "logs"

REM Create default config if it doesn't exist
if not exist "config.json" (
    echo Creating default config.json...
    echo {"commands": []} > config.json
)

REM Build executable using spec file
echo Building executable...
pyinstaller command_manager.spec

if errorlevel 1 (
    echo ERROR: Build failed
    pause
    exit /b 1
)

REM Copy additional files to dist directory
echo Copying additional files...
if exist "dist\command_manager.exe" (
    echo.
    echo SUCCESS: Executable created at dist\command_manager.exe
    echo.
    echo The executable includes:
    echo - GUI application with system tray
    echo - API server on port 8765
    echo - Configuration management
    echo - Log viewer
    echo.
    echo To run: double-click dist\command_manager.exe
    echo.
) else (
    echo ERROR: Executable not found in dist directory
    pause
    exit /b 1
)

pause