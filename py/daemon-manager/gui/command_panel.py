"""
Individual Command Panel Widget
Shows command status, controls, and live output (refactored from command_tab.py)
"""

import tkinter as tk
from tkinter import ttk, messagebox, scrolledtext
import threading
import queue
import time
from datetime import datetime


class CommandPanel:
    def __init__(self, parent_frame, command, command_manager):
        self.parent_frame = parent_frame
        self.command = command
        self.command_manager = command_manager
        self.command_id = command['id']
        
        # Create main frame for this panel
        self.frame = ttk.Frame(parent_frame)
        
        # State management
        self.is_active = True
        self.output_queue = queue.Queue()
        self.last_log_position = 0
        
        # GUI elements
        self.status_label = None
        self.pid_label = None
        self.uptime_label = None
        self.start_button = None
        self.stop_button = None
        self.output_text = None
        
        # Setup GUI
        self._setup_gui()
        
        # Start output monitoring
        self._start_output_monitoring()
        
        # Initial refresh
        self.refresh()
    
    def pack(self, **kwargs):
        """Pack the panel frame"""
        self.frame.pack(**kwargs)
    
    def pack_forget(self):
        """Hide the panel frame"""
        self.frame.pack_forget()
    
    def _setup_gui(self):
        """Setup the command panel GUI"""
        # Main container with padding
        main_container = ttk.Frame(self.frame)
        main_container.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        # Command info section
        self._create_info_section(main_container)
        
        # Control buttons section
        self._create_controls_section(main_container)
        
        # Output section
        self._create_output_section(main_container)
    
    def _create_info_section(self, parent):
        """Create command information section"""
        info_frame = ttk.LabelFrame(parent, text="Informasi Perintah", padding="15")
        info_frame.pack(fill=tk.X, pady=(0, 15))
        
        # Command details
        details_frame = ttk.Frame(info_frame)
        details_frame.pack(fill=tk.X)
        
        # Left side - Basic info
        left_frame = ttk.Frame(details_frame)
        left_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        ttk.Label(left_frame, text="Nama:", font=('TkDefaultFont', 10, 'bold')).grid(row=0, column=0, sticky=tk.W, pady=2)
        ttk.Label(left_frame, text=self.command['name'], font=('TkDefaultFont', 10)).grid(row=0, column=1, sticky=tk.W, padx=(10, 0), pady=2)
        
        ttk.Label(left_frame, text="Command:", font=('TkDefaultFont', 10, 'bold')).grid(row=1, column=0, sticky=tk.W, pady=2)
        ttk.Label(left_frame, text=self.command['command'], font=('TkDefaultFont', 10)).grid(row=1, column=1, sticky=tk.W, padx=(10, 0), pady=2)
        
        if self.command.get('description'):
            ttk.Label(left_frame, text="Deskripsi:", font=('TkDefaultFont', 10, 'bold')).grid(row=2, column=0, sticky=tk.W, pady=2)
            ttk.Label(left_frame, text=self.command['description'], font=('TkDefaultFont', 10)).grid(row=2, column=1, sticky=tk.W, padx=(10, 0), pady=2)
        
        # Right side - Status info
        right_frame = ttk.Frame(details_frame)
        right_frame.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)
        
        # Status indicator
        status_container = ttk.Frame(right_frame)
        status_container.pack(anchor=tk.NE)
        
        ttk.Label(status_container, text="Status:", font=('TkDefaultFont', 10, 'bold')).pack(side=tk.LEFT)
        self.status_label = ttk.Label(status_container, text="Unknown", font=('TkDefaultFont', 10, 'bold'))
        self.status_label.pack(side=tk.LEFT, padx=(5, 0))
        
        # PID info
        pid_container = ttk.Frame(right_frame)
        pid_container.pack(anchor=tk.NE, pady=(5, 0))
        
        ttk.Label(pid_container, text="PID:", font=('TkDefaultFont', 9)).pack(side=tk.LEFT)
        self.pid_label = ttk.Label(pid_container, text="N/A", font=('TkDefaultFont', 9))
        self.pid_label.pack(side=tk.LEFT, padx=(5, 0))
        
        # Uptime info
        uptime_container = ttk.Frame(right_frame)
        uptime_container.pack(anchor=tk.NE, pady=(5, 0))
        
        ttk.Label(uptime_container, text="Uptime:", font=('TkDefaultFont', 9)).pack(side=tk.LEFT)
        self.uptime_label = ttk.Label(uptime_container, text="N/A", font=('TkDefaultFont', 9))
        self.uptime_label.pack(side=tk.LEFT, padx=(5, 0))
    
    def _create_controls_section(self, parent):
        """Create control buttons section"""
        controls_frame = ttk.Frame(parent)
        controls_frame.pack(fill=tk.X, pady=(0, 15))
        
        # Left side - Primary controls
        primary_controls = ttk.Frame(controls_frame)
        primary_controls.pack(side=tk.LEFT)
        
        self.start_button = ttk.Button(primary_controls, text="▶ Jalankan", 
                                      command=self._start_command, style="Accent.TButton")
        self.start_button.pack(side=tk.LEFT, padx=(0, 10))
        
        self.stop_button = ttk.Button(primary_controls, text="⏹ Hentikan", 
                                     command=self._stop_command)
        self.stop_button.pack(side=tk.LEFT, padx=(0, 10))
        
        ttk.Button(primary_controls, text="🔄 Refresh", 
                  command=self.refresh).pack(side=tk.LEFT, padx=(0, 10))
        
        # Right side - Secondary controls
        secondary_controls = ttk.Frame(controls_frame)
        secondary_controls.pack(side=tk.RIGHT)
        
        ttk.Button(secondary_controls, text="📄 Lihat Log", 
                  command=self._view_full_logs).pack(side=tk.LEFT, padx=(10, 0))
        
        ttk.Button(secondary_controls, text="🗑 Bersihkan Output", 
                  command=self._clear_output).pack(side=tk.LEFT, padx=(10, 0))
    
    def _create_output_section(self, parent):
        """Create live output section"""
        output_frame = ttk.Frame(parent)
        output_frame.pack(fill=tk.BOTH, expand=True)
        
        # Output text area with scrollbar
        text_frame = ttk.Frame(output_frame)
        text_frame.pack(fill=tk.BOTH, expand=True)
        
        self.output_text = scrolledtext.ScrolledText(
            text_frame,
            height=15,
            font=('Consolas', 9),
            bg='#1e1e1e',
            fg='#d4d4d4',
            insertbackground='#d4d4d4',
            selectbackground='#3a3d41',
            wrap=tk.WORD,
            state=tk.DISABLED
        )
        self.output_text.pack(fill=tk.BOTH, expand=True)
        
        # Configure text tags for colored output
        self.output_text.tag_configure("info", foreground="#4dc9f6")
        self.output_text.tag_configure("warning", foreground="#ffc107")
        self.output_text.tag_configure("error", foreground="#dc3545")
        self.output_text.tag_configure("success", foreground="#28a745")
        self.output_text.tag_configure("timestamp", foreground="#6c757d")
    
    def _start_command(self):
        """Start the command"""
        try:
            if self.command_manager.start_command(self.command_id):
                self._add_output_line(f"🚀 Menjalankan perintah: {self.command['name']}", "info")
            else:
                self._add_output_line(f"❌ Gagal menjalankan perintah", "error")
        except Exception as e:
            self._add_output_line(f"❌ Error menjalankan perintah: {e}", "error")
        
        self.refresh()
    
    def _stop_command(self):
        """Stop the command"""
        try:
            if self.command_manager.stop_command(self.command_id):
                self._add_output_line(f"⏹ Menghentikan perintah: {self.command['name']}", "warning")
            else:
                self._add_output_line(f"❌ Gagal menghentikan perintah", "error")
        except Exception as e:
            self._add_output_line(f"❌ Error menghentikan perintah: {e}", "error")
        
        self.refresh()
    
    def _view_full_logs(self):
        """Open full logs in a separate window"""
        try:
            logs = self.command_manager.get_command_logs(self.command_id, 500)
            
            # Create log viewer window
            log_window = tk.Toplevel(self.frame)
            log_window.title(f"Log Lengkap - {self.command['name']}")
            log_window.geometry("900x600")
            
            # Log text area
            text_frame = ttk.Frame(log_window)
            text_frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
            
            log_text = scrolledtext.ScrolledText(
                text_frame,
                font=('Consolas', 9),
                bg='#1e1e1e',
                fg='#d4d4d4',
                wrap=tk.WORD
            )
            log_text.pack(fill=tk.BOTH, expand=True)
            
            # Insert logs
            if logs:
                log_text.insert(tk.END, "\n".join(logs))
            else:
                log_text.insert(tk.END, "Tidak ada log tersedia.")
            
            log_text.config(state=tk.DISABLED)
            
            # Close button
            ttk.Button(log_window, text="Tutup", 
                      command=log_window.destroy).pack(pady=(0, 10))
            
        except Exception as e:
            messagebox.showerror("Error", f"Failed to load logs: {e}")
    
    def _clear_output(self):
        """Clear the output display"""
        self.output_text.config(state=tk.NORMAL)
        self.output_text.delete(1.0, tk.END)
        self.output_text.config(state=tk.DISABLED)
        self._add_output_line("📝 Output dibersihkan", "info")
    
    def _add_output_line(self, text, tag=""):
        """Add a line to the output display"""
        timestamp = datetime.now().strftime("%H:%M:%S")
        line = f"[{timestamp}] {text}\n"
        
        self.output_text.config(state=tk.NORMAL)
        
        # Insert timestamp
        self.output_text.insert(tk.END, f"[{timestamp}] ", "timestamp")
        
        # Insert main text with tag
        if tag:
            self.output_text.insert(tk.END, text + "\n", tag)
        else:
            self.output_text.insert(tk.END, text + "\n")
        
        self.output_text.config(state=tk.DISABLED)
        
        # Auto-scroll to bottom
        self.output_text.see(tk.END)
    
    def _start_output_monitoring(self):
        """Start monitoring command output"""
        def monitor_output():
            while self.is_active:
                try:
                    # Get recent logs
                    logs = self.command_manager.get_command_logs(self.command_id, 50)
                    
                    # Check for new output
                    if len(logs) > self.last_log_position:
                        new_logs = logs[self.last_log_position:]
                        for log_line in new_logs:
                            # Queue the new log line
                            self.output_queue.put(log_line)
                        
                        self.last_log_position = len(logs)
                    
                    time.sleep(2)  # Check every 2 seconds
                    
                except Exception as e:
                    print(f"Output monitoring error for {self.command_id}: {e}")
                    time.sleep(5)
        
        # Start monitoring thread
        monitor_thread = threading.Thread(target=monitor_output, daemon=True)
        monitor_thread.start()
        
        # Process queued output
        self._process_output_queue()
    
    def _process_output_queue(self):
        """Process queued output lines"""
        try:
            while True:
                log_line = self.output_queue.get_nowait()
                
                # Determine log level based on content
                tag = ""
                if "ERROR" in log_line.upper() or "EXCEPTION" in log_line.upper():
                    tag = "error"
                elif "WARNING" in log_line.upper() or "WARN" in log_line.upper():
                    tag = "warning"
                elif "SUCCESS" in log_line.upper() or "COMPLETED" in log_line.upper():
                    tag = "success"
                elif "INFO" in log_line.upper():
                    tag = "info"
                
                # Add to output (without adding another timestamp)
                self.output_text.config(state=tk.NORMAL)
                if tag:
                    self.output_text.insert(tk.END, log_line + "\n", tag)
                else:
                    self.output_text.insert(tk.END, log_line + "\n")
                self.output_text.config(state=tk.DISABLED)
                self.output_text.see(tk.END)
                
        except queue.Empty:
            pass
        except Exception as e:
            print(f"Error processing output queue: {e}")
        
        # Schedule next check
        if self.is_active:
            self.frame.after(1000, self._process_output_queue)  # Check every second
    
    def refresh(self):
        """Refresh command status and information"""
        try:
            status = self.command_manager.get_command_status(self.command_id)
            
            # Update status label
            status_text = status['status'].upper()
            if status_text == "RUNNING":
                self.status_label.config(text="BERJALAN", foreground="green")
                self.start_button.config(state=tk.DISABLED)
                self.stop_button.config(state=tk.NORMAL)
            else:
                self.status_label.config(text="BERHENTI", foreground="red")
                self.start_button.config(state=tk.NORMAL)
                self.stop_button.config(state=tk.DISABLED)
            
            # Update PID
            if status.get('pid'):
                self.pid_label.config(text=str(status['pid']))
            else:
                self.pid_label.config(text="N/A")
            
            # Update uptime
            if status.get('started_at'):
                try:
                    start_time = datetime.fromisoformat(status['started_at'])
                    uptime = datetime.now() - start_time
                    
                    # Format uptime
                    days = uptime.days
                    hours, remainder = divmod(uptime.seconds, 3600)
                    minutes, seconds = divmod(remainder, 60)
                    
                    if days > 0:
                        uptime_text = f"{days}d {hours}h {minutes}m"
                    elif hours > 0:
                        uptime_text = f"{hours}h {minutes}m"
                    else:
                        uptime_text = f"{minutes}m {seconds}s"
                    
                    self.uptime_label.config(text=uptime_text)
                except:
                    self.uptime_label.config(text="N/A")
            else:
                self.uptime_label.config(text="N/A")
                
        except Exception as e:
            print(f"Error refreshing command panel {self.command_id}: {e}")
    
    def update_command_info(self, command):
        """Update command information"""
        self.command = command
        # Refresh display to show updated info
        self.refresh()
    
    def cleanup(self):
        """Cleanup resources"""
        self.is_active = False