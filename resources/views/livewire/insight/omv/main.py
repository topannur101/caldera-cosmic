import serial
from flask import Flask, jsonify
import serial.tools.list_ports

app = Flask(__name__)

# Configure the serial port
BAUD_RATE = 9600

def find_arduino_port():
    ports = list(serial.tools.list_ports.comports())
    for p in ports:
        if 'Arduino' in p.description:
            return p.device
    return None

SERIAL_PORT = find_arduino_port() or 'COM3'  # Default to COM3 if Arduino not found

def read_serial_data():
    try:
        with serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=1) as ser:
            line = ser.readline().decode('utf-8').strip()
            if line and line.isdigit() and len(line) == 4:
                return int(line)
            else:
                return None
    except serial.SerialException as e:
        print(f"Error reading from serial port: {e}")
        return None

@app.route('/get-data')
def get_data():
    data = read_serial_data()
    if data is not None:
        return jsonify({"data": data})
    else:
        return jsonify({"error": "Unable to read data from Arduino"}), 500

if __name__ == '__main__':
    print(f"Using serial port: {SERIAL_PORT}")
    app.run(host='localhost', port=5000, debug=True)
