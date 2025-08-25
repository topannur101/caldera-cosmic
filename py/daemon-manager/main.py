#!/usr/bin/env python3
"""
Laravel Artisan Daemon Manager
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

# Improved import handling for PyInstaller
missing_packages = []

try:
    import tkinter as tk
    from tkinter import messagebox, filedialog
except ImportError as e:
    missing_packages.append("tkinter")
    print(f"Missing tkinter: {e}")

try:
    import flask
except ImportError as e:
    missing_packages.append("flask")
    print(f"Missing flask: {e}")

# Only exit if we're not running from PyInstaller
if missing_packages and not getattr(sys, 'frozen', False):
    print(f"Missing required packages: {', '.join(missing_packages)}")
    print("Please install required packages:")
    print("pip install flask")
    sys.exit(1)
elif missing_packages:
    print(f"Warning: Some packages appear missing in frozen app: {', '.join(missing_packages)}")
    print("This might be a PyInstaller packaging issue.")


def get_icon_path():
    """Get the correct path to icon.ico for both development and PyInstaller"""
    if getattr(sys, 'frozen', False):
        # Running as PyInstaller bundle
        bundle_dir = Path(sys._MEIPASS)
        icon_path = bundle_dir / 'icon.ico'
    else:
        # Running in development
        icon_path = Path(__file__).parent / 'icon.ico'
    
    if icon_path.exists():
        return str(icon_path)
    else:
        print(f"Warning: Icon file not found at {icon_path}")
        return None


def set_window_icon(window):
    """Set the window icon for tkinter window"""
    icon_path = get_icon_path()
    if icon_path:
        try:
            window.iconbitmap(icon_path)
            print(f"Window icon set successfully: {icon_path}")
        except Exception as e:
            print(f"Failed to set window icon: {e}")
    else:
        print("No icon file found, using default window icon")


class CommandManagerApp:
    def __init__(self):
        self.config_manager = ConfigManager()
        self.command_manager = CommandManager(self.config_manager)
        self.api_server = APIServer(self.command_manager, port=8765)
        
        # GUI variables
        self.root = None
        self.main_window = None
        self.running = False
        
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
    
    def on_window_close(self):
        """Handle main window close event with confirmation"""
        # Show confirmation dialog
        result = messagebox.askyesno(
            "Konfirmasi Keluar",
            "Semua perintah akan dihentikan. Yakin ingin keluar?",
            icon='question'
        )
        
        if result:
            # Stop all running commands and quit
            self.quit_app()
            return True  # Allow close
        else:
            return False  # Prevent close
    
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
        
    
    def run_api_server(self):
        """Run API server in background thread"""
        try:
            self.api_server.run()
        except Exception as e:
            print(f"API Server error: {e}")
    
    
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
                        # Set icon for temporary dialog window too
                        set_window_icon(temp_root)
                        
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
                        # Set icon for temporary dialog window too
                        set_window_icon(temp_root)
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
        print("Starting Laravel Daemon Manager...")
        
        # Check for single instance
        if not self.check_single_instance():
            print("Another instance of Daemon Manager is already running.")
            print("Exiting...")
            
            # Show message box to user
            try:
                # Create temporary root for message box if GUI isn't ready yet
                temp_root = tk.Tk()
                temp_root.withdraw()  # Hide the window
                # Set icon for temporary dialog window too
                set_window_icon(temp_root)
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
        
        # Set the window icon
        set_window_icon(self.root)
        
        self.main_window = MainWindow(
            self.root, 
            self.config_manager, 
            self.command_manager,
            app_instance=self,
            on_close=self.on_window_close
        )
        
        print("Main window created.")
        print(f"API Server running on http://localhost:8765")
        
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