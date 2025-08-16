"""
Commands Tab Widget
Main commands tab with vertical sub-tabs for each command
"""

import tkinter as tk
from tkinter import ttk
import sys
from pathlib import Path

# Add parent directory to path
sys.path.append(str(Path(__file__).parent.parent))

from gui.command_panel import CommandPanel


class CommandsTab:
    def __init__(self, parent_frame, config_manager, command_manager):
        self.frame = parent_frame
        self.config_manager = config_manager
        self.command_manager = command_manager
        
        # State management
        self.command_panels = {}  # command_id -> CommandPanel
        self.selected_command = None
        
        # GUI elements
        self.commands_listbox = None
        self.content_frame = None
        self.no_commands_frame = None
        self.current_panel = None
        
        # Setup GUI
        self._setup_gui()
        
        # Load initial commands
        self.refresh_command_list()
    
    def _setup_gui(self):
        """Setup the commands tab GUI"""
        # Main container
        main_container = ttk.Frame(self.frame)
        main_container.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        # Create horizontal paned window for left/right layout
        paned_window = ttk.PanedWindow(main_container, orient=tk.HORIZONTAL)
        paned_window.pack(fill=tk.BOTH, expand=True)
        
        # Left panel - Command list
        self._create_left_panel(paned_window)
        
        # Right panel - Command details
        self._create_right_panel(paned_window)
        
        # Add panels to paned window
        paned_window.add(self.left_panel, weight=0)
        paned_window.add(self.right_panel, weight=1)
        
        # Set initial sash position (left panel width)
        paned_window.sashpos(0, 250)
    
    def _create_left_panel(self, parent):
        """Create left panel with vertical command list"""
        self.left_panel = ttk.Frame(parent)
        
        # Title
        title_frame = ttk.Frame(self.left_panel)
        title_frame.pack(fill=tk.X, padx=10, pady=(10, 5))
        
        # Commands listbox with scrollbar
        listbox_frame = ttk.Frame(self.left_panel)
        listbox_frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=(0, 10))
        
        # Create listbox
        self.commands_listbox = tk.Listbox(
            listbox_frame,
            font=('TkDefaultFont', 9),
            selectmode=tk.SINGLE,
            activestyle='none',
            highlightthickness=0,
            selectbackground='#0078d4',
            selectforeground='white'
        )
        
        # Scrollbar for listbox
        scrollbar = ttk.Scrollbar(listbox_frame, orient=tk.VERTICAL, command=self.commands_listbox.yview)
        self.commands_listbox.configure(yscrollcommand=scrollbar.set)
        
        # Pack listbox and scrollbar
        self.commands_listbox.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        
        # Bind selection event
        self.commands_listbox.bind('<<ListboxSelect>>', self._on_command_selected)
        
        # Refresh button
        refresh_frame = ttk.Frame(self.left_panel)
        refresh_frame.pack(fill=tk.X, padx=10, pady=(0, 10))
        
        ttk.Button(refresh_frame, text="🔄 Refresh", 
                  command=self.refresh_command_list).pack(fill=tk.X)
    
    def _create_right_panel(self, parent):
        """Create right panel for command details"""
        self.right_panel = ttk.Frame(parent)
        
        # Content frame (where command panels will be shown)
        self.content_frame = ttk.Frame(self.right_panel)
        self.content_frame.pack(fill=tk.BOTH, expand=True)
        
        # Create "no commands" frame
        self._create_no_commands_frame()
        
        # Initially show no commands frame
        self._show_no_commands()
    
    def _create_no_commands_frame(self):
        """Create frame to show when no commands are available"""
        self.no_commands_frame = ttk.Frame(self.content_frame)
        
        # Center the content
        center_frame = ttk.Frame(self.no_commands_frame)
        center_frame.place(relx=0.5, rely=0.5, anchor=tk.CENTER)
        
        # Icon and message
        icon_label = ttk.Label(center_frame, text="🔧", font=('TkDefaultFont', 48))
        icon_label.pack(pady=(0, 20))
        
        title_label = ttk.Label(center_frame, text="Tidak Ada Perintah Tersedia", 
                               font=('TkDefaultFont', 16, 'bold'))
        title_label.pack(pady=(0, 10))
        
        desc_label = ttk.Label(center_frame, 
                              text="Konfigurasi perintah di tab Konfigurasi\nuntuk melihatnya di sini.",
                              font=('TkDefaultFont', 10), foreground='gray',
                              justify=tk.CENTER)
        desc_label.pack()
    
    def _show_no_commands(self):
        """Show the no commands frame"""
        # Hide current panel if any
        if self.current_panel:
            self.current_panel.pack_forget()
            self.current_panel = None
        
        # Show no commands frame
        self.no_commands_frame.pack(fill=tk.BOTH, expand=True)
    
    def _show_command_panel(self, command_id):
        """Show the panel for a specific command"""
        # Hide no commands frame
        self.no_commands_frame.pack_forget()
        
        # Hide current panel if any
        if self.current_panel:
            self.current_panel.pack_forget()
        
        # Get or create command panel
        if command_id not in self.command_panels:
            command = self.config_manager.get_command(command_id)
            if command:
                panel = CommandPanel(self.content_frame, command, self.command_manager)
                self.command_panels[command_id] = panel
        
        # Show the command panel
        if command_id in self.command_panels:
            panel = self.command_panels[command_id]
            panel.pack(fill=tk.BOTH, expand=True)
            self.current_panel = panel
            
            # Refresh the panel
            panel.refresh()
    
    def _on_command_selected(self, event):
        """Handle command selection from listbox"""
        selection = self.commands_listbox.curselection()
        if not selection:
            return
        
        # Get selected command ID
        index = selection[0]
        command_data = self.commands_listbox.get(index)
        
        # Extract command name from the display text
        command_name = command_data
        
        # Find command by name
        commands = self.config_manager.get_commands()
        for command in commands:
            if command['name'] == command_name:
                self.selected_command = command['id']
                self._show_command_panel(command['id'])
                break
    
    def refresh_command_list(self):
        """Refresh the command list"""
        try:
            # Clear current listbox
            self.commands_listbox.delete(0, tk.END)
            
            # Get commands
            commands = self.config_manager.get_commands()
            
            if not commands:
                self._show_no_commands()
                return
            
            # Populate listbox with commands
            for command in commands:
                command_id = command['id']
                command_name = command['name']
                
                # Get status for icon
                status = self.command_manager.get_command_status(command_id)
                
                # Create display text without status
                display_text = command_name
                
                # Add to listbox
                self.commands_listbox.insert(tk.END, display_text)
            
            # Select first command if none selected and we have commands
            if not self.selected_command and commands:
                self.commands_listbox.selection_set(0)
                self.selected_command = commands[0]['id']
                self._show_command_panel(self.selected_command)
            
            # Update existing panels
            for command_id, panel in self.command_panels.items():
                command = self.config_manager.get_command(command_id)
                if command:
                    panel.update_command_info(command)
                else:
                    # Command was deleted, remove panel
                    panel.cleanup()
                    del self.command_panels[command_id]
            
            # Remove panels for deleted commands
            current_command_ids = {cmd['id'] for cmd in commands}
            panels_to_remove = []
            for command_id in self.command_panels:
                if command_id not in current_command_ids:
                    panels_to_remove.append(command_id)
            
            for command_id in panels_to_remove:
                self.command_panels[command_id].cleanup()
                del self.command_panels[command_id]
                
                # If the deleted command was selected, clear selection
                if self.selected_command == command_id:
                    self.selected_command = None
                    if commands:
                        # Select first available command
                        self.commands_listbox.selection_set(0)
                        self.selected_command = commands[0]['id']
                        self._show_command_panel(self.selected_command)
                    else:
                        self._show_no_commands()
            
        except Exception as e:
            print(f"Error refreshing command list: {e}")
    
    def refresh(self):
        """Refresh all data"""
        try:
            # Refresh command list (this will update icons)
            self.refresh_command_list()
            
            # Refresh current panel if any
            if self.current_panel:
                self.current_panel.refresh()
        except Exception as e:
            print(f"Error refreshing commands tab: {e}")
    
    def cleanup(self):
        """Cleanup resources"""
        # Cleanup all command panels
        for panel in self.command_panels.values():
            panel.cleanup()
        self.command_panels.clear()