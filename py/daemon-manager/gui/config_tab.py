"""
Configuration Tab Widget
Manage commands configuration, add/edit/delete commands
"""

import tkinter as tk
from tkinter import ttk, messagebox, filedialog
import re
import sys
import os
from pathlib import Path

# Add parent directory to path
sys.path.append(str(Path(__file__).parent.parent))

from gui.command_form import CommandForm


class ConfigTab:
    def __init__(self, parent_frame, config_manager, command_manager, on_change_callback=None):
        self.frame = parent_frame
        self.config_manager = config_manager
        self.command_manager = command_manager
        self.on_change_callback = on_change_callback
        
        # GUI elements
        self.commands_tree = None
        
        # Setup GUI
        self._setup_gui()
        
        # Load initial data
        self.refresh()
        
        # Load auto-start setting
        self._load_auto_start_setting()
        
        # Load working directory setting
        self._load_working_directory_setting()
    
    def _setup_gui(self):
        """Setup the configuration tab GUI"""
        # Main container with padding
        main_container = ttk.Frame(self.frame)
        main_container.pack(fill=tk.BOTH, expand=True, padx=20, pady=20)
        
        # Commands list section
        self._create_commands_section(main_container)
        
        # Import/Export section
        self._create_import_export_section(main_container)
    
    
    def _create_commands_section(self, parent):
        """Create commands list section"""
        commands_frame = ttk.LabelFrame(parent, text="Perintah Terkonfigurasi", padding="15")
        commands_frame.pack(fill=tk.BOTH, expand=True, pady=(0, 15))
        
        # Toolbar at top of commands section
        toolbar_frame = ttk.Frame(commands_frame)
        toolbar_frame.pack(fill=tk.X, pady=(0, 10))
        
        # Command management buttons
        ttk.Button(toolbar_frame, text="➕ Tambah", 
                  command=self._add_command).pack(side=tk.LEFT, padx=(0, 5))
        ttk.Button(toolbar_frame, text="✏️ Edit", 
                  command=self._edit_command).pack(side=tk.LEFT, padx=5)
        ttk.Button(toolbar_frame, text="🗑️ Hapus", 
                  command=self._delete_command).pack(side=tk.LEFT, padx=5)
        
        # Right side - Refresh button
        ttk.Button(toolbar_frame, text="🔄 Refresh", 
                  command=self.refresh).pack(side=tk.RIGHT, padx=5)
        
        # Commands treeview
        tree_frame = ttk.Frame(commands_frame)
        tree_frame.pack(fill=tk.BOTH, expand=True)
        
        # Treeview with columns
        columns = ("ID", "Name", "Command", "Description", "Enabled")
        self.commands_tree = ttk.Treeview(tree_frame, columns=columns, show="headings", height=12)
        
        # Configure columns
        self.commands_tree.heading("ID", text="ID")
        self.commands_tree.heading("Name", text="Nama")
        self.commands_tree.heading("Command", text="Command")
        self.commands_tree.heading("Description", text="Deskripsi")
        self.commands_tree.heading("Enabled", text="Aktif")
        
        self.commands_tree.column("ID", width=120, minwidth=80)
        self.commands_tree.column("Name", width=150, minwidth=100)
        self.commands_tree.column("Command", width=250, minwidth=200)
        self.commands_tree.column("Description", width=200, minwidth=150)
        self.commands_tree.column("Enabled", width=80, minwidth=60)
        
        # Scrollbars
        v_scrollbar = ttk.Scrollbar(tree_frame, orient=tk.VERTICAL, command=self.commands_tree.yview)
        h_scrollbar = ttk.Scrollbar(tree_frame, orient=tk.HORIZONTAL, command=self.commands_tree.xview)
        self.commands_tree.configure(yscrollcommand=v_scrollbar.set, xscrollcommand=h_scrollbar.set)
        
        # Grid treeview and scrollbars
        self.commands_tree.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        v_scrollbar.grid(row=0, column=1, sticky=(tk.N, tk.S))
        h_scrollbar.grid(row=1, column=0, sticky=(tk.W, tk.E))
        
        # Configure grid weights
        tree_frame.grid_rowconfigure(0, weight=1)
        tree_frame.grid_columnconfigure(0, weight=1)
        
        # Bind double-click to edit
        self.commands_tree.bind("<Double-1>", self._on_double_click)
    
    
    def _create_import_export_section(self, parent):
        """Create settings and import/export section"""
        ie_frame = ttk.Frame(parent)
        ie_frame.pack(fill=tk.X, pady=(15, 0))
        
        # Left side - Settings (Working Directory and Auto-start)
        left_frame = ttk.Frame(ie_frame)
        left_frame.pack(side=tk.LEFT, fill=tk.Y, padx=(10, 25))
        
        # Working directory frame
        wd_frame = ttk.Frame(left_frame)
        wd_frame.pack(fill=tk.X, pady=(0, 10))
        
        ttk.Label(wd_frame, text="Direktori kerja:").pack(anchor=tk.W, pady=(0, 5))
        
        # Entry with validation indicator
        wd_input_frame = ttk.Frame(wd_frame)
        wd_input_frame.pack(fill=tk.X)
        
        self.working_dir_var = tk.StringVar()
        self.working_dir_entry = ttk.Entry(wd_input_frame, textvariable=self.working_dir_var, width=40)
        self.working_dir_entry.pack(side=tk.LEFT, fill=tk.X, expand=True)
        
        # Validation status indicator
        self.working_dir_status_label = ttk.Label(wd_input_frame, text="", width=3)
        self.working_dir_status_label.pack(side=tk.RIGHT, padx=(5, 0))
        
        # Status message label
        self.working_dir_message_label = ttk.Label(wd_frame, text="", font=('TkDefaultFont', 8))
        self.working_dir_message_label.pack(anchor=tk.W, pady=(2, 0))
        
        # Bind click event to open folder selection
        self.working_dir_entry.bind("<Button-1>", self._on_working_dir_click)
        # Bind variable change to validate
        self.working_dir_var.trace_add("write", self._on_working_dir_changed)
        # Add flag to prevent multiple dialogs
        self._dialog_open = False
        
        # Auto-start checkbox
        self.auto_start_var = tk.BooleanVar()
        self.auto_start_check = ttk.Checkbutton(
            left_frame, 
            text="Jalankan semua perintah ketika aplikasi dijalankan",
            variable=self.auto_start_var,
            command=self._on_auto_start_changed
        )
        self.auto_start_check.pack(anchor=tk.W, pady=(5, 0))
        
        # Right side - Import/Export buttons (vertical layout)
        right_frame = ttk.Frame(ie_frame)
        right_frame.pack(side=tk.RIGHT, fill=tk.Y, padx=(25, 10))
        
        ttk.Button(right_frame, text="📁 Impor Konfigurasi", 
                  command=self._import_config).pack(fill=tk.X, pady=(0, 5))
        ttk.Button(right_frame, text="💾 Ekspor Konfigurasi", 
                  command=self._export_config).pack(fill=tk.X)
    
    def _get_selected_command_id(self):
        """Get the selected command ID"""
        selection = self.commands_tree.selection()
        if not selection:
            return None
        
        item = self.commands_tree.item(selection[0])
        return item['values'][0]  # First column is ID
    
    def _on_double_click(self, event):
        """Handle double-click on command item"""
        self._edit_command()
    
    def _add_command(self):
        """Add a new command"""
        dialog = CommandFormDialog(self.frame, self.config_manager, "Tambah Perintah")
        if dialog.result:
            self.refresh()
            if self.on_change_callback:
                self.on_change_callback()
    
    def _edit_command(self):
        """Edit selected command"""
        command_id = self._get_selected_command_id()
        if not command_id:
            messagebox.showwarning("Peringatan", "Silakan pilih perintah untuk diedit.")
            return
        
        command = self.config_manager.get_command(command_id)
        if command:
            dialog = CommandFormDialog(self.frame, self.config_manager, "Edit Perintah", command)
            if dialog.result:
                self.refresh()
                if self.on_change_callback:
                    self.on_change_callback()
    
    def _delete_command(self):
        """Delete selected command"""
        command_id = self._get_selected_command_id()
        if not command_id:
            messagebox.showwarning("Peringatan", "Silakan pilih perintah untuk dihapus.")
            return
        
        command = self.config_manager.get_command(command_id)
        if not command:
            return
        
        # Confirm deletion
        if messagebox.askyesno("Konfirmasi Hapus", 
                              f"Yakin ingin menghapus perintah '{command['name']}'?"):
            
            # Note: Commands should be stopped via Overview tab if needed
            
            # Delete command
            if self.config_manager.remove_command(command_id):
                messagebox.showinfo("Berhasil", "Perintah berhasil dihapus.")
                self.refresh()
                if self.on_change_callback:
                    self.on_change_callback()
            else:
                messagebox.showerror("Error", "Gagal menghapus perintah.")
    
    
    def _import_config(self):
        """Import configuration from file"""
        file_path = filedialog.askopenfilename(
            title="Import Konfigurasi",
            filetypes=[("JSON files", "*.json"), ("All files", "*.*")]
        )
        
        if file_path:
            if self.config_manager.import_config(file_path):
                messagebox.showinfo("Berhasil", "Konfigurasi berhasil diimport.")
                self.refresh()
                if self.on_change_callback:
                    self.on_change_callback()
            else:
                messagebox.showerror("Error", "Gagal mengimport konfigurasi.")
    
    def _export_config(self):
        """Export configuration to file"""
        file_path = filedialog.asksaveasfilename(
            title="Export Konfigurasi",
            defaultextension=".json",
            filetypes=[("JSON files", "*.json"), ("All files", "*.*")]
        )
        
        if file_path:
            if self.config_manager.export_config(file_path):
                messagebox.showinfo("Berhasil", "Konfigurasi berhasil diexport.")
            else:
                messagebox.showerror("Error", "Gagal mengexport konfigurasi.")
    
    def refresh(self):
        """Refresh the commands list"""
        # Clear existing items
        for item in self.commands_tree.get_children():
            self.commands_tree.delete(item)
        
        # Load commands
        commands = self.config_manager.get_commands()
        
        for command in commands:
            command_id = command['id']
            
            # Add to tree
            item_id = self.commands_tree.insert("", tk.END, values=(
                command_id,
                command['name'],
                command['command'],
                command.get('description', ''),
                "Ya" if command.get('enabled', True) else "Tidak"
            ))
            
            # Disable appearance for disabled commands
            if not command.get('enabled', True):
                self.commands_tree.item(item_id, tags=('disabled',))
        
        # Configure tags
        self.commands_tree.tag_configure('disabled', foreground='gray')
    
    def _load_auto_start_setting(self):
        """Load auto-start setting from config"""
        auto_start = self.config_manager.get_auto_start()
        self.auto_start_var.set(auto_start)
    
    def _on_auto_start_changed(self):
        """Handle auto-start checkbox change"""
        auto_start = self.auto_start_var.get()
        self.config_manager.set_auto_start(auto_start)
        
        if self.on_change_callback:
            self.on_change_callback()
    
    def _load_working_directory_setting(self):
        """Load working directory setting from config"""
        working_dir = self.config_manager.get_working_directory()
        self.working_dir_var.set(working_dir)
        # Validate immediately after loading
        self._validate_working_directory_display()
    
    def _on_working_dir_click(self, event=None):
        """Handle click on working directory entry - open folder selection"""
        # Prevent multiple dialogs
        if self._dialog_open:
            return
        
        self._dialog_open = True
        try:
            self._select_working_directory()
        finally:
            self._dialog_open = False
    
    def _select_working_directory(self):
        """Select and save working directory"""
        current_dir = self.working_dir_var.get() or "."
        directory = filedialog.askdirectory(
            title="Pilih Direktori Kerja",
            initialdir=current_dir
        )
        
        if directory:
            # Update the entry field
            self.working_dir_var.set(directory)
            
            # Validate before saving
            if not self.config_manager.validate_working_directory(directory):
                messagebox.showwarning("Peringatan", 
                    f"Direktori yang dipilih tidak valid atau tidak dapat diakses:\n{directory}")
                return
            
            # Automatically save the setting
            if self.config_manager.set_working_directory(directory):
                if self.on_change_callback:
                    self.on_change_callback()
                self._validate_working_directory_display()
            else:
                messagebox.showerror("Error", "Gagal menyimpan direktori kerja.")
    
    def _on_working_dir_changed(self, *args):
        """Handle working directory variable change"""
        # Update validation display when the variable changes
        self._validate_working_directory_display()
    
    def _validate_working_directory_display(self):
        """Update the visual validation display for working directory"""
        try:
            is_valid, message = self.config_manager.validate_current_working_directory()
            
            if is_valid:
                self.working_dir_status_label.config(text="✓", foreground="green")
                self.working_dir_message_label.config(text=message, foreground="green")
            else:
                # Check if directory is not configured at all
                configured_dir = self.config_manager.get_working_directory()
                if not configured_dir:
                    self.working_dir_status_label.config(text="❗", foreground="red")
                    self.working_dir_message_label.config(
                        text="DIPERLUKAN: Pilih direktori kerja untuk menjalankan perintah", 
                        foreground="red"
                    )
                else:
                    self.working_dir_status_label.config(text="⚠", foreground="orange")
                    self.working_dir_message_label.config(text=message, foreground="orange")
                
        except Exception as e:
            self.working_dir_status_label.config(text="✗", foreground="red")
            self.working_dir_message_label.config(text=f"Validation error: {e}", foreground="red")
    
    def cleanup(self):
        """Cleanup resources"""
        pass


