#!/usr/bin/env python3
"""
Laravel Artisan Command Manager
Main entry point with tabbed GUI interface
"""

import os
import sys
import threading
import time
import socket
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
        
        # Single instance variables
        self.lock_socket = None
        
        # Track application start time
        from datetime import datetime
        self.start_time = datetime.now()
        
        # Create initial configuration if it doesn't exist
        self._initialize_default_config()
    
    def check_single_instance(self):
        """Check if another instance is already running"""
        try:
            self.lock_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            self.lock_socket.bind(('localhost', 8766))  # Use different port for lock
            return True  # No other instance running
        except socket.error:
            # Another instance is running
            return False
    
    def release_instance_lock(self):
        """Release the instance lock"""
        if self.lock_socket:
            try:
                self.lock_socket.close()
            except:
                pass
            self.lock_socket = None
    
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
        ]
        
        # Add dynamic show/hide menu item based on window state
        if self.minimized_to_tray:
            menu_items.append(pystray.MenuItem("Tampilkan", self.show_window))
        else:
            menu_items.append(pystray.MenuItem("Sembunyikan", self.hide_window))
        
        menu_items.extend([
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("Keluar", self.quit_app),
        ])
        
        return pystray.Menu(*menu_items)
    
    def update_tray_menu(self):
        """Update system tray menu based on current window state"""
        if self.icon:
            self.icon.menu = self.create_menu()
            # Update the menu - pystray automatically updates when menu is changed
    
    def show_window(self, icon=None, item=None):
        """Show the main window"""
        if self.root and self.main_window:
            self.root.deiconify()
            self.root.lift()
            self.root.focus_force()
            self.minimized_to_tray = False
            self.update_tray_menu()
    
    def hide_window(self, icon=None, item=None):
        """Hide the main window to system tray"""
        if self.root:
            self.root.withdraw()
            self.minimized_to_tray = True
            self.update_tray_menu()
    
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
        
        # Release instance lock
        self.release_instance_lock()
        
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
    
    def _validate_startup_working_directory(self):
        """Validate working directory configuration at startup"""
        try:
            is_valid, message = self.config_manager.validate_current_working_directory()
            
            print(f"Working directory validation: {message}")
            
            if not is_valid:
                print("WARNING: Working directory must be explicitly configured!")
                
                # Check if no directory is configured at all
                configured_dir = self.config_manager.get_working_directory()
                if not configured_dir:
                    print("No working directory configured - user must set one before running commands")
                    
                    # Show mandatory configuration dialog
                    try:
                        temp_root = tk.Tk()
                        temp_root.withdraw()
                        
                        result = messagebox.askquestion(
                            "Direktori Kerja Diperlukan",
                            "Aplikasi memerlukan direktori kerja untuk menjalankan perintah Laravel.\n\n"
                            "Direktori kerja menentukan lokasi di mana perintah artisan akan dijalankan.\n\n"
                            "Apakah Anda ingin memilih direktori kerja sekarang?\n\n"
                            "(Anda juga dapat mengaturnya nanti melalui tab Konfigurasi)",
                            icon="question"
                        )
                        
                        if result == 'yes':
                            from tkinter import filedialog
                            directory = filedialog.askdirectory(
                                title="Pilih Direktori Kerja Laravel",
                                initialdir="."
                            )
                            
                            if directory:
                                if self.config_manager.set_working_directory(directory):
                                    messagebox.showinfo(
                                        "Berhasil", 
                                        f"Direktori kerja berhasil diatur ke:\n{directory}"
                                    )
                                    print(f"Working directory set to: {directory}")
                                else:
                                    messagebox.showerror(
                                        "Error", 
                                        f"Gagal mengatur direktori kerja ke:\n{directory}"
                                    )
                        
                        temp_root.destroy()
                    except Exception as e:
                        print(f"Could not show working directory configuration dialog: {e}")
                else:
                    # Directory is configured but invalid
                    print(f"Configured directory is invalid: {configured_dir}")
                    
                    try:
                        temp_root = tk.Tk()
                        temp_root.withdraw()
                        messagebox.showwarning(
                            "Direktori Kerja Tidak Valid",
                            f"Direktori kerja yang dikonfigurasi tidak valid:\n\n{configured_dir}\n\n"
                            f"Masalah: {message}\n\n"
                            "Silakan buka tab Konfigurasi dan pilih direktori kerja yang valid."
                        )
                        temp_root.destroy()
                    except Exception as e:
                        print(f"Could not show invalid directory warning: {e}")
            
        except Exception as e:
            print(f"Error validating working directory at startup: {e}")
            print("Continuing without working directory validation...")
    
    def run(self):
        """Run the application"""
        print("Starting Laravel Command Manager...")
        
        # Check for single instance
        if not self.check_single_instance():
            print("Another instance of Command Manager is already running.")
            print("Exiting...")
            
            # Show message box to user
            try:
                # Create temporary root for message box if GUI isn't ready yet
                temp_root = tk.Tk()
                temp_root.withdraw()  # Hide the window
                messagebox.showinfo(
                    "Aplikasi Sudah Berjalan", 
                    "Aplikasi Pengelola Daemon Caldera sudah berjalan"
                )
                temp_root.destroy()
            except Exception as e:
                print(f"Could not show message box: {e}")
            
            return
        
        # Validate working directory configuration
        self._validate_startup_working_directory()
        
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
            
            # Check if working directory is configured before auto-starting
            if not self.config_manager.is_working_directory_configured():
                print("SKIPPING AUTO-START: Working directory must be explicitly configured")
                print("Commands will not be auto-started until working directory is set")
            else:
                enabled_commands = self.config_manager.get_enabled_commands()
                command_ids = [cmd['id'] for cmd in enabled_commands]
                
                if command_ids:
                    # Use bulk start method for better error handling
                    results = self.command_manager.start_multiple_commands(command_ids)
                    
                    for command in enabled_commands:
                        command_id = command['id']
                        if results.get(command_id, False):
                            print(f"Started: {command['name']}")
                        else:
                            print(f"Failed to start: {command['name']}")
                else:
                    print("No enabled commands found for auto-start.")
        
        # Create and show main window
        self.root = tk.Tk()
        self.main_window = MainWindow(
            self.root, 
            self.config_manager, 
            self.command_manager,
            app_instance=self,
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
        app.release_instance_lock()
        sys.exit(1)