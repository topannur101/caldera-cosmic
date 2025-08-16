#!/usr/bin/env python3
"""
Laravel Artisan Command Manager
Main entry point for the system tray application
"""

import os
import sys
import threading
import time
from pathlib import Path

# Add current directory to Python path
sys.path.append(str(Path(__file__).parent))

from api_server import APIServer
from command_manager import CommandManager
from config_manager import ConfigManager
from gui.main_window import MainWindow

try:
    import pystray
    from PIL import Image
    import tkinter as tk
    from tkinter import messagebox
except ImportError as e:
    print(f"Missing required packages: {e}")
    print("Please install required packages:")
    print("pip install pystray pillow flask")
    sys.exit(1)


class CommandManagerApp:
    def __init__(self):
        self.config_manager = ConfigManager()
        self.command_manager = CommandManager(self.config_manager)
        self.api_server = APIServer(self.command_manager, port=8765)
        
        # System tray variables
        self.icon = None
        self.running = False
        
        # Create initial configuration if it doesn't exist
        self._initialize_default_config()
    
    def _initialize_default_config(self):
        """Initialize default artisan commands if config is empty"""
        if not self.config_manager.get_commands():
            default_commands = [
                {
                    "id": "queue-work",
                    "name": "Queue Worker", 
                    "command": "php artisan queue:work",
                    "description": "Process queued jobs",
                    "enabled": True
                },
                {
                    "id": "schedule-work",
                    "name": "Schedule Worker",
                    "command": "php artisan schedule:work", 
                    "description": "Run scheduled tasks",
                    "enabled": True
                },
                {
                    "id": "ins-clm-poll",
                    "name": "CLM Polling",
                    "command": "php artisan app:ins-clm-poll --d",
                    "description": "Climate monitoring polling daemon",
                    "enabled": True
                },
                {
                    "id": "ins-ctc-poll", 
                    "name": "CTC Polling",
                    "command": "php artisan app:ins-ctc-poll --d",
                    "description": "Calendar thickness control polling daemon",
                    "enabled": True
                },
                {
                    "id": "ins-stc-routine",
                    "name": "STC Routine",
                    "command": "php artisan app:ins-stc-routine --d", 
                    "description": "Stabilization temperature control routine daemon",
                    "enabled": True
                }
            ]
            
            for cmd in default_commands:
                self.config_manager.add_command(cmd)
    
    def create_icon_image(self):
        """Create a simple icon for the system tray"""
        # Create a simple 16x16 icon
        from PIL import Image, ImageDraw
        
        image = Image.new('RGBA', (16, 16), (0, 0, 0, 0))
        draw = ImageDraw.Draw(image)
        
        # Draw a simple gear-like icon
        draw.ellipse([2, 2, 14, 14], fill=(100, 149, 237, 255))  # Blue circle
        draw.ellipse([5, 5, 11, 11], fill=(255, 255, 255, 0))    # Transparent center
        
        return image
    
    def create_menu(self):
        """Create system tray menu"""
        menu_items = [
            pystray.MenuItem("Laravel Command Manager", lambda: None, enabled=False),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("Configure Commands", self.show_config),
            pystray.MenuItem("View Status", self.show_status),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("Exit", self.quit_app),
        ]
        
        return pystray.Menu(*menu_items)
    
    def show_config(self, icon=None, item=None):
        """Show configuration window"""
        try:
            root = tk.Tk()
            root.withdraw()  # Hide root window
            
            config_window = MainWindow(root, self.config_manager, self.command_manager)
            config_window.show()
            
        except Exception as e:
            messagebox.showerror("Error", f"Failed to open configuration: {e}")
    
    def show_status(self, icon=None, item=None):
        """Show status information"""
        try:
            status_info = []
            commands = self.config_manager.get_commands()
            
            for cmd in commands:
                status = self.command_manager.get_command_status(cmd['id'])
                status_info.append(f"{cmd['name']}: {status['status']}")
            
            status_text = "\n".join(status_info) if status_info else "No commands configured"
            
            root = tk.Tk()
            root.withdraw()
            messagebox.showinfo("Command Status", status_text)
            root.destroy()
            
        except Exception as e:
            messagebox.showerror("Error", f"Failed to get status: {e}")
    
    def quit_app(self, icon=None, item=None):
        """Quit the application"""
        print("Shutting down...")
        self.running = False
        
        # Stop all running commands
        self.command_manager.stop_all_commands()
        
        # Stop API server
        if self.api_server:
            self.api_server.stop()
        
        # Stop system tray
        if self.icon:
            self.icon.stop()
    
    def run_api_server(self):
        """Run API server in background thread"""
        try:
            self.api_server.run()
        except Exception as e:
            print(f"API Server error: {e}")
    
    def run(self):
        """Run the application"""
        print("Starting Laravel Command Manager...")
        self.running = True
        
        # Start API server in background thread
        api_thread = threading.Thread(target=self.run_api_server, daemon=True)
        api_thread.start()
        
        # Wait a moment for API server to start
        time.sleep(1)
        
        # Create and run system tray
        icon_image = self.create_icon_image()
        menu = self.create_menu()
        
        self.icon = pystray.Icon(
            "Laravel Command Manager",
            icon_image,
            menu=menu
        )
        
        print("System tray started. Use the tray icon to configure commands.")
        print(f"API Server running on http://localhost:8765")
        
        # Run system tray (this blocks)
        self.icon.run()


if __name__ == "__main__":
    # Set working directory to Laravel root
    laravel_root = Path(__file__).parent.parent.parent
    os.chdir(laravel_root)
    
    app = CommandManagerApp()
    try:
        app.run()
    except KeyboardInterrupt:
        print("\nShutting down...")
        app.quit_app()
    except Exception as e:
        print(f"Fatal error: {e}")
        sys.exit(1)