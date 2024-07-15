import serial
from flask import Flask, jsonify, send_file
import time
import cv2
import io

# pip install pyserial Flask opencv-python-headless

app = Flask(__name__)

# Configure the serial port
SERIAL_PORT = 'COM6'  # Change this to match your Arduino's COM port
BAUD_RATE = 9600

# Photo settings
PHOTO_WIDTH = 320
PHOTO_HEIGHT = 240

# Statistics
start_time = time.time()
request_count = 0
error_count = 0

def read_serial_data():
    global error_count
    try:
        with serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=1) as ser:
            ser.reset_input_buffer()
            for _ in range(5):
                line = ser.readline()
                print(f"Raw data received: {line}")  # Debug print
                if line:
                    decoded_line = line.decode('utf-8', errors='replace').strip()
                    print(f"Decoded data: {decoded_line}")  # Debug print
                    if decoded_line.isdigit():  # Accept any numeric string
                        return int(decoded_line)
                    else:
                        print(f"Invalid data format: {decoded_line}")  # Debug print
                time.sleep(0.2)  # Short delay between attempts
            print("No valid data received after 5 attempts")
            return None
    except serial.SerialException as e:
        print(f"Error reading from serial port: {e}")
        error_count += 1
        return None

@app.route('/')
def root():
    return "Not Found", 404

@app.route('/get-data')
def get_data():
    global request_count
    request_count += 1
    data = read_serial_data()
    if data is not None:
        return jsonify({"data": data})
    else:
        return jsonify({"error": "Unable to read valid data from Arduino"}), 500

def crop_and_resize(image, target_width, target_height):
    height, width = image.shape[:2]
    aspect_ratio = width / height

    if aspect_ratio > target_width / target_height:  # wider than 4:3
        new_width = int(height * target_width / target_height)
        start_x = (width - new_width) // 2
        cropped = image[:, start_x:start_x+new_width]
    else:  # taller than 4:3
        new_height = int(width * target_height / target_width)
        start_y = (height - new_height) // 2
        cropped = image[start_y:start_y+new_height, :]

    resized = cv2.resize(cropped, (target_width, target_height), interpolation=cv2.INTER_AREA)
    return resized

@app.route('/get-photo')
def get_photo():
    try:
        # Initialize the webcam
        cap = cv2.VideoCapture(0)  # 0 is usually the default webcam
        
        # Check if the webcam is opened correctly
        if not cap.isOpened():
            raise IOError("Cannot open webcam")

        # Capture a frame
        ret, frame = cap.read()
        
        # Release the webcam
        cap.release()
        
        if not ret:
            raise IOError("Cannot capture image")

        # Crop and resize the image
        processed_frame = crop_and_resize(frame, PHOTO_WIDTH, PHOTO_HEIGHT)

        # Convert the image to JPEG format
        _, buffer = cv2.imencode('.jpg', processed_frame)
        
        # Convert to bytes
        image_bytes = io.BytesIO(buffer)
        
        # Send the image file
        return send_file(image_bytes, mimetype='image/jpeg')

    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/debug-info')
def debug_info():
    uptime = time.time() - start_time
    return jsonify({
        "serial_port": SERIAL_PORT,
        "baud_rate": BAUD_RATE,
        "uptime_seconds": round(uptime, 2),
        "total_requests": request_count,
        "error_count": error_count
    })

if __name__ == '__main__':
    print(f"Starting server. Debug info available at http://localhost:92/debug-info")
    app.run(host='localhost', port=92, debug=True)