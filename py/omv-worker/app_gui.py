import sys
import json
import subprocess
from PyQt6.QtWidgets import (QApplication, QMainWindow, QWidget, QVBoxLayout, 
                             QPushButton, QTextEdit, QLabel, QTabWidget, 
                             QLineEdit, QFormLayout, QSpinBox, QCheckBox, 
                             QHBoxLayout, QGroupBox, QScrollArea, QSplitter,
                             QSizePolicy)
from PyQt6.QtCore import QTimer, pyqtSlot, Qt
from PyQt6.QtGui import QPixmap, QImage
import serial
import cv2
import requests
import time

class ConfigEditor(QWidget):
    def __init__(self, config, section):
        super().__init__()
        self.config = config
        self.section = section
        self.init_ui()

    def init_ui(self):
        layout = QFormLayout()
        self.widgets = {}

        for key, value in self.config[self.section].items():
            if isinstance(value, bool):
                widget = QCheckBox()
                widget.setChecked(value)
            elif isinstance(value, int):
                widget = QSpinBox()
                widget.setRange(-1000000, 1000000)
                widget.setValue(value)
            else:
                widget = QLineEdit(str(value))

            layout.addRow(key, widget)
            self.widgets[key] = widget

        save_button = QPushButton("Perbarui")
        save_button.clicked.connect(self.save_changes)
        layout.addRow(save_button)

        self.setLayout(layout)

    def save_changes(self):
        for key, widget in self.widgets.items():
            if isinstance(widget, QCheckBox):
                self.config[self.section][key] = widget.isChecked()
            elif isinstance(widget, QSpinBox):
                self.config[self.section][key] = widget.value()
            else:
                self.config[self.section][key] = widget.text()

        with open('config.json', 'w') as config_file:
            json.dump(self.config, config_file, indent=4)

        print(f"Konfigurasi untuk {self.section} disimpan")

