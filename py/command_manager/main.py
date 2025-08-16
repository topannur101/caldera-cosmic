#!/usr/bin/env python3
"""
Laravel Artisan Command Manager
Main entry point with tabbed GUI interface
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
        
        # GUI variables
        self.root = None
        self.main_window = None
        self.icon = None
        self.running = False
        self.minimized_to_tray = False
        
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
            pystray.MenuItem("Pengelola Daemon Caldera", lambda: None, enabled=False),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("Tampilkan", self.show_window),
            pystray.MenuItem("Sembunyikan", self.hide_window),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("Keluar", self.quit_app),
        ]
        
        return pystray.Menu(*menu_items)
    
    def show_window(self, icon=None, item=None):
        """Show the main window"""
        if self.root and self.main_window:
            self.root.deiconify()
            self.root.lift()
            self.root.focus_force()
            self.minimized_to_tray = False
    
    def hide_window(self, icon=None, item=None):
        """Hide the main window to system tray"""
        if self.root:
            self.root.withdraw()
            self.minimized_to_tray = True
    
    def on_window_close(self):
        """Handle main window close event"""
        # Hide to tray instead of closing
        self.hide_window()
        return False  # Prevent default close
    
    def quit_app(self, icon=None, item=None):
        """Quit the application"""
        print("Shutting down...")
        self.running = False
        
        # Stop all running commands
        self.command_manager.stop_all_commands()
        
        # Stop API server
        if self.api_server:
            self.api_server.stop()
        
        # Close GUI
        if self.root:
            self.root.quit()
            self.root.destroy()
        
        # Stop system tray
        if self.icon:
            self.icon.stop()
    
    def run_api_server(self):
        """Run API server in background thread"""
        try:
            self.api_server.run()
        except Exception as e:
            print(f"API Server error: {e}")
    
    def run_system_tray(self):
        """Run system tray in background thread"""
        try:
            icon_image = self.create_icon_image()
            menu = self.create_menu()
            
            self.icon = pystray.Icon(
                "Pengelola Daemon Caldera",
                icon_image,
                menu=menu
            )
            
            self.icon.run()
        except Exception as e:
            print(f"System tray error: {e}")
    
    def run(self):
        """Run the application"""
        print("Starting Laravel Command Manager...")
        self.running = True
        
        # Start API server in background thread
        api_thread = threading.Thread(target=self.run_api_server, daemon=True)
        api_thread.start()
        
        # Start system tray in background thread
        tray_thread = threading.Thread(target=self.run_system_tray, daemon=True)
        tray_thread.start()
        
        # Wait a moment for API server to start
        time.sleep(1)
        
        # Auto-start enabled commands if setting is enabled
        if self.config_manager.get_auto_start():
            print("Auto-starting enabled commands...")
            enabled_commands = self.config_manager.get_enabled_commands()
            for command in enabled_commands:
                try:
                    self.command_manager.start_command(command['id'])
                    print(f"Started: {command['name']}")
                except Exception as e:
                    print(f"Failed to start {command['name']}: {e}")
        
        # Create and show main window
        self.root = tk.Tk()
        self.main_window = MainWindow(
            self.root, 
            self.config_manager, 
            self.command_manager,
            on_close=self.on_window_close
        )
        
        print("Main window created.")
        print(f"API Server running on http://localhost:8765")
        print("System tray available for background operation.")
        
        # Run the main GUI loop
        try:
            self.root.mainloop()
        except KeyboardInterrupt:
            self.quit_app()


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