class CommandFormDialog:
    """Modal dialog for adding/editing commands"""
    
    def __init__(self, parent, config_manager, title, command=None):
        self.config_manager = config_manager
        self.command = command
        self.result = False
        
        # Create dialog
        self.dialog = tk.Toplevel(parent)
        self.dialog.title(title)
        self.dialog.geometry("600x500")
        self.dialog.resizable(False, False)
        
        # Make modal
        self.dialog.transient(parent)
        self.dialog.grab_set()
        
        # Center dialog
        self._center_dialog(parent)
        
        # Setup GUI
        self._setup_gui()
        
        # Load data if editing
        if self.command:
            self._load_command_data()
        
        # Handle close
        self.dialog.protocol("WM_DELETE_WINDOW", self._on_cancel)
        
        # Focus first field
        self.id_entry.focus_set()
        
        # Wait for dialog
        parent.wait_window(self.dialog)
    
    def _center_dialog(self, parent):
        """Center dialog on parent"""
        self.dialog.update_idletasks()
        x = parent.winfo_x() + (parent.winfo_width() // 2) - (self.dialog.winfo_width() // 2)
        y = parent.winfo_y() + (parent.winfo_height() // 2) - (self.dialog.winfo_height() // 2)
        self.dialog.geometry(f"+{x}+{y}")
    
    def _setup_gui(self):
        """Setup dialog GUI"""
        main_frame = ttk.Frame(self.dialog, padding="20")
        main_frame.pack(fill=tk.BOTH, expand=True)
        
        # Form fields
        self._create_form_fields(main_frame)
        
        # Buttons
        self._create_buttons(main_frame)
    
    def _create_form_fields(self, parent):
        """Create form fields"""
        # ID field
        ttk.Label(parent, text="ID:").grid(row=0, column=0, sticky=tk.W, pady=(0, 5))
        self.id_entry = ttk.Entry(parent, width=50)
        self.id_entry.grid(row=0, column=1, sticky=(tk.W, tk.E), pady=(0, 5))
        
        ttk.Label(parent, text="Unique identifier (lowercase, dashes allowed)", 
                 font=('TkDefaultFont', 8), foreground='gray').grid(row=1, column=1, sticky=tk.W, pady=(0, 10))
        
        # Name field
        ttk.Label(parent, text="Name:").grid(row=2, column=0, sticky=tk.W, pady=(0, 5))
        self.name_entry = ttk.Entry(parent, width=50)
        self.name_entry.grid(row=2, column=1, sticky=(tk.W, tk.E), pady=(0, 5))
        
        ttk.Label(parent, text="Display name for the command", 
                 font=('TkDefaultFont', 8), foreground='gray').grid(row=3, column=1, sticky=tk.W, pady=(0, 10))
        
        # Command field
        ttk.Label(parent, text="Command:").grid(row=4, column=0, sticky=(tk.W, tk.N), pady=(0, 5))
        self.command_text = tk.Text(parent, height=3, width=50, wrap=tk.WORD)
        self.command_text.grid(row=4, column=1, sticky=(tk.W, tk.E), pady=(0, 5))
        
        ttk.Label(parent, text="Full artisan command (e.g., php artisan queue:work)", 
                 font=('TkDefaultFont', 8), foreground='gray').grid(row=5, column=1, sticky=tk.W, pady=(0, 10))
        
        # Description field
        ttk.Label(parent, text="Description:").grid(row=6, column=0, sticky=(tk.W, tk.N), pady=(0, 5))
        self.description_text = tk.Text(parent, height=3, width=50, wrap=tk.WORD)
        self.description_text.grid(row=6, column=1, sticky=(tk.W, tk.E), pady=(0, 5))
        
        ttk.Label(parent, text="Optional description of what this command does", 
                 font=('TkDefaultFont', 8), foreground='gray').grid(row=7, column=1, sticky=tk.W, pady=(0, 10))
        
        # Enabled checkbox
        self.enabled_var = tk.BooleanVar(value=True)
        self.enabled_check = ttk.Checkbutton(parent, text="Enabled", variable=self.enabled_var)
        self.enabled_check.grid(row=8, column=1, sticky=tk.W, pady=(0, 20))
        
        # Configure grid
        parent.grid_columnconfigure(1, weight=1)
    
    def _create_buttons(self, parent):
        """Create dialog buttons"""
        buttons_frame = ttk.Frame(parent)
        buttons_frame.grid(row=9, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(10, 0))
        
        ttk.Button(buttons_frame, text="Save", 
                  command=self._on_save).pack(side=tk.RIGHT, padx=(10, 0))
        ttk.Button(buttons_frame, text="Cancel", 
                  command=self._on_cancel).pack(side=tk.RIGHT)
    
    def _load_command_data(self):
        """Load existing command data"""
        if not self.command:
            return
        
        self.id_entry.insert(0, self.command.get('id', ''))
        self.id_entry.config(state='readonly')  # Don't allow ID changes when editing
        
        self.name_entry.insert(0, self.command.get('name', ''))
        self.command_text.insert('1.0', self.command.get('command', ''))
        self.description_text.insert('1.0', self.command.get('description', ''))
        self.enabled_var.set(self.command.get('enabled', True))
    
    def _validate_form(self):
        """Validate form data"""
        command_id = self.id_entry.get().strip()
        name = self.name_entry.get().strip()
        command = self.command_text.get('1.0', tk.END).strip()
        
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
            self.command_text.focus_set()
            return False
        
        # Validate ID format
        if not re.match(r'^[a-z0-9\-_]+$', command_id):
            messagebox.showerror("Validation Error", 
                               "ID must contain only lowercase letters, numbers, hyphens, and underscores.")
            self.id_entry.focus_set()
            return False
        
        # Check for duplicate ID (only when adding)
        if not self.command:
            existing = self.config_manager.get_command(command_id)
            if existing:
                messagebox.showerror("Validation Error", 
                                   f"A command with ID '{command_id}' already exists.")
                self.id_entry.focus_set()
                return False
        
        return True
    
    def _on_save(self):
        """Handle save"""
        if not self._validate_form():
            return
        
        command_id = self.id_entry.get().strip()
        name = self.name_entry.get().strip()
        command = self.command_text.get('1.0', tk.END).strip()
        description = self.description_text.get('1.0', tk.END).strip()
        enabled = self.enabled_var.get()
        
        command_data = {
            'id': command_id,
            'name': name,
            'command': command,
            'description': description,
            'enabled': enabled
        }
        
        try:
            if self.command:  # Editing
                success = self.config_manager.update_command(command_id, command_data)
                action = "updated"
            else:  # Adding
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
        """Handle cancel"""
        self.result = False
        self.dialog.destroy()