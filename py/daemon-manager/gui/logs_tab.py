"""
Centralized Logs Tab Widget
View and search logs from all commands in one place
"""

import tkinter as tk
from tkinter import ttk, scrolledtext, filedialog, messagebox
import re
from datetime import datetime
from pathlib import Path


class LogsTab:
    def __init__(self, parent_frame, config_manager, command_manager):
        self.frame = parent_frame
        self.config_manager = config_manager
        self.command_manager = command_manager
        
        # State variables
        self.selected_command = tk.StringVar()
        self.auto_refresh = tk.BooleanVar(value=True)
        self.filter_text = tk.StringVar()
        self.show_timestamps = tk.BooleanVar(value=True)
        self.log_level = tk.StringVar(value="ALL")
        
        # GUI elements
        self.command_combo = None
        self.logs_text = None
        self.status_label = None
        
        # Setup GUI
        self._setup_gui()
        
        # Initial refresh
        self.refresh()
    
    def _setup_gui(self):
        """Setup the logs tab GUI"""
        # Main container with padding
        main_container = ttk.Frame(self.frame)
        main_container.pack(fill=tk.BOTH, expand=True, padx=20, pady=20)
        
        # Controls section
        self._create_controls_section(main_container)
        
        # Logs display section
        self._create_logs_section(main_container)
        
        # Status bar
        self._create_status_bar(main_container)
    
    
    def _create_controls_section(self, parent):
        """Create controls section"""
        controls_frame = ttk.Frame(parent)
        controls_frame.pack(fill=tk.X, pady=(0, 15))
        
        # Top row - Command selection and refresh
        top_row = ttk.Frame(controls_frame)
        top_row.pack(fill=tk.X, pady=(0, 10))
        
        # Command selection
        ttk.Label(top_row, text="Perintah:").pack(side=tk.LEFT)
        self.command_combo = ttk.Combobox(top_row, textvariable=self.selected_command, 
                                         state="readonly", width=25)
        self.command_combo.pack(side=tk.LEFT, padx=(5, 15))
        self.command_combo.bind("<<ComboboxSelected>>", self._on_command_selected)
        
        # Auto refresh
        auto_refresh_check = ttk.Checkbutton(top_row, text="Refresh otomatis", 
                                           variable=self.auto_refresh)
        auto_refresh_check.pack(side=tk.LEFT, padx=(0, 15))
        
        # Manual refresh button
        ttk.Button(top_row, text="🔄 Refresh", 
                  command=self.refresh).pack(side=tk.LEFT, padx=(0, 15))
        
        # Clear logs button
        ttk.Button(top_row, text="🗑️ Bersihkan", 
                  command=self._clear_logs).pack(side=tk.LEFT, padx=(0, 15))
        
        # Export logs button
        ttk.Button(top_row, text="💾 Export", 
                  command=self._export_logs).pack(side=tk.LEFT)
        
        # Bottom row - Filtering and display options
        bottom_row = ttk.Frame(controls_frame)
        bottom_row.pack(fill=tk.X)
        
        # Filter
        ttk.Label(bottom_row, text="Filter:").pack(side=tk.LEFT)
        filter_entry = ttk.Entry(bottom_row, textvariable=self.filter_text, width=30)
        filter_entry.pack(side=tk.LEFT, padx=(5, 15))
        filter_entry.bind("<KeyRelease>", self._on_filter_changed)
        
        # Log level filter
        ttk.Label(bottom_row, text="Level:").pack(side=tk.LEFT)
        level_combo = ttk.Combobox(bottom_row, textvariable=self.log_level, 
                                  values=["ALL", "ERROR", "WARNING", "INFO", "DEBUG"], 
                                  state="readonly", width=10)
        level_combo.pack(side=tk.LEFT, padx=(5, 15))
        level_combo.bind("<<ComboboxSelected>>", self._on_filter_changed)
        
        # Show timestamps
        timestamps_check = ttk.Checkbutton(bottom_row, text="Tampilkan Timestamp", 
                                         variable=self.show_timestamps,
                                         command=self._on_display_options_changed)
        timestamps_check.pack(side=tk.LEFT)
    
    def _create_logs_section(self, parent):
        """Create logs display section"""
        logs_frame = ttk.Frame(parent)
        logs_frame.pack(fill=tk.BOTH, expand=True, pady=(0, 10))
        
        # Logs text area with scrollbar
        self.logs_text = scrolledtext.ScrolledText(
            logs_frame,
            height=20,
            font=('Consolas', 9),
            bg='#1e1e1e',
            fg='#d4d4d4',
            insertbackground='#d4d4d4',
            selectbackground='#3a3d41',
            wrap=tk.WORD,
            state=tk.DISABLED
        )
        self.logs_text.pack(fill=tk.BOTH, expand=True)
        
        # Configure text tags for colored output
        self.logs_text.tag_configure("error", foreground="#ff6b6b")
        self.logs_text.tag_configure("warning", foreground="#ffd93d")
        self.logs_text.tag_configure("info", foreground="#6bcf7f")
        self.logs_text.tag_configure("debug", foreground="#a8a8a8")
        self.logs_text.tag_configure("timestamp", foreground="#8e8e8e")
        self.logs_text.tag_configure("command_name", foreground="#74c0fc", font=('Consolas', 9, 'bold'))
        self.logs_text.tag_configure("highlight", background="#3a3d41")
    
    def _create_status_bar(self, parent):
        """Create status bar"""
        status_frame = ttk.Frame(parent)
        status_frame.pack(fill=tk.X)
        
        self.status_label = ttk.Label(status_frame, text="Siap", 
                                     font=('TkDefaultFont', 8), foreground='gray')
        self.status_label.pack(side=tk.LEFT)
        
        # Line count on the right
        self.line_count_label = ttk.Label(status_frame, text="0 baris", 
                                         font=('TkDefaultFont', 8), foreground='gray')
        self.line_count_label.pack(side=tk.RIGHT)
    
    def _populate_command_combo(self):
        """Populate command combobox with available commands"""
        commands = self.config_manager.get_commands()
        command_names = ["SEMUA PERINTAH"] + [cmd['name'] for cmd in commands]
        
        self.command_combo['values'] = command_names
        
        # Set default selection
        if not self.selected_command.get():
            self.selected_command.set("SEMUA PERINTAH")
    
    def _on_command_selected(self, event=None):
        """Handle command selection change"""
        self.refresh()
    
    def _on_filter_changed(self, event=None):
        """Handle filter text change"""
        self._apply_filters()
    
    def _on_display_options_changed(self):
        """Handle display options change"""
        self.refresh()
    
    def _clear_logs(self):
        """Clear the logs display"""
        self.logs_text.config(state=tk.NORMAL)
        self.logs_text.delete(1.0, tk.END)
        self.logs_text.config(state=tk.DISABLED)
        self.status_label.config(text="Log dibersihkan")
        self.line_count_label.config(text="0 baris")
    
    def _export_logs(self):
        """Export current logs to file"""
        try:
            file_path = filedialog.asksaveasfilename(
                title="Export Logs",
                defaultextension=".txt",
                filetypes=[("Text files", "*.txt"), ("All files", "*.*")]
            )
            
            if file_path:
                content = self.logs_text.get(1.0, tk.END)
                with open(file_path, 'w', encoding='utf-8') as f:
                    f.write(content)
                
                messagebox.showinfo("Sukses", f"Log diekspor ke {file_path}")
                self.status_label.config(text=f"Log diekspor ke {Path(file_path).name}")
                
        except Exception as e:
            messagebox.showerror("Galat", f"Gagal mengekspor log: {e}")
            self.status_label.config(text="Ekspor gagal")
    
    def _apply_filters(self):
        """Apply current filters to the logs display"""
        try:
            filter_text = self.filter_text.get().lower()
            log_level = self.log_level.get()
            
            # Get all content
            content = self.logs_text.get(1.0, tk.END)
            lines = content.split('\n')
            
            # Clear and reapply
            self.logs_text.config(state=tk.NORMAL)
            self.logs_text.delete(1.0, tk.END)
            
            filtered_lines = []
            for line in lines:
                if not line.strip():
                    continue
                
                # Apply text filter
                if filter_text and filter_text not in line.lower():
                    continue
                
                # Apply log level filter
                if log_level != "ALL":
                    if log_level == "ERROR" and "ERROR" not in line.upper():
                        continue
                    elif log_level == "WARNING" and "WARNING" not in line.upper() and "WARN" not in line.upper():
                        continue
                    elif log_level == "INFO" and "INFO" not in line.upper():
                        continue
                    elif log_level == "DEBUG" and "DEBUG" not in line.upper():
                        continue
                
                filtered_lines.append(line)
            
            # Re-insert filtered content with highlighting
            for line in filtered_lines:
                self._insert_log_line(line, highlight_filter=filter_text)
            
            self.logs_text.config(state=tk.DISABLED)
            self.line_count_label.config(text=f"{len(filtered_lines)} baris")
            
        except Exception as e:
            print(f"Error applying filters: {e}")
    
    def _insert_log_line(self, line, highlight_filter=""):
        """Insert a log line with appropriate formatting"""
        if not line.strip():
            return
        
        # Determine log level and apply color
        tag = ""
        if "ERROR" in line.upper():
            tag = "error"
        elif "WARNING" in line.upper() or "WARN" in line.upper():
            tag = "warning"
        elif "INFO" in line.upper():
            tag = "info"
        elif "DEBUG" in line.upper():
            tag = "debug"
        
        # Parse timestamp if present
        timestamp_match = re.match(r'^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})', line)
        if timestamp_match and self.show_timestamps.get():
            timestamp = timestamp_match.group(1)
            rest_of_line = line[len(timestamp):].strip()
            
            # Insert timestamp
            self.logs_text.insert(tk.END, timestamp + " ", "timestamp")
            
            # Insert rest with appropriate tag
            if highlight_filter and highlight_filter.lower() in rest_of_line.lower():
                # Split and highlight
                parts = rest_of_line.lower().split(highlight_filter.lower())
                start_idx = 0
                for i, part in enumerate(parts[:-1]):
                    # Insert part before highlight
                    if part:
                        actual_part = rest_of_line[start_idx:start_idx + len(part)]
                        self.logs_text.insert(tk.END, actual_part, tag)
                        start_idx += len(part)
                    
                    # Insert highlighted part
                    highlighted_part = rest_of_line[start_idx:start_idx + len(highlight_filter)]
                    self.logs_text.insert(tk.END, highlighted_part, "highlight")
                    start_idx += len(highlight_filter)
                
                # Insert final part
                if parts[-1]:
                    final_part = rest_of_line[start_idx:]
                    self.logs_text.insert(tk.END, final_part, tag)
            else:
                self.logs_text.insert(tk.END, rest_of_line, tag)
            
        else:
            # No timestamp parsing, insert whole line
            if highlight_filter and highlight_filter.lower() in line.lower():
                # Apply highlighting
                parts = line.lower().split(highlight_filter.lower())
                start_idx = 0
                for i, part in enumerate(parts[:-1]):
                    if part:
                        actual_part = line[start_idx:start_idx + len(part)]
                        self.logs_text.insert(tk.END, actual_part, tag)
                        start_idx += len(part)
                    
                    highlighted_part = line[start_idx:start_idx + len(highlight_filter)]
                    self.logs_text.insert(tk.END, highlighted_part, "highlight")
                    start_idx += len(highlight_filter)
                
                if parts[-1]:
                    final_part = line[start_idx:]
                    self.logs_text.insert(tk.END, final_part, tag)
            else:
                self.logs_text.insert(tk.END, line, tag)
        
        self.logs_text.insert(tk.END, "\n")
    
    def refresh(self):
        """Refresh logs display"""
        try:
            self.status_label.config(text="Memuat log...")
            
            # Populate command combo if needed
            self._populate_command_combo()
            
            # Clear current display
            self.logs_text.config(state=tk.NORMAL)
            self.logs_text.delete(1.0, tk.END)
            
            selected_command = self.selected_command.get()
            
            if selected_command == "SEMUA PERINTAH":
                # Load logs from all commands
                commands = self.config_manager.get_commands()
                all_logs = []
                
                for command in commands:
                    command_id = command['id']
                    command_name = command['name']
                    
                    try:
                        logs = self.command_manager.get_command_logs(command_id, 100)
                        for log_line in logs:
                            # Prefix with command name
                            prefixed_line = f"[{command_name}] {log_line}"
                            all_logs.append(prefixed_line)
                    except Exception as e:
                        print(f"Galat memuat log untuk {command_id}: {e}")
                
                # Sort by timestamp if possible
                try:
                    all_logs.sort()
                except:
                    pass  # Keep original order if sorting fails
                
                # Display logs
                for log_line in all_logs:
                    self._insert_log_line(log_line)
                
                self.line_count_label.config(text=f"{len(all_logs)} baris")
                self.status_label.config(text=f"Memuat log dari {len(commands)} perintah")
                
            else:
                # Load logs from specific command
                commands = self.config_manager.get_commands()
                target_command = None
                
                for command in commands:
                    if command['name'] == selected_command:
                        target_command = command
                        break
                
                if target_command:
                    command_id = target_command['id']
                    logs = self.command_manager.get_command_logs(command_id, 200)
                    
                    for log_line in logs:
                        self._insert_log_line(log_line)
                    
                    self.line_count_label.config(text=f"{len(logs)} baris")
                    self.status_label.config(text=f"Memuat log untuk {selected_command}")
                else:
                    self.status_label.config(text="Perintah tidak ditemukan")
            
            self.logs_text.config(state=tk.DISABLED)
            
            # Auto-scroll to bottom
            self.logs_text.see(tk.END)
            
            # Apply current filters
            if self.filter_text.get() or self.log_level.get() != "ALL":
                self._apply_filters()
            
        except Exception as e:
            self.status_label.config(text=f"Galat memuat log: {e}")
            print(f"Galat merefresh log: {e}")
        
        # Schedule next auto-refresh
        if self.auto_refresh.get():
            self.frame.after(10000, self.refresh)  # 10 seconds
    
    def cleanup(self):
        """Cleanup resources"""
        # Stop auto-refresh
        self.auto_refresh.set(False)