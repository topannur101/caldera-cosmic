import serial
import time
import json

class ArduinoManager:
    def __init__(self, config):
        self.config = config
        self.ser = None
        self.connected = False
        self.data = 0

    def connect(self):
        try:
            self.ser = serial.Serial(
                self.config['serial']['port'],
                self.config['serial']['baud_rate'],
                timeout=1
            )
            self.connected = True
            return True
        except serial.SerialException as e:
            print(f"Error connecting to Arduino: {str(e)}")
            return False

    def disconnect(self):
        if self.ser:
            self.ser.close()
        self.connected = False
        self.data = 0

    def read_data(self):
        if not self.connected:
            return None
        
        try:
            raw_line = self.ser.readline()
            try:
                line = raw_line.decode('utf-8').strip()
            except UnicodeDecodeError:
                line = raw_line.decode('latin-1').strip()
                print(f"Warning: Received non-UTF-8 data: {line}")

            if line:
                try:
                    self.data = float(line)
                    self.save_data()
                    return self.data
                except ValueError:
                    print(f"Invalid data received: {line}")
            return None
        except serial.SerialException as e:
            print(f"Serial error: {str(e)}")
            self.disconnect()
            return None

    def save_data(self):
        data = {
            'value': self.data,
            'timestamp': time.time()
        }
        with open('arduino_data.json', 'w') as f:
            json.dump(data, f)