class MainWindow(QMainWindow):
    def __init__(self):
        super().__init__()
        self.setWindowTitle("OMV Worker")
        self.setGeometry(100, 100, 1000, 600)

        # Load configuration
        with open('config.json', 'r') as config_file:
            self.config = json.load(config_file)

        # Ensure new Arduino config options are present
        if 'auto_connect' not in self.config['serial']:
            self.config['serial']['auto_connect'] = False
        if 'interval' not in self.config['serial']:
            self.config['serial']['interval'] = 2000  # 2 seconds default
        self.save_config()

        # Ensure 'auto_start_server' is in the config
        if 'auto_start_server' not in self.config['app']:
            self.config['app']['auto_start_server'] = False
            self.save_config()

        # Create central widget and main layout
        central_widget = QWidget()
        main_layout = QVBoxLayout()
        central_widget.setLayout(main_layout)
        self.setCentralWidget(central_widget)

        # Create splitter for tab widget and log display
        self.splitter = QSplitter(Qt.Orientation.Vertical)
        main_layout.addWidget(self.splitter)

        # Create tab widget
        self.tab_widget = QTabWidget()
        self.splitter.addWidget(self.tab_widget)

        # Create tabs
        self.create_local_server_tab()
        self.create_arduino_tab()
        self.create_camera_tab()
        self.create_data_tab()

        # Log display (shared between tabs)
        self.log_display = QTextEdit()
        self.log_display.setReadOnly(True)
        self.splitter.addWidget(self.log_display)

        # Set initial sizes for splitter
        self.splitter.setSizes([700, 300])
        self.splitter.setCollapsible(0, False)

        # Arduino connection and data
        self.arduino_connected = False
        self.arduino_data = 0

        # Setup timer for Arduino data
        self.arduino_timer = QTimer()
        self.arduino_timer.timeout.connect(self.update_arduino_data)

        # Flask server process
        self.server_process = None

        # Auto-start server if configured
        if self.config['app']['auto_start_server']:
            self.toggle_server()

        # Auto-connect Arduino if configured
        if self.config['serial']['auto_connect']:
            self.toggle_arduino_connection()
            

    def create_split_layout(self, left_widget, right_widget, config_title):
        layout = QHBoxLayout()
        layout.addWidget(left_widget, 1)
        
        # Wrap the right widget (config editor) in a QGroupBox
        group_box = QGroupBox(config_title)
        group_box_layout = QVBoxLayout()
        group_box_layout.addWidget(right_widget)
        group_box.setLayout(group_box_layout)
        
        layout.addWidget(group_box, 1)
        return layout

    def create_local_server_tab(self):
        tab = QWidget()
        layout = QHBoxLayout()
        
        # Left side - Server controls
        left_widget = QWidget()
        left_layout = QVBoxLayout()
        left_widget.setLayout(left_layout)

        self.server_status_label = QLabel("Status server: Tidak berjalan")
        left_layout.addWidget(self.server_status_label)

        self.server_button = QPushButton("Jalankan server")
        self.server_button.clicked.connect(self.toggle_server)
        left_layout.addWidget(self.server_button)

        layout.addWidget(left_widget)

        # Right side - Configuration
        right_widget = ConfigEditor(self.config, 'app')
        
        # Wrap the right widget in a QGroupBox
        group_box = QGroupBox("Konfigurasi server lokal")
        group_box_layout = QVBoxLayout()
        group_box_layout.addWidget(right_widget)
        group_box.setLayout(group_box_layout)
        
        layout.addWidget(group_box)

        layout = self.create_split_layout(left_widget, right_widget, "Konfigurasi server lokal")
        tab.setLayout(layout)
        self.tab_widget.addTab(tab, "Server lokal")

    def toggle_server(self):
       if self.server_process is None or self.server_process.poll() is not None:
          # Start the server
          self.server_process = subprocess.Popen([sys.executable, 'server.py'])
          self.server_status_label.setText("Status server: Berjalan")
          self.server_button.setText("Hentikan server")
       else:
          # Stop the server
          self.server_process.terminate()
          self.server_process = None
          self.server_status_label.setText("Status server: Berhenti")
          self.server_button.setText("Jalankan server")

    def save_config(self):
        with open('config.json', 'w') as config_file:
            json.dump(self.config, config_file, indent=4)

    def create_arduino_tab(self):
        tab = QWidget()
        left_widget = QWidget()
        left_layout = QVBoxLayout()
        left_widget.setLayout(left_layout)
        
        self.arduino_status = QLabel("Status Arduino: Tidak tersambung")
        left_layout.addWidget(self.arduino_status)
        
        self.arduino_connect_button = QPushButton("Sambungkan Arduino")
        self.arduino_connect_button.clicked.connect(self.toggle_arduino_connection)
        left_layout.addWidget(self.arduino_connect_button)
        
        right_widget = ConfigEditor(self.config, 'serial')
        
        layout = self.create_split_layout(left_widget, right_widget, "Konfigurasi Arduino")
        tab.setLayout(layout)
        self.tab_widget.addTab(tab, "Arduino")

    @pyqtSlot()
    def toggle_arduino_connection(self):
        if not self.arduino_connected:
            try:
                self.ser = serial.Serial(self.config['serial']['port'], self.config['serial']['baud_rate'], timeout=1)
                self.arduino_connected = True
                self.arduino_status.setText("Status Arduino: Tersambung")
                self.arduino_connect_button.setText("Putuskan Arduino")
                self.arduino_timer.start(self.config['serial']['interval'])
                self.log_display.append("Arduino tersambung")
            except serial.SerialException as e:
                self.log_display.append(f"Galat penyambungan Arduino: {str(e)}")
        else:
            self.arduino_timer.stop()
            self.ser.close()
            self.arduino_connected = False
            self.arduino_status.setText("Status Arduino: Tidak tersambung")
            self.arduino_connect_button.setText("Sambungkan Arduino")
            self.arduino_data = 0
            self.log_display.append("Arduino terputus")

    @pyqtSlot()
    def update_arduino_data(self):
        if self.arduino_connected:
            try:
                line = self.ser.readline().decode('utf-8').strip()
                if line:
                    try:
                        self.arduino_data = float(line)
                        self.arduino_status.setText(f"Status Arduino: Tersambung - Data: {self.arduino_data}")
                        self.log_display.append(f"Data yang diterima: {self.arduino_data}")
                        
                        # Write data to file with timestamp
                        data = {
                            'value': self.arduino_data,
                            'timestamp': time.time()
                        }
                        with open('arduino_data.json', 'w') as f:
                            json.dump(data, f)
                    except ValueError:
                        self.arduino_data = 0
                        self.log_display.append(f"Data tidak valid diterima: {line}")
                else:
                    self.arduino_status.setText("Status Arduino: Tersambung - Tidak ada data")
            except serial.SerialException as e:
                self.log_display.append(f"Galat serial: {str(e)}")
                self.toggle_arduino_connection()  # Disconnect on error

    def create_camera_tab(self):
        tab = QWidget()
        left_widget = QWidget()
        left_layout = QVBoxLayout()
        left_widget.setLayout(left_layout)
        
        self.capture_button = QPushButton("Ambil Foto")
        self.capture_button.clicked.connect(self.capture_photo)
        left_layout.addWidget(self.capture_button)
        
        self.photo_label = QLabel()
        self.photo_label.setAlignment(Qt.AlignmentFlag.AlignCenter)
        self.photo_label.setSizePolicy(QSizePolicy.Policy.Expanding, QSizePolicy.Policy.Expanding)
        left_layout.addWidget(self.photo_label)
        
        right_widget = ConfigEditor(self.config, 'capture')
        
        layout = self.create_split_layout(left_widget, right_widget, "Konfigurasi Kamera")
        tab.setLayout(layout)
        self.tab_widget.addTab(tab, "Kamera")

    def create_data_tab(self):
        tab = QWidget()
        left_widget = QWidget()
        left_layout = QVBoxLayout()
        left_widget.setLayout(left_layout)
        
        data_status = QLabel("Penanganan data")
        left_layout.addWidget(data_status)
        
        right_widget = ConfigEditor(self.config, 'data_handling')
        
        layout = self.create_split_layout(left_widget, right_widget, "Konfigurasi data")
        tab.setLayout(layout)
        self.tab_widget.addTab(tab, "Data")

    @pyqtSlot()
    def update_arduino_data(self):
        if self.arduino_connected:
            try:
                raw_line = self.ser.readline()
                try:
                    line = raw_line.decode('utf-8').strip()
                except UnicodeDecodeError:
                    # If UTF-8 decoding fails, try with 'latin-1' which accepts all byte values
                    line = raw_line.decode('latin-1').strip()
                    self.log_display.append(f"Warning: Received non-UTF-8 data: {line}")

                if line:
                    try:
                        self.arduino_data = float(line)
                        self.arduino_status.setText(f"Status Arduino: Tersambung - Data: {self.arduino_data}")
                        self.log_display.append(f"Data yang diterima: {self.arduino_data}")
                        
                        # Write data to file with timestamp
                        data = {
                            'value': self.arduino_data,
                            'timestamp': time.time()
                        }
                        with open('arduino_data.json', 'w') as f:
                            json.dump(data, f)
                    except ValueError:
                        self.arduino_data = 0
                        self.log_display.append(f"Data tidak valid diterima: {line}")
                else:
                    self.arduino_status.setText("Status Arduino: Tersambung - Tidak ada data")
            except serial.SerialException as e:
                self.log_display.append(f"Galat serial: {str(e)}")
                self.toggle_arduino_connection()  # Disconnect on error

    @pyqtSlot()
    def capture_photo(self):
        cap = cv2.VideoCapture(self.config['capture']['camera_index'])
        ret, frame = cap.read()
        cap.release()

        if ret:
            rgb_image = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            h, w, ch = rgb_image.shape
            bytes_per_line = ch * w
            qt_image = QImage(rgb_image.data, w, h, bytes_per_line, QImage.Format.Format_RGB888)
            pixmap = QPixmap.fromImage(qt_image)
            
            scaled_pixmap = pixmap.scaled(self.photo_label.size(), Qt.AspectRatioMode.KeepAspectRatio, Qt.TransformationMode.SmoothTransformation)
            self.photo_label.setPixmap(scaled_pixmap)
            
            self.log_display.append("Foto diambil")
        else:
            self.log_display.append("Foto gagal diambil")

    def resizeEvent(self, event):
        super().resizeEvent(event)
        if hasattr(self, 'photo_label') and self.photo_label.pixmap():
            scaled_pixmap = self.photo_label.pixmap().scaled(
                self.photo_label.size(), 
                Qt.AspectRatioMode.KeepAspectRatio, 
                Qt.TransformationMode.SmoothTransformation
            )
            self.photo_label.setPixmap(scaled_pixmap)

    def restart_application(self):
        QApplication.quit()
        status = subprocess.call([sys.executable, sys.argv[0]] + sys.argv[1:])
        sys.exit(status)

if __name__ == '__main__':
    app = QApplication(sys.argv)
    window = MainWindow()
    window.show()
    sys.exit(app.exec())