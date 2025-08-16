# Laravel Command Manager

A Python system tray application that manages Laravel artisan daemon processes with a web interface integration.

## Features

- **System Tray Application**: Runs in the background with system tray icon
- **Web Interface**: Laravel admin interface for monitoring and controlling commands
- **Real-time Monitoring**: Live status updates and process monitoring
- **Log Management**: Rotating log files with web-based log viewing
- **GUI Configuration**: Easy command setup through Python GUI
- **API Integration**: RESTful API for Laravel communication

## Installation

### 1. Install Python Dependencies

```bash
cd py/command_manager
pip install -r requirements.txt
```

### 2. Configure Laravel Service (Optional)

Add to your Laravel `config/services.php`:

```php
'python_command_manager' => [
    'url' => env('PYTHON_COMMAND_MANAGER_URL', 'http://127.0.0.1:8765'),
    'timeout' => env('PYTHON_COMMAND_MANAGER_TIMEOUT', 10),
    'cache_time' => env('PYTHON_COMMAND_MANAGER_CACHE_TIME', 30),
],
```

### 3. Start the Command Manager

```bash
cd C:\laragon\www\caldera-cosmic
python py/command_manager/main.py
```

The application will:
- Start the system tray icon
- Launch the API server on port 8765
- Create default artisan command configurations

## Usage

### Python Application

1. **System Tray**: Right-click the tray icon for options
2. **Configure Commands**: Use "Configure Commands" to add/edit commands
3. **View Status**: Check command status from the tray menu

### Laravel Web Interface

1. Login as superuser (user ID 1)
2. Navigate to `/admin/daemon-manage`
3. View, start, stop, and monitor commands
4. View real-time logs

## Default Commands

The application comes pre-configured with these Laravel artisan commands:

- `php artisan queue:work` - Queue Worker
- `php artisan schedule:work` - Schedule Worker  
- `php artisan app:ins-clm-poll --d` - CLM Polling Daemon
- `php artisan app:ins-ctc-poll --d` - CTC Polling Daemon
- `php artisan app:ins-stc-routine --d` - STC Routine Daemon

## API Endpoints

- `GET /api/health` - Health check
- `GET /api/commands` - List all commands with status
- `POST /api/commands/{id}/start` - Start command
- `POST /api/commands/{id}/stop` - Stop command
- `GET /api/commands/{id}/status` - Get command status
- `GET /api/commands/{id}/logs` - Get command logs

## Configuration

Commands are stored in `config.json` with this structure:

```json
{
  "commands": [
    {
      "id": "queue-work",
      "name": "Queue Worker",
      "command": "php artisan queue:work",
      "description": "Process queued jobs",
      "enabled": true
    }
  ]
}
```

## Log Files

- Location: `py/command_manager/logs/`
- Format: `{command-id}.log`
- Rotation: 5MB max size, 3 backup files
- Viewable through web interface

## Troubleshooting

### Python App Won't Start
- Check Python version (3.8+)
- Install missing dependencies: `pip install -r requirements.txt`
- Ensure Laravel directory is accessible

### Laravel Can't Connect
- Verify Python app is running on port 8765
- Check firewall settings
- Test API directly: `curl http://localhost:8765/api/health`

### Commands Won't Start
- Verify artisan commands work manually
- Check working directory (should be Laravel root)
- Review command logs for error details

## Development

To modify or extend the application:

1. **Python Components**:
   - `main.py` - Entry point and system tray
   - `api_server.py` - Flask API server
   - `command_manager.py` - Process management
   - `config_manager.py` - Configuration handling
   - `gui/` - Tkinter GUI components

2. **Laravel Components**:
   - `app/Services/PythonCommandService.php` - API client
   - `resources/views/livewire/admin/daemon/manage.blade.php` - Web interface

## Auto-startup

To start the Python app automatically with Windows:

1. Create a batch file:
```batch
@echo off
cd /d "C:\laragon\www\caldera-cosmic"
python py/command_manager/main.py
```

2. Add to Windows startup folder or Task Scheduler