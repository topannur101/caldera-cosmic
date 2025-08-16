"""
Main GUI Window for Laravel Command Manager Configuration
"""

import tkinter as tk
from tkinter import ttk, messagebox, filedialog
import sys
from pathlib import Path

# Add parent directory to path
sys.path.append(str(Path(__file__).parent.parent))

from gui.command_form import CommandForm


class MainWindow:
    def __init__(self, parent, config_manager, command_manager):
        self.config_manager = config_manager
        self.command_manager = command_manager
        self.parent = parent
        
        # Create main window
        self.window = tk.Toplevel(parent)
        self.window.title("Laravel Command Manager - Configuration")
        self.window.geometry("900x600")
        self.window.resizable(True, True)
        
        # Make window modal
        self.window.transient(parent)
        self.window.grab_set()
        
        # Center window
        self._center_window()
        
        # Setup GUI
        self._setup_gui()
        
        # Load commands
        self._refresh_commands()
        
        # Handle window close
        self.window.protocol("WM_DELETE_WINDOW", self._on_close)
    
    def _center_window(self):
        """Center the window on screen"""
        self.window.update_idletasks()
        width = self.window.winfo_width()
        height = self.window.winfo_height()
        x = (self.window.winfo_screenwidth() // 2) - (width // 2)
        y = (self.window.winfo_screenheight() // 2) - (height // 2)
        self.window.geometry(f'{width}x{height}+{x}+{y}')
    
    def _setup_gui(self):
        """Setup the GUI components"""
        # Main frame
        main_frame = ttk.Frame(self.window, padding="10")
        main_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        
        # Configure grid weights
        self.window.columnconfigure(0, weight=1)
        self.window.rowconfigure(0, weight=1)
        main_frame.columnconfigure(1, weight=1)
        main_frame.rowconfigure(1, weight=1)
        
        # Title
        title_label = ttk.Label(main_frame, text="Laravel Command Manager Configuration", 
                               font=('TkDefaultFont', 16, 'bold'))
        title_label.grid(row=0, column=0, columnspan=3, pady=(0, 20), sticky=tk.W)
        
        # Commands list frame
        list_frame = ttk.LabelFrame(main_frame, text="Configured Commands", padding="10")
        list_frame.grid(row=1, column=0, columnspan=2, sticky=(tk.W, tk.E, tk.N, tk.S), pady=(0, 10))
        list_frame.columnconfigure(0, weight=1)
        list_frame.rowconfigure(0, weight=1)
        
        # Treeview for commands
        columns = ("ID", "Name", "Command", "Status", "Enabled")
        self.tree = ttk.Treeview(list_frame, columns=columns, show="headings", height=15)
        
        # Configure columns
        self.tree.heading("ID", text="ID")
        self.tree.heading("Name", text="Name")
        self.tree.heading("Command", text="Command")
        self.tree.heading("Status", text="Status")
        self.tree.heading("Enabled", text="Enabled")
        
        self.tree.column("ID", width=120, minwidth=80)
        self.tree.column("Name", width=150, minwidth=100)
        self.tree.column("Command", width=300, minwidth=200)
        self.tree.column("Status", width=80, minwidth=60)
        self.tree.column("Enabled", width=80, minwidth=60)
        
        # Scrollbars
        v_scrollbar = ttk.Scrollbar(list_frame, orient=tk.VERTICAL, command=self.tree.yview)
        h_scrollbar = ttk.Scrollbar(list_frame, orient=tk.HORIZONTAL, command=self.tree.xview)
        self.tree.configure(yscrollcommand=v_scrollbar.set, xscrollcommand=h_scrollbar.set)
        
        # Grid treeview and scrollbars
        self.tree.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        v_scrollbar.grid(row=0, column=1, sticky=(tk.N, tk.S))
        h_scrollbar.grid(row=1, column=0, sticky=(tk.W, tk.E))
        
        # Buttons frame
        buttons_frame = ttk.Frame(main_frame)
        buttons_frame.grid(row=2, column=0, columnspan=2, pady=(10, 0), sticky=(tk.W, tk.E))
        
        # Command management buttons
        ttk.Button(buttons_frame, text="Add Command", 
                  command=self._add_command).grid(row=0, column=0, padx=(0, 5))
        ttk.Button(buttons_frame, text="Edit Command", 
                  command=self._edit_command).grid(row=0, column=1, padx=5)
        ttk.Button(buttons_frame, text="Delete Command", 
                  command=self._delete_command).grid(row=0, column=2, padx=5)
        
        # Separator
        ttk.Separator(buttons_frame, orient=tk.VERTICAL).grid(row=0, column=3, sticky=(tk.N, tk.S), padx=10)
        
        # Process control buttons
        ttk.Button(buttons_frame, text="Start", 
                  command=self._start_command).grid(row=0, column=4, padx=5)
        ttk.Button(buttons_frame, text="Stop", 
                  command=self._stop_command).grid(row=0, column=5, padx=5)
        ttk.Button(buttons_frame, text="View Logs", 
                  command=self._view_logs).grid(row=0, column=6, padx=5)
        
        # Separator
        ttk.Separator(buttons_frame, orient=tk.VERTICAL).grid(row=0, column=7, sticky=(tk.N, tk.S), padx=10)
        
        # Utility buttons
        ttk.Button(buttons_frame, text="Refresh", 
                  command=self._refresh_commands).grid(row=0, column=8, padx=5)
        ttk.Button(buttons_frame, text="Import", 
                  command=self._import_config).grid(row=0, column=9, padx=5)
        ttk.Button(buttons_frame, text="Export", 
                  command=self._export_config).grid(row=0, column=10, padx=5)
        
        # Close button
        ttk.Button(buttons_frame, text="Close", 
                  command=self._on_close).grid(row=0, column=11, padx=(20, 0))
    
    def _refresh_commands(self):
        """Refresh the commands list"""
        # Clear existing items
        for item in self.tree.get_children():
            self.tree.delete(item)
        
        # Load commands
        commands = self.config_manager.get_commands()
        
        for cmd in commands:
            command_id = cmd['id']
            status = self.command_manager.get_command_status(command_id)
            
            self.tree.insert("", tk.END, values=(
                command_id,
                cmd['name'],
                cmd['command'],
                status['status'],
                "Yes" if cmd.get('enabled', True) else "No"
            ))
    
    def _get_selected_command_id(self):
        """Get the selected command ID"""
        selection = self.tree.selection()
        if not selection:
            return None
        
        item = self.tree.item(selection[0])
        return item['values'][0]  # First column is ID
    
    def _add_command(self):
        """Add a new command"""
        form = CommandForm(self.window, self.config_manager, "Add Command")
        if form.result:
            self._refresh_commands()
    
    def _edit_command(self):
        """Edit selected command"""
        command_id = self._get_selected_command_id()
        if not command_id:
            messagebox.showwarning("Warning", "Please select a command to edit.")
            return
        
        command = self.config_manager.get_command(command_id)
        if command:
            form = CommandForm(self.window, self.config_manager, "Edit Command", command)
            if form.result:
                self._refresh_commands()
    
    def _delete_command(self):
        """Delete selected command"""
        command_id = self._get_selected_command_id()
        if not command_id:
            messagebox.showwarning("Warning", "Please select a command to delete.")
            return
        
        command = self.config_manager.get_command(command_id)
        if not command:
            return
        
        # Confirm deletion
        if messagebox.askyesno("Confirm Delete", 
                              f"Are you sure you want to delete command '{command['name']}'?"):
            
            # Stop command if running
            status = self.command_manager.get_command_status(command_id)
            if status['status'] == 'running':
                if messagebox.askyesno("Command Running", 
                                      "This command is currently running. Stop it first?"):
                    self.command_manager.stop_command(command_id)
                else:
                    return
            
            # Delete command
            if self.config_manager.remove_command(command_id):
                messagebox.showinfo("Success", "Command deleted successfully.")
                self._refresh_commands()
            else:
                messagebox.showerror("Error", "Failed to delete command.")
    
    def _start_command(self):
        """Start selected command"""
        command_id = self._get_selected_command_id()
        if not command_id:
            messagebox.showwarning("Warning", "Please select a command to start.")
            return
        
        command = self.config_manager.get_command(command_id)
        if not command:
            return
        
        if self.command_manager.start_command(command_id):
            messagebox.showinfo("Success", f"Command '{command['name']}' started successfully.")
            self._refresh_commands()
        else:
            messagebox.showerror("Error", "Failed to start command.")
    
    def _stop_command(self):
        """Stop selected command"""
        command_id = self._get_selected_command_id()
        if not command_id:
            messagebox.showwarning("Warning", "Please select a command to stop.")
            return
        
        command = self.config_manager.get_command(command_id)
        if not command:
            return
        
        if self.command_manager.stop_command(command_id):
            messagebox.showinfo("Success", f"Command '{command['name']}' stopped successfully.")
            self._refresh_commands()
        else:
            messagebox.showerror("Error", "Failed to stop command.")
    
    def _view_logs(self):
        """View logs for selected command"""
        command_id = self._get_selected_command_id()
        if not command_id:
            messagebox.showwarning("Warning", "Please select a command to view logs.")
            return
        
        command = self.config_manager.get_command(command_id)
        if not command:
            return
        
        # Get logs
        logs = self.command_manager.get_command_logs(command_id, 200)
        
        # Create log viewer window
        log_window = tk.Toplevel(self.window)
        log_window.title(f"Logs - {command['name']}")
        log_window.geometry("800x600")
        
        # Text widget with scrollbar
        text_frame = ttk.Frame(log_window)
        text_frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        text_widget = tk.Text(text_frame, wrap=tk.WORD)
        scrollbar = ttk.Scrollbar(text_frame, orient=tk.VERTICAL, command=text_widget.yview)
        text_widget.configure(yscrollcommand=scrollbar.set)
        
        text_widget.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        
        # Insert logs
        if logs:
            text_widget.insert(tk.END, "\n".join(logs))
        else:
            text_widget.insert(tk.END, "No logs available.")
        
        text_widget.config(state=tk.DISABLED)
        
        # Close button
        ttk.Button(log_window, text="Close", 
                  command=log_window.destroy).pack(pady=(0, 10))
    
    def _import_config(self):
        """Import configuration from file"""
        file_path = filedialog.askopenfilename(
            title="Import Configuration",
            filetypes=[("JSON files", "*.json"), ("All files", "*.*")]
        )
        
        if file_path:
            if self.config_manager.import_config(file_path):
                messagebox.showinfo("Success", "Configuration imported successfully.")
                self._refresh_commands()
            else:
                messagebox.showerror("Error", "Failed to import configuration.")
    
    def _export_config(self):
        """Export configuration to file"""
        file_path = filedialog.asksaveasfilename(
            title="Export Configuration",
            defaultextension=".json",
            filetypes=[("JSON files", "*.json"), ("All files", "*.*")]
        )
        
        if file_path:
            if self.config_manager.export_config(file_path):
                messagebox.showinfo("Success", "Configuration exported successfully.")
            else:
                messagebox.showerror("Error", "Failed to export configuration.")
    
    def _on_close(self):
        """Handle window close"""
        self.window.destroy()
    
    def show(self):
        """Show the window"""
        self.window.focus_set()
        self.parent.wait_window(self.window)