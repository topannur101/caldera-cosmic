import sys
from PyQt6.QtWidgets import (QMainWindow, QWidget, QVBoxLayout, QPushButton, QTextEdit, 
                             QLabel, QTabWidget, QHBoxLayout, QGroupBox, QFormLayout,
                             QSplitter, QGridLayout, QFrame)
from PyQt6.QtCore import QTimer, pyqtSlot, Qt
from PyQt6.QtGui import QPixmap, QColor

from config_manager import ConfigManager
from arduino_manager import ArduinoManager
from camera_manager import CameraManager
from server_manager import ServerManager
from gui_config_editor import ConfigEditor

class StatusIndicator(QFrame):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.setFixedSize(20, 20)
        self.setStatus(False)

    def setStatus(self, is_active):
        color = QColor(0, 255, 0) if is_active else QColor(255, 0, 0)
        self.setStyleSheet(f"background-color: {color.name()}; border-radius: 10px;")

class MainWindow(QMainWindow):
    def __init__(self):
        super().__init__()
        self.setWindowTitle("OMV Worker")
        self.setGeometry(100, 100, 1200, 800)

        self.config_manager = ConfigManager()
        self.arduino_manager = ArduinoManager(self.config_manager.get_config())
        self.camera_manager = CameraManager(self.config_manager.get_config())
        self.server_manager = ServerManager()

        self.init_ui()

        # Setup timer for Arduino data
        self.arduino_timer = QTimer()
        self.arduino_timer.timeout.connect(self.update_arduino_data)

        # Auto-start server if configured
        if self.config_manager.get_config('app')['start_on_startup']:
            self.toggle_server()

        # Auto-connect Arduino if configured
        if self.config_manager.get_config('serial')['connect_on_startup']:
            self.toggle_arduino_connection()

    def init_ui(self):
        central_widget = QWidget()
        main_layout = QHBoxLayout()
        central_widget.setLayout(main_layout)
        self.setCentralWidget(central_widget)

        # Create vertical tab widget
        self.tab_widget = QTabWidget()
        self.tab_widget.setTabPosition(QTabWidget.TabPosition.West)
        main_layout.addWidget(self.tab_widget)

        self.create_home_tab()
        self.create_camera_tab()
        self.create_data_tab()
        self.create_configurations_tab()

    def create_home_tab(self):
        tab = QWidget()
        layout = QVBoxLayout()
        
        # Top half: Server and Arduino status
        top_widget = QWidget()
        top_layout = QHBoxLayout()
        top_widget.setLayout(top_layout)
        
        # Server status
        server_group = QGroupBox("Server Status")
        server_layout = QHBoxLayout()
        self.server_status_indicator = StatusIndicator()
        self.server_status = QLabel("Not running")
        self.server_button = QPushButton("Start Server")
        self.server_button.clicked.connect(self.toggle_server)
        server_layout.addWidget(self.server_status_indicator)
        server_layout.addWidget(self.server_status)
        server_layout.addWidget(self.server_button)
        server_group.setLayout(server_layout)
        
        # Arduino status
        arduino_group = QGroupBox("Arduino Status")
        arduino_layout = QHBoxLayout()
        self.arduino_status_indicator = StatusIndicator()
        self.arduino_status = QLabel("Not connected")
        self.arduino_connect_button = QPushButton("Connect Arduino")
        self.arduino_connect_button.clicked.connect(self.toggle_arduino_connection)
        arduino_layout.addWidget(self.arduino_status_indicator)
        arduino_layout.addWidget(self.arduino_status)
        arduino_layout.addWidget(self.arduino_connect_button)
        arduino_group.setLayout(arduino_layout)
        
        top_layout.addWidget(server_group)
        top_layout.addWidget(arduino_group)
        
        # Bottom half: Log view
        self.log_display = QTextEdit()
        self.log_display.setReadOnly(True)
        
        # Add top and bottom to main layout
        layout.addWidget(top_widget)
        layout.addWidget(self.log_display)
        
        tab.setLayout(layout)
        self.tab_widget.addTab(tab, "Home")

    def create_camera_tab(self):
        tab = QWidget()
        layout = QVBoxLayout()
        
        self.capture_button = QPushButton("Take Picture")
        self.capture_button.clicked.connect(self.capture_photo)
        layout.addWidget(self.capture_button)
        
        self.photo_label = QLabel("No photo taken")
        self.photo_label.setAlignment(Qt.AlignmentFlag.AlignCenter)
        self.photo_label.setMinimumSize(640, 480)
        layout.addWidget(self.photo_label)
        
        tab.setLayout(layout)
        self.tab_widget.addTab(tab, "Camera")

    def create_data_tab(self):
        tab = QWidget()
        layout = QVBoxLayout()
        
        label = QLabel("Data tab content will be added here.")
        layout.addWidget(label)
        
        tab.setLayout(layout)
        self.tab_widget.addTab(tab, "Data")

    def create_configurations_tab(self):
        tab = QWidget()
        layout = QVBoxLayout()
        
        grid_layout = QGridLayout()
        self.config_editors = {}
        sections = ['app', 'serial', 'capture', 'data_handling']
        for i, section in enumerate(sections):
            group = QGroupBox(section.capitalize() + " Configuration")
            group_layout = QVBoxLayout()
            config_editor = ConfigEditor(self.config_manager, section)
            self.config_editors[section] = config_editor
            group_layout.addWidget(config_editor)
            group.setLayout(group_layout)
            grid_layout.addWidget(group, i // 2, i % 2)
        
        layout.addLayout(grid_layout)
        
        save_button = QPushButton("Save All Configurations")
        save_button.clicked.connect(self.save_all_configurations)
        layout.addWidget(save_button)
        
        tab.setLayout(layout)
        self.tab_widget.addTab(tab, "Configurations")

    @pyqtSlot()
    def toggle_server(self):
        if not self.server_manager.is_running():
            if self.server_manager.start_server():
                self.server_status.setText("Running")
                self.server_button.setText("Stop Server")
                self.server_status_indicator.setStatus(True)
                self.log_display.append("Server started")
        else:
            if self.server_manager.stop_server():
                self.server_status.setText("Not running")
                self.server_button.setText("Start Server")
                self.server_status_indicator.setStatus(False)
                self.log_display.append("Server stopped")

    @pyqtSlot()
    def toggle_arduino_connection(self):
        if not self.arduino_manager.connected:
            if self.arduino_manager.connect():
                self.arduino_status.setText("Connected")
                self.arduino_connect_button.setText("Disconnect Arduino")
                self.arduino_status_indicator.setStatus(True)
                self.arduino_timer.start(self.config_manager.get_config('serial')['interval'])
                self.log_display.append("Arduino connected")
            else:
                self.log_display.append("Failed to connect to Arduino")
        else:
            self.arduino_manager.disconnect()
            self.arduino_timer.stop()
            self.arduino_status.setText("Not connected")
            self.arduino_connect_button.setText("Connect Arduino")
            self.arduino_status_indicator.setStatus(False)
            self.log_display.append("Arduino disconnected")

    @pyqtSlot()
    def update_arduino_data(self):
        data = self.arduino_manager.read_data()
        if data is not None:
            self.arduino_status.setText(f"Connected - Data: {data}")
            self.log_display.append(f"Received data: {data}")
        else:
            self.arduino_status.setText("Connected - No data")

    @pyqtSlot()
    def capture_photo(self):
        pixmap = self.camera_manager.capture_photo()
        if pixmap:
            scaled_pixmap = self.camera_manager.scale_image(pixmap, self.photo_label.size())
            self.photo_label.setPixmap(scaled_pixmap)
            self.photo_label.setStyleSheet("")  # Remove background color
            self.log_display.append("Photo captured")
        else:
            self.log_display.append("Failed to capture photo")

    @pyqtSlot()
    def save_all_configurations(self):
        for section, editor in self.config_editors.items():
            result = editor.save_changes()
            self.log_display.append(result)
        self.log_display.append("All configurations saved")

    def resizeEvent(self, event):
        super().resizeEvent(event)
        if hasattr(self, 'photo_label') and self.photo_label.pixmap():
            scaled_pixmap = self.camera_manager.scale_image(self.photo_label.pixmap(), self.photo_label.size())
            self.photo_label.setPixmap(scaled_pixmap)