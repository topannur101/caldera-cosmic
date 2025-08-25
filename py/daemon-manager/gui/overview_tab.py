"""
Overview Tab Widget
Dashboard showing summary of all commands and system status
"""

import tkinter as tk
from tkinter import ttk, messagebox
from datetime import datetime
import platform
import psutil


class OverviewTab:
    def __init__(self, parent_frame, config_manager, command_manager, app_instance=None):
        self.frame = parent_frame
        self.config_manager = config_manager
        self.command_manager = command_manager
        self.app_instance = app_instance
        
        # GUI elements
        self.summary_cards = {}
        self.commands_summary = None
        self.system_info = None
        
        # Setup GUI
        self._setup_gui()
        
        # Initial refresh
        self.refresh()
    
    def _setup_gui(self):
        """Setup the overview tab GUI"""
        # Main container with padding
        main_container = ttk.Frame(self.frame)
        main_container.pack(fill=tk.BOTH, expand=True, padx=20, pady=20)
        
        # Summary cards section
        self._create_summary_cards(main_container)
        
        # Commands overview section  
        self._create_commands_overview(main_container)
        
        # System information section
        self._create_system_info(main_container)
    
    
    def _create_summary_cards(self, parent):
        """Create summary statistics cards"""
        cards_frame = ttk.Frame(parent)
        cards_frame.pack(fill=tk.X, pady=(0, 15))
        
        # Create a grid of cards
        cards_container = ttk.Frame(cards_frame)
        cards_container.pack(fill=tk.X)
        
        # Configure grid
        for i in range(4):
            cards_container.grid_columnconfigure(i, weight=1)
        
        # Total Commands Card
        self.summary_cards['total'] = self._create_summary_card(
            cards_container, "Total Perintah", "0", "#007bff", 0, 0
        )
        
        # Running Commands Card
        self.summary_cards['running'] = self._create_summary_card(
            cards_container, "Berjalan", "0", "#28a745", 0, 1
        )
        
        # Stopped Commands Card
        self.summary_cards['stopped'] = self._create_summary_card(
            cards_container, "Berhenti", "0", "#dc3545", 0, 2
        )
        
        # Enabled Commands Card
        self.summary_cards['enabled'] = self._create_summary_card(
            cards_container, "Aktif", "0", "#17a2b8", 0, 3
        )
    
    def _create_summary_card(self, parent, title, value, color, row, col):
        """Create a single summary card"""
        card_frame = ttk.Frame(parent, relief="raised", borderwidth=1)
        card_frame.grid(row=row, column=col, padx=0, pady=5, sticky=(tk.W, tk.E, tk.N, tk.S))
        
        # Card content
        content_frame = ttk.Frame(card_frame)
        content_frame.pack(fill=tk.BOTH, expand=True, padx=5, pady=10)
        
        # Title
        title_label = ttk.Label(content_frame, text=title, font=('TkDefaultFont', 8))
        title_label.pack(anchor=tk.W)
        
        # Value
        value_label = ttk.Label(content_frame, text=value, 
                               font=('TkDefaultFont', 16, 'bold'),
                               foreground=color)
        value_label.pack(anchor=tk.W, pady=(3, 0))
        
        return {'title': title_label, 'value': value_label}
    
    def _create_commands_overview(self, parent):
        """Create commands overview section"""
        commands_frame = ttk.LabelFrame(parent, text="Ringkasan Perintah", padding="20")
        commands_frame.pack(fill=tk.BOTH, expand=True, pady=(0, 20))
        
        # Bulk action buttons at the top
        bulk_actions_frame = ttk.Frame(commands_frame)
        bulk_actions_frame.pack(fill=tk.X, pady=(0, 15))
        
        ttk.Button(bulk_actions_frame, text="▶️ Jalankan semua", 
                  command=self._start_all_enabled,
                  style="Accent.TButton").pack(side=tk.LEFT, padx=(0, 10))
        
        ttk.Button(bulk_actions_frame, text="⏹️ Hentikan semua", 
                  command=self._stop_all_running).pack(side=tk.LEFT)
        
        # Create treeview for commands summary
        tree_frame = ttk.Frame(commands_frame)
        tree_frame.pack(fill=tk.BOTH, expand=True)
        
        columns = ("Name", "Status", "PID", "Uptime", "Enabled", "Actions")
        self.commands_summary = ttk.Treeview(tree_frame, columns=columns, show="headings", height=8)
        
        # Configure columns
        self.commands_summary.heading("Name", text="Nama Perintah")
        self.commands_summary.heading("Status", text="Status")
        self.commands_summary.heading("PID", text="Process ID")
        self.commands_summary.heading("Uptime", text="Uptime")
        self.commands_summary.heading("Enabled", text="Aktif")
        self.commands_summary.heading("Actions", text="Aksi")
        
        self.commands_summary.column("Name", width=180, minwidth=130)
        self.commands_summary.column("Status", width=100, minwidth=80)
        self.commands_summary.column("PID", width=80, minwidth=60)
        self.commands_summary.column("Uptime", width=120, minwidth=100)
        self.commands_summary.column("Enabled", width=80, minwidth=60)
        self.commands_summary.column("Actions", width=100, minwidth=80)
        
        # Scrollbar
        scrollbar = ttk.Scrollbar(tree_frame, orient=tk.VERTICAL, command=self.commands_summary.yview)
        self.commands_summary.configure(yscrollcommand=scrollbar.set)
        
        # Pack treeview and scrollbar
        self.commands_summary.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        
        # Bind double-click to toggle command
        self.commands_summary.bind("<Double-1>", self._on_command_double_click)
        
        # Configure tags for styling
        self.commands_summary.tag_configure('running', background='#d4edda', foreground='#155724')
        self.commands_summary.tag_configure('stopped', background='#f8d7da', foreground='#721c24')
        self.commands_summary.tag_configure('disabled', foreground='gray')
    
    def _create_system_info(self, parent):
        """Create system information section"""
        system_frame = ttk.LabelFrame(parent, text="Informasi Sistem", padding="20")
        system_frame.pack(fill=tk.X)
        
        # Create two columns for system info
        info_container = ttk.Frame(system_frame)
        info_container.pack(fill=tk.X)
        
        # Left column
        left_column = ttk.Frame(info_container)
        left_column.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        # Right column
        right_column = ttk.Frame(info_container)
        right_column.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True, padx=(20, 0))
        
        # System info labels (will be populated in refresh)
        self.system_info = {
            'current_time': ttk.Label(left_column, text="Waktu Saat Ini: Loading...", font=('TkDefaultFont', 9)),
            'system_uptime': ttk.Label(left_column, text="System Uptime: Loading...", font=('TkDefaultFont', 9)),
            'python_version': ttk.Label(left_column, text="Python: Loading...", font=('TkDefaultFont', 9)),
            'cpu_usage': ttk.Label(right_column, text="Penggunaan CPU: Loading...", font=('TkDefaultFont', 9)),
            'memory_usage': ttk.Label(right_column, text="Penggunaan Memory: Loading...", font=('TkDefaultFont', 9)),
            'disk_usage': ttk.Label(right_column, text="Penggunaan Disk: Loading...", font=('TkDefaultFont', 9))
        }
        
        # Pack labels
        for i, (key, label) in enumerate(self.system_info.items()):
            if i < 3:  # Left column (current_time, app_uptime, system_uptime)
                label.pack(anchor=tk.W, pady=2)
            else:  # Right column (python_version, cpu_usage, memory_usage, disk_usage)
                label.pack(anchor=tk.W, pady=2)
    
    def refresh(self):
        """Refresh all data in the overview"""
        try:
            self._refresh_summary_cards()
            self._refresh_commands_overview()
            self._refresh_system_info()
        except Exception as e:
            print(f"Error refreshing overview: {e}")
    
    def _refresh_summary_cards(self):
        """Refresh summary statistics cards"""
        try:
            commands = self.config_manager.get_commands()
            
            total = len(commands)
            running = 0
            stopped = 0
            enabled = 0
            
            for command in commands:
                status = self.command_manager.get_command_status(command['id'])
                
                if status['status'] == 'running':
                    running += 1
                else:
                    stopped += 1
                
                if command.get('enabled', True):
                    enabled += 1
            
            # Update cards
            self.summary_cards['total']['value'].config(text=str(total))
            self.summary_cards['running']['value'].config(text=str(running))
            self.summary_cards['stopped']['value'].config(text=str(stopped))
            self.summary_cards['enabled']['value'].config(text=str(enabled))
            
        except Exception as e:
            print(f"Error refreshing summary cards: {e}")
    
    def _refresh_commands_overview(self):
        """Refresh commands overview table"""
        try:
            # Clear existing items
            for item in self.commands_summary.get_children():
                self.commands_summary.delete(item)
            
            # Load commands
            commands = self.config_manager.get_commands()
            
            for command in commands:
                command_id = command['id']
                status = self.command_manager.get_command_status(command_id)
                
                # Format uptime
                uptime_text = "N/A"
                if status.get('started_at'):
                    try:
                        start_time = datetime.fromisoformat(status['started_at'])
                        uptime_delta = datetime.now() - start_time
                        
                        days = uptime_delta.days
                        hours, remainder = divmod(uptime_delta.seconds, 3600)
                        minutes, _ = divmod(remainder, 60)
                        
                        if days > 0:
                            uptime_text = f"{days}d {hours}h {minutes}m"
                        elif hours > 0:
                            uptime_text = f"{hours}h {minutes}m"
                        else:
                            uptime_text = f"{minutes}m"
                    except:
                        uptime_text = "N/A"
                
                # Determine tag for styling
                tag = ""
                if not command.get('enabled', True):
                    tag = "disabled"
                elif status['status'] == 'running':
                    tag = "running"
                else:
                    tag = "stopped"
                
                # Create actions text based on status
                if status['status'] == 'running':
                    actions_text = "⏹️ Hentikan"
                else:
                    actions_text = "▶️ Jalankan" if command.get('enabled', True) else "❌ Nonaktif"
                
                # Insert item
                item_id = self.commands_summary.insert("", tk.END, values=(
                    command['name'],
                    "Berjalan" if status['status'] == 'running' else "Berhenti",
                    status.get('pid', 'N/A'),
                    uptime_text,
                    "Ya" if command.get('enabled', True) else "Tidak",
                    actions_text
                ), tags=(tag,))
            
        except Exception as e:
            print(f"Error refreshing commands overview: {e}")
    
    def _refresh_system_info(self):
        """Refresh system information"""
        try:
            # Current time
            current_time = datetime.now().strftime("%A, %B %d, %Y - %I:%M %p")
            self.system_info['current_time'].config(text=f"Waktu Saat Ini: {current_time}")
            
            # Python version
            python_version = platform.python_version()
            self.system_info['python_version'].config(text=f"Python: {python_version}")
            
            # CPU usage
            cpu_percent = psutil.cpu_percent(interval=0.1)
            self.system_info['cpu_usage'].config(text=f"Penggunaan CPU: {cpu_percent:.1f}%")
            
            # Memory usage
            memory = psutil.virtual_memory()
            memory_percent = memory.percent
            memory_used = memory.used / (1024**3)  # GB
            memory_total = memory.total / (1024**3)  # GB
            self.system_info['memory_usage'].config(
                text=f"Penggunaan Memory: {memory_used:.1f}GB / {memory_total:.1f}GB ({memory_percent:.1f}%)"
            )
            
            # Disk usage
            disk = psutil.disk_usage('/')
            disk_percent = (disk.used / disk.total) * 100
            disk_used = disk.used / (1024**3)  # GB
            disk_total = disk.total / (1024**3)  # GB
            self.system_info['disk_usage'].config(
                text=f"Penggunaan Disk: {disk_used:.1f}GB / {disk_total:.1f}GB ({disk_percent:.1f}%)"
            )
            
            # System uptime
            try:
                boot_time = datetime.fromtimestamp(psutil.boot_time())
                uptime_delta = datetime.now() - boot_time
                days = uptime_delta.days
                hours, remainder = divmod(uptime_delta.seconds, 3600)
                minutes, _ = divmod(remainder, 60)
                
                if days > 0:
                    uptime_text = f"{days} days, {hours} hours, {minutes} minutes"
                else:
                    uptime_text = f"{hours} hours, {minutes} minutes"
                
                self.system_info['system_uptime'].config(text=f"System Uptime: {uptime_text}")
            except:
                self.system_info['system_uptime'].config(text="System Uptime: N/A")
            
        except Exception as e:
            print(f"Error refreshing system info: {e}")
            # Set fallback values
            for key, label in self.system_info.items():
                if "Loading..." in label.cget("text"):
                    label.config(text=f"{key.replace('_', ' ').title()}: N/A")
    
    def _start_all_enabled(self):
        """Start all enabled commands that are not running"""
        # Check if working directory is configured
        if not self.config_manager.is_working_directory_configured():
            messagebox.showerror(
                "Direktori Kerja Diperlukan",
                "Direktori kerja harus dikonfigurasi sebelum menjalankan perintah.\n\n"
                "Silakan buka tab Konfigurasi dan pilih direktori kerja terlebih dahulu."
            )
            return
        
        try:
            commands = self.config_manager.get_commands()
            started_count = 0
            
            for command in commands:
                if not command.get('enabled', True):
                    continue  # Skip disabled commands
                
                command_id = command['id']
                status = self.command_manager.get_command_status(command_id)
                
                if status['status'] != 'running':
                    try:
                        if self.command_manager.start_command(command_id):
                            started_count += 1
                    except Exception as e:
                        print(f"Failed to start command {command['name']}: {e}")
            
            if started_count > 0:
                messagebox.showinfo("Jalankan Massal", f"Berhasil menjalankan {started_count} perintah.")
            else:
                messagebox.showinfo("Jalankan Massal", "Tidak ada perintah yang dijalankan. Mungkin sudah berjalan atau nonaktif.")
            
            # Refresh the overview
            self.refresh()
            
        except Exception as e:
            messagebox.showerror("Error", f"Failed to start commands: {e}")
    
    def _stop_all_running(self):
        """Stop all running commands"""
        try:
            commands = self.config_manager.get_commands()
            stopped_count = 0
            
            for command in commands:
                command_id = command['id']
                status = self.command_manager.get_command_status(command_id)
                
                if status['status'] == 'running':
                    try:
                        if self.command_manager.stop_command(command_id):
                            stopped_count += 1
                    except Exception as e:
                        print(f"Failed to stop command {command['name']}: {e}")
            
            if stopped_count > 0:
                messagebox.showinfo("Hentikan Massal", f"Berhasil menghentikan {stopped_count} perintah.")
            else:
                messagebox.showinfo("Hentikan Massal", "Tidak ada perintah yang berjalan untuk dihentikan.")
            
            # Refresh the overview
            self.refresh()
            
        except Exception as e:
            messagebox.showerror("Error", f"Failed to stop commands: {e}")
    
    def _on_command_double_click(self, event):
        """Handle double-click on command to toggle start/stop"""
        try:
            # Get selected item
            selection = self.commands_summary.selection()
            if not selection:
                return
            
            item = self.commands_summary.item(selection[0])
            command_name = item['values'][0]
            
            # Find command by name
            commands = self.config_manager.get_commands()
            target_command = None
            for command in commands:
                if command['name'] == command_name:
                    target_command = command
                    break
            
            if not target_command:
                return
            
            command_id = target_command['id']
            
            # Check if command is enabled
            if not target_command.get('enabled', True):
                messagebox.showwarning("Peringatan", f"Perintah '{command_name}' nonaktif dan tidak dapat dijalankan.")
                return
            
            # Get current status and toggle
            status = self.command_manager.get_command_status(command_id)
            
            if status['status'] == 'running':
                # Stop the command
                if self.command_manager.stop_command(command_id):
                    messagebox.showinfo("Berhasil", f"Perintah '{command_name}' berhasil dihentikan.")
                else:
                    messagebox.showerror("Error", f"Gagal menghentikan perintah '{command_name}'.")
            else:
                # Check if working directory is configured before starting
                if not self.config_manager.is_working_directory_configured():
                    messagebox.showerror(
                        "Direktori Kerja Diperlukan",
                        "Direktori kerja harus dikonfigurasi sebelum menjalankan perintah.\n\n"
                        "Silakan buka tab Konfigurasi dan pilih direktori kerja terlebih dahulu."
                    )
                    return
                
                # Start the command
                if self.command_manager.start_command(command_id):
                    messagebox.showinfo("Berhasil", f"Perintah '{command_name}' berhasil dijalankan.")
                else:
                    messagebox.showerror("Error", f"Gagal menjalankan perintah '{command_name}'.")
            
            # Refresh the overview
            self.refresh()
            
        except Exception as e:
            messagebox.showerror("Error", f"Failed to toggle command: {e}")
    
    def cleanup(self):
        """Cleanup resources"""
        pass