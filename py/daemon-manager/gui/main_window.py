"""
Main GUI Window for Laravel Daemon Manager
Tabbed interface with overview, command tabs, and configuration
"""

import tkinter as tk
from tkinter import ttk, messagebox
import sys
from pathlib import Path

# Add parent directory to path
sys.path.append(str(Path(__file__).parent.parent))

from gui.overview_tab import OverviewTab
from gui.commands_tab import CommandsTab
from gui.config_tab import ConfigTab
from gui.logs_tab import LogsTab


class MainWindow:
    def __init__(self, root, config_manager, command_manager, app_instance=None, on_close=None):
        self.root = root
        self.config_manager = config_manager
        self.command_manager = command_manager
        self.app_instance = app_instance
        self.on_close_callback = on_close
        
        # Tab management
        self.tab_widgets = {}   # tab_id -> widget instance
        
        # Setup window
        self._setup_window()
        
        # Setup GUI
        self._setup_gui()
        
        # Load initial tabs
        self._load_tabs()
        
        # Start auto-refresh
        self._auto_refresh()
    
    def _setup_window(self):
        """Setup main window properties"""
        self.root.title("Pengelola Daemon Caldera")
        self.root.geometry("1200x800")
        self.root.minsize(900, 600)
        
        # Set consistent beige background
        self.root.configure(bg='#DCDAD5')
        
        # Handle window close
        self.root.protocol("WM_DELETE_WINDOW", self._on_window_close)
        
        # Configure style for uniform beige theme
        style = ttk.Style()
        style.theme_use('clam')
        
        # Define beige color scheme
        beige_bg = '#DCDAD5'
        light_beige = '#E8E6E1'
        dark_beige = '#D0CEC9'
        
        # Configure main components with beige theme
        style.configure('TNotebook', 
                       background=beige_bg,
                       borderwidth=0,
                       focuscolor='none')
        

        
        style.map('TNotebook.Tab',
                 background=[('selected', beige_bg),
                           ('active', dark_beige)],
                 foreground=[('selected', '#000000'),
                           ('active', '#000000')])
        
        # Configure frames with beige background
        style.configure('TFrame', background=beige_bg)
        style.configure('TLabelFrame', background=beige_bg)
        style.configure('TLabelFrame.Label', background=beige_bg)
        
        # Configure other components
        style.configure('TLabel', background=beige_bg)
        style.configure('TButton', padding=[10, 5])
        style.configure('Heading.TLabel', 
                       background=beige_bg,
                       font=('TkDefaultFont', 12, 'bold'))
    
    def _setup_gui(self):
        """Setup the main GUI components"""
        # Main frame
        main_frame = ttk.Frame(self.root)
        main_frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        # Title and toolbar
        self._create_toolbar(main_frame)
        
        # Create notebook for tabs
        self.notebook = ttk.Notebook(main_frame)
        self.notebook.pack(fill=tk.BOTH, expand=True, pady=(10, 0))
        
        # Bind tab selection event
        self.notebook.bind("<<NotebookTabChanged>>", self._on_tab_changed)
    
    def _create_toolbar(self, parent):
        """Create toolbar with title and controls"""
        toolbar_frame = ttk.Frame(parent)
        toolbar_frame.pack(fill=tk.X, pady=(0, 10))
        
        # Title
        title_frame = ttk.Frame(toolbar_frame)
        title_frame.pack(side=tk.LEFT, fill=tk.X, expand=True)
        
        title_label = ttk.Label(title_frame, text="Pengelola Daemon Caldera", 
                               font=('TkDefaultFont', 16, 'bold'))
        title_label.pack(side=tk.LEFT)
    
        
        # Control buttons
        controls_frame = ttk.Frame(toolbar_frame)
        controls_frame.pack(side=tk.RIGHT)
        
        ttk.Button(controls_frame, text="Tentang", 
                  command=self._show_about_dialog).pack(side=tk.RIGHT, padx=(5, 0))
        
        ttk.Button(controls_frame, text="Refresh", 
                  command=self._refresh_all_tabs).pack(side=tk.RIGHT, padx=(5, 0))
    
    def _load_tabs(self):
        """Load all tabs"""
        # Overview tab
        self._add_overview_tab()
        
        # Commands tab
        self._add_commands_tab()
        
        # Configuration tab
        self._add_config_tab()
        
        # Logs tab
        self._add_logs_tab()
    
    def _add_overview_tab(self):
        """Add overview/dashboard tab"""
        overview_frame = ttk.Frame(self.notebook)
        self.notebook.add(overview_frame, text="📊 Ringkasan")
        
        overview_tab = OverviewTab(overview_frame, self.config_manager, self.command_manager, self.app_instance)
        self.tab_widgets['overview'] = overview_tab
    
    def _add_commands_tab(self):
        """Add commands tab with vertical sub-tabs"""
        commands_frame = ttk.Frame(self.notebook)
        self.notebook.add(commands_frame, text="🔧 Perintah")
        
        commands_tab = CommandsTab(commands_frame, self.config_manager, self.command_manager)
        self.tab_widgets['commands'] = commands_tab
    
    def _add_config_tab(self):
        """Add configuration tab"""
        config_frame = ttk.Frame(self.notebook)
        self.notebook.add(config_frame, text="⚙️ Konfigurasi")
        
        config_tab = ConfigTab(config_frame, self.config_manager, self.command_manager, 
                              self._on_commands_changed)
        self.tab_widgets['config'] = config_tab
    
    def _add_logs_tab(self):
        """Add centralized logs tab"""
        logs_frame = ttk.Frame(self.notebook)
        self.notebook.add(logs_frame, text="📄 Log")
        
        logs_tab = LogsTab(logs_frame, self.config_manager, self.command_manager)
        self.tab_widgets['logs'] = logs_tab
    
    def _on_commands_changed(self):
        """Called when commands configuration changes"""
        # Refresh commands tab to update vertical list
        if 'commands' in self.tab_widgets:
            self.tab_widgets['commands'].refresh_command_list()
        self._refresh_all_tabs()
    
    def _on_tab_changed(self, event):
        """Handle tab selection change"""
        selected_tab = event.widget.select()
        tab_text = event.widget.tab(selected_tab, "text")
        
        # Refresh the selected tab
        if "Ringkasan" in tab_text and 'overview' in self.tab_widgets:
            self.tab_widgets['overview'].refresh()
        elif "Perintah" in tab_text and 'commands' in self.tab_widgets:
            self.tab_widgets['commands'].refresh()
        elif "Log" in tab_text and 'logs' in self.tab_widgets:
            self.tab_widgets['logs'].refresh()
    
    def _refresh_all_tabs(self):
        """Refresh all tabs"""
        # Refresh overview
        if 'overview' in self.tab_widgets:
            self.tab_widgets['overview'].refresh()
        
        # Refresh commands tab
        if 'commands' in self.tab_widgets:
            self.tab_widgets['commands'].refresh()
        
        # Refresh logs tab
        if 'logs' in self.tab_widgets:
            self.tab_widgets['logs'].refresh()
    
    def _auto_refresh(self):
        """Auto-refresh tabs periodically"""
        try:
            # Refresh overview and commands tabs every 5 seconds
            if 'overview' in self.tab_widgets:
                self.tab_widgets['overview'].refresh()
            
            if 'commands' in self.tab_widgets:
                self.tab_widgets['commands'].refresh()
            
        except Exception as e:
            print(f"Auto-refresh error: {e}")
        
        # Schedule next refresh
        self.root.after(5000, self._auto_refresh)  # 5 seconds
    
    
    def _on_window_close(self):
        """Handle window close event"""
        if self.on_close_callback:
            # Let the callback handle the close (which includes confirmation)
            result = self.on_close_callback()
            if result is False:
                return  # Prevent default close
        
        # Default close behavior
        self.cleanup()
        self.root.quit()
    
    def cleanup(self):
        """Cleanup resources"""
        # Cleanup all tabs
        for tab_widget in self.tab_widgets.values():
            if hasattr(tab_widget, 'cleanup'):
                tab_widget.cleanup()
    
    def _show_about_dialog(self):
        """Show about dialog with author information"""
        # Create about dialog
        about_dialog = tk.Toplevel(self.root)
        about_dialog.title("Tentang")
        about_dialog.geometry("500x400")
        about_dialog.resizable(False, False)
        about_dialog.configure(bg='#DCDAD5')
        
        # Make modal
        about_dialog.transient(self.root)
        about_dialog.grab_set()
        
        # Center dialog
        about_dialog.update_idletasks()
        x = self.root.winfo_x() + (self.root.winfo_width() // 2) - (about_dialog.winfo_width() // 2)
        y = self.root.winfo_y() + (self.root.winfo_height() // 2) - (about_dialog.winfo_height() // 2)
        about_dialog.geometry(f"+{x}+{y}")
        
        # Main frame
        main_frame = ttk.Frame(about_dialog, padding="30")
        main_frame.pack(fill=tk.BOTH, expand=True)
        
        # Author information
        author_frame = ttk.LabelFrame(main_frame, text="Informasi Pengembang", padding="20")
        author_frame.pack(fill=tk.X, pady=(0, 20))
        
        ttk.Label(author_frame, text="Nama:", font=('TkDefaultFont', 10, 'bold')).pack(anchor=tk.W)
        ttk.Label(author_frame, text="Andi Permana", font=('TkDefaultFont', 10)).pack(anchor=tk.W, pady=(0, 10))
        
        ttk.Label(author_frame, text="Departemen:", font=('TkDefaultFont', 10, 'bold')).pack(anchor=tk.W)
        ttk.Label(author_frame, text="MM untuk PT. TKG Taekwang Indonesia", font=('TkDefaultFont', 10)).pack(anchor=tk.W, pady=(0, 10))
        
        # Links frame
        links_frame = ttk.Frame(author_frame)
        links_frame.pack(fill=tk.X, pady=(10, 0))
        
        ttk.Label(links_frame, text="LinkedIn:", font=('TkDefaultFont', 10, 'bold')).pack(anchor=tk.W)
        linkedin_label = ttk.Label(links_frame, text="https://www.linkedin.com/in/andimendunia/", 
                                  font=('TkDefaultFont', 10), foreground='blue', cursor='hand2')
        linkedin_label.pack(anchor=tk.W, pady=(0, 5))
        
        ttk.Label(links_frame, text="GitHub:", font=('TkDefaultFont', 10, 'bold')).pack(anchor=tk.W)
        github_label = ttk.Label(links_frame, text="https://github.com/andimendunia", 
                                font=('TkDefaultFont', 10), foreground='blue', cursor='hand2')
        github_label.pack(anchor=tk.W, pady=(0, 15))
        
        # City and year at bottom
        city_year_label = ttk.Label(links_frame, text="Subang, 2025", 
                                   font=('TkDefaultFont', 9), foreground='gray')
        city_year_label.pack(anchor=tk.W)
        
        # Bind link clicks
        def open_linkedin(event):
            import webbrowser
            webbrowser.open("https://www.linkedin.com/in/andimendunia/")
        
        def open_github(event):
            import webbrowser
            webbrowser.open("https://github.com/andimendunia")
        
        linkedin_label.bind("<Button-1>", open_linkedin)
        github_label.bind("<Button-1>", open_github)
        
        # Close button
        ttk.Button(main_frame, text="Tutup", 
                  command=about_dialog.destroy).pack(padx=(10, 0))
    
    def show(self):
        """Show the window (for compatibility)"""
        self.root.deiconify()
        self.root.lift()