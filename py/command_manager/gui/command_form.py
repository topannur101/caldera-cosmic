"""
Command Form Dialog for Adding/Editing Commands
"""

import tkinter as tk
from tkinter import ttk, messagebox
import re


class CommandForm:
    def __init__(self, parent, config_manager, title, command=None):
        self.config_manager = config_manager
        self.command = command  # None for new, command dict for edit
        self.result = False  # True if command was saved
        
        # Create dialog window
        self.dialog = tk.Toplevel(parent)
        self.dialog.title(title)
        self.dialog.geometry("600x500")
        self.dialog.resizable(False, False)
        
        # Make dialog modal
        self.dialog.transient(parent)
        self.dialog.grab_set()
        
        # Center dialog
        self._center_dialog(parent)
        
        # Setup GUI
        self._setup_gui()
        
        # Load command data if editing
        if self.command:
            self._load_command_data()
        
        # Handle dialog close
        self.dialog.protocol("WM_DELETE_WINDOW", self._on_cancel)
        
        # Focus on first field
        self.id_entry.focus_set()
        
        # Wait for dialog to close
        parent.wait_window(self.dialog)
    
    def _center_dialog(self, parent):
        """Center the dialog on parent window"""
        self.dialog.update_idletasks()
        width = self.dialog.winfo_width()
        height = self.dialog.winfo_height()
        parent_x = parent.winfo_x()
        parent_y = parent.winfo_y()
        parent_width = parent.winfo_width()
        parent_height = parent.winfo_height()
        
        x = parent_x + (parent_width // 2) - (width // 2)
        y = parent_y + (parent_height // 2) - (height // 2)
        
        self.dialog.geometry(f'{width}x{height}+{x}+{y}')
    
    def _setup_gui(self):
        """Setup the GUI components"""
        # Main frame
        main_frame = ttk.Frame(self.dialog, padding="20")
        main_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        
        # Configure grid weights
        self.dialog.columnconfigure(0, weight=1)
        self.dialog.rowconfigure(0, weight=1)
        main_frame.columnconfigure(1, weight=1)
        
        # Form fields
        row = 0
        
        # ID field
        ttk.Label(main_frame, text="ID:").grid(row=row, column=0, sticky=tk.W, pady=(0, 10))
        self.id_entry = ttk.Entry(main_frame, width=50)
        self.id_entry.grid(row=row, column=1, sticky=(tk.W, tk.E), pady=(0, 10))
        
        # ID help text
        id_help = ttk.Label(main_frame, text="Unique identifier (lowercase, dashes allowed)", 
                           font=('TkDefaultFont', 8), foreground='gray')
        id_help.grid(row=row+1, column=1, sticky=tk.W, pady=(0, 15))
        
        row += 2
        
        # Name field
        ttk.Label(main_frame, text="Name:").grid(row=row, column=0, sticky=tk.W, pady=(0, 10))
        self.name_entry = ttk.Entry(main_frame, width=50)
        self.name_entry.grid(row=row, column=1, sticky=(tk.W, tk.E), pady=(0, 10))
        
        # Name help text
        name_help = ttk.Label(main_frame, text="Display name for the command", 
                             font=('TkDefaultFont', 8), foreground='gray')
        name_help.grid(row=row+1, column=1, sticky=tk.W, pady=(0, 15))
        
        row += 2
        
        # Command field
        ttk.Label(main_frame, text="Command:").grid(row=row, column=0, sticky=(tk.W, tk.N), pady=(0, 10))
        
        command_frame = ttk.Frame(main_frame)
        command_frame.grid(row=row, column=1, sticky=(tk.W, tk.E), pady=(0, 10))
        command_frame.columnconfigure(0, weight=1)
        
        self.command_entry = tk.Text(command_frame, height=3, width=50, wrap=tk.WORD)
        command_scrollbar = ttk.Scrollbar(command_frame, orient=tk.VERTICAL, command=self.command_entry.yview)
        self.command_entry.configure(yscrollcommand=command_scrollbar.set)
        
        self.command_entry.grid(row=0, column=0, sticky=(tk.W, tk.E))
        command_scrollbar.grid(row=0, column=1, sticky=(tk.N, tk.S))
        
        # Command help text
        command_help = ttk.Label(main_frame, text="Full artisan command (e.g., php artisan queue:work)", 
                               font=('TkDefaultFont', 8), foreground='gray')
        command_help.grid(row=row+1, column=1, sticky=tk.W, pady=(0, 15))
        
        row += 2
        
        # Description field
        ttk.Label(main_frame, text="Description:").grid(row=row, column=0, sticky=(tk.W, tk.N), pady=(0, 10))
        
        desc_frame = ttk.Frame(main_frame)
        desc_frame.grid(row=row, column=1, sticky=(tk.W, tk.E), pady=(0, 10))
        desc_frame.columnconfigure(0, weight=1)
        
        self.description_entry = tk.Text(desc_frame, height=4, width=50, wrap=tk.WORD)
        desc_scrollbar = ttk.Scrollbar(desc_frame, orient=tk.VERTICAL, command=self.description_entry.yview)
        self.description_entry.configure(yscrollcommand=desc_scrollbar.set)
        
        self.description_entry.grid(row=0, column=0, sticky=(tk.W, tk.E))
        desc_scrollbar.grid(row=0, column=1, sticky=(tk.N, tk.S))
        
        # Description help text
        desc_help = ttk.Label(main_frame, text="Optional description of what this command does", 
                             font=('TkDefaultFont', 8), foreground='gray')
        desc_help.grid(row=row+1, column=1, sticky=tk.W, pady=(0, 15))
        
        row += 2
        
        # Enabled checkbox
        self.enabled_var = tk.BooleanVar(value=True)
        self.enabled_check = ttk.Checkbutton(main_frame, text="Enabled", variable=self.enabled_var)
        self.enabled_check.grid(row=row, column=1, sticky=tk.W, pady=(0, 20))
        
        row += 1
        
        # Buttons frame
        buttons_frame = ttk.Frame(main_frame)
        buttons_frame.grid(row=row, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(20, 0))
        
        # Save and Cancel buttons
        ttk.Button(buttons_frame, text="Save", command=self._on_save).pack(side=tk.RIGHT, padx=(10, 0))
        ttk.Button(buttons_frame, text="Cancel", command=self._on_cancel).pack(side=tk.RIGHT)
        
        # Common commands frame
        common_frame = ttk.LabelFrame(main_frame, text="Common Commands", padding="10")
        common_frame.grid(row=row+1, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(20, 0))
        
        # Common command buttons
        common_commands = [
            ("Queue Worker", "php artisan queue:work"),
            ("Schedule Worker", "php artisan schedule:work"),
            ("CLM Polling", "php artisan app:ins-clm-poll --d"),
            ("CTC Polling", "php artisan app:ins-ctc-poll --d"),
            ("STC Routine", "php artisan app:ins-stc-routine --d"),
        ]
        
        for i, (name, command) in enumerate(common_commands):
            btn = ttk.Button(common_frame, text=name, 
                           command=lambda cmd=command: self._insert_common_command(cmd))
            btn.grid(row=i//2, column=i%2, padx=5, pady=2, sticky=tk.W)
    
    def _insert_common_command(self, command):
        """Insert a common command into the command field"""
        self.command_entry.delete('1.0', tk.END)
        self.command_entry.insert('1.0', command)
    
    def _load_command_data(self):
        """Load existing command data into form"""
        if not self.command:
            return
        
        self.id_entry.insert(0, self.command.get('id', ''))
        self.id_entry.config(state='readonly')  # Don't allow ID changes when editing
        
        self.name_entry.insert(0, self.command.get('name', ''))
        
        self.command_entry.insert('1.0', self.command.get('command', ''))
        
        self.description_entry.insert('1.0', self.command.get('description', ''))
        
        self.enabled_var.set(self.command.get('enabled', True))
    
    def _validate_form(self):
        """Validate form data"""
        # Get form data
        command_id = self.id_entry.get().strip()
        name = self.name_entry.get().strip()
        command = self.command_entry.get('1.0', tk.END).strip()
        
        # Validate required fields
        if not command_id:
            messagebox.showerror("Validation Error", "ID is required.")
            self.id_entry.focus_set()
            return False
        
        if not name:
            messagebox.showerror("Validation Error", "Name is required.")
            self.name_entry.focus_set()
            return False
        
        if not command:
            messagebox.showerror("Validation Error", "Command is required.")
            self.command_entry.focus_set()
            return False
        
        # Validate ID format
        if not re.match(r'^[a-z0-9\-_]+$', command_id):
            messagebox.showerror("Validation Error", 
                               "ID must contain only lowercase letters, numbers, hyphens, and underscores.")
            self.id_entry.focus_set()
            return False
        
        # Check for duplicate ID (only when adding new command)
        if not self.command:  # New command
            existing_command = self.config_manager.get_command(command_id)
            if existing_command:
                messagebox.showerror("Validation Error", 
                                   f"A command with ID '{command_id}' already exists.")
                self.id_entry.focus_set()
                return False
        
        # Validate command format (should start with php artisan)
        if not command.startswith('php artisan'):
            if not messagebox.askyesno("Warning", 
                                     "Command doesn't start with 'php artisan'. Continue anyway?"):
                self.command_entry.focus_set()
                return False
        
        return True
    
    def _on_save(self):
        """Handle save button click"""
        if not self._validate_form():
            return
        
        # Get form data
        command_id = self.id_entry.get().strip()
        name = self.name_entry.get().strip()
        command = self.command_entry.get('1.0', tk.END).strip()
        description = self.description_entry.get('1.0', tk.END).strip()
        enabled = self.enabled_var.get()
        
        # Create command dict
        command_data = {
            'id': command_id,
            'name': name,
            'command': command,
            'description': description,
            'enabled': enabled
        }
        
        # Save command
        try:
            if self.command:  # Editing existing command
                success = self.config_manager.update_command(command_id, command_data)
                action = "updated"
            else:  # Adding new command
                success = self.config_manager.add_command(command_data)
                action = "added"
            
            if success:
                messagebox.showinfo("Success", f"Command {action} successfully.")
                self.result = True
                self.dialog.destroy()
            else:
                messagebox.showerror("Error", f"Failed to {action.replace('ed', '')} command.")
                
        except Exception as e:
            messagebox.showerror("Error", f"An error occurred: {e}")
    
    def _on_cancel(self):
        """Handle cancel button click"""
        self.result = False
        self.dialog.destroy()