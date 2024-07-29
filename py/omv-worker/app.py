import serial
from flask import Flask, jsonify, send_file, request
import time
import cv2
import io
import json
import sqlite3
import requests
from datetime import datetime, timedelta
import threading
from flask_cors import CORS
import os
import logging
from logging.handlers import RotatingFileHandler
from urllib.parse import urlparse

app = Flask(__name__)
CORS(app)

# Load configuration
config_file_path = 'config.json'
with open(config_file_path, 'r') as config_file:
    config = json.load(config_file)

# Set up logging
log_file = 'debug.log'
log_handler = RotatingFileHandler(log_file, maxBytes=100000, backupCount=1)
log_handler.setFormatter(logging.Formatter('%(asctime)s - %(levelname)s - %(message)s'))
logger = logging.getLogger()
logger.addHandler(log_handler)
logger.setLevel(logging.ERROR)

# Database setup
conn = sqlite3.connect(config['app']['database_file'], check_same_thread=False)
cursor = conn.cursor()
cursor.execute('''CREATE TABLE IF NOT EXISTS queue
                  (id INTEGER PRIMARY KEY AUTOINCREMENT,
                   data TEXT,
                   attempts INTEGER,
                   created_at TIMESTAMP,
                   last_tried_at TIMESTAMP,
                   last_tried_msg TEXT)''')
conn.commit()

# Global variables
start_time = time.time()
request_count = 0
error_count = 0

def is_valid_url(url):
    try:
        result = urlparse(url)
        return all([result.scheme, result.netloc])
    except ValueError:
        return False

def validate_server_url(server):
    return is_valid_url(server)

def update_config(new_server):
    if config['app']['remote_server_url'] != new_server:
        config['app']['remote_server_url'] = new_server
        with open(config_file_path, 'w') as config_file:
            json.dump(config, config_file, indent=4)
        logger.info(f"Updated remote server configuration to: {new_server}")

@app.route('/send-data', methods=['POST'])
def send_data():
    data = request.json
    if 'server_url' not in data:
        return jsonify({"status": "error", "message": "server_url parameter is missing"}), 400
    
    server_url = data['server_url']
    if not validate_server_url(server_url):
        return jsonify({"status": "error", "message": "Invalid server_url parameter value"}), 400
    
    update_config(server_url)
    
    success, server_message = send_to_server(data)
    if success:
        return jsonify({"status": "success", "message": server_message}), 200
    else:
        queue_data(data, server_message)
        error_message = f"Data queued. Server message: {server_message}"
        logger.error(error_message)
        return jsonify({"status": "queued", "message": error_message}), 202

def send_to_server(data):
    try:
        server_url = f"{config['app']['remote_server_url']}/api/omv-metric"
        response = requests.post(server_url, json=data, timeout=5)
        server_message = response.text
        if response.status_code != 200:
            error_message = f"Server returned non-200 status code: {response.status_code}. Message: {server_message}"
            logger.error(error_message)
            return False, error_message
        return True, server_message
    except requests.RequestException as e:
        error_message = f"Error sending data to server: {str(e)}"
        logger.error(error_message)
        return False, error_message

def queue_data(data, server_message):
    try:
        current_time = datetime.now()
        cursor.execute("""
            INSERT INTO queue (data, attempts, created_at, last_tried_at, last_tried_msg)
            VALUES (?, ?, ?, ?, ?)
        """, (json.dumps(data), 0, current_time, current_time, server_message))
        conn.commit()
    except sqlite3.Error as e:
        error_message = f"Error queueing data: {str(e)}"
        logger.error(error_message)

def retry_queued_data():
    while True:
        try:
            cursor.execute("""
                SELECT id, data, attempts FROM queue
                WHERE attempts < ? AND created_at > ?
            """, (config['data_handling']['max_attempts'], 
                  datetime.now() - timedelta(days=config['data_handling']['max_age_days'])))
            rows = cursor.fetchall()
            for row in rows:
                id, data, attempts = row
                success, server_message = send_to_server(json.loads(data))
                if success:
                    cursor.execute("DELETE FROM queue WHERE id = ?", (id,))
                else:
                    new_attempts = attempts + 1
                    cursor.execute("""
                        UPDATE queue
                        SET attempts = ?, last_tried_at = ?, last_tried_msg = ?
                        WHERE id = ?
                    """, (new_attempts, datetime.now(), server_message, id))
                conn.commit()
                time.sleep(calculate_backoff(attempts))
            
            if not rows:
                # If no rows were processed, sleep for a while before checking again
                time.sleep(config['data_handling']['initial_backoff'])
        except Exception as e:
            error_message = f"Error in retry_queued_data: {str(e)}"
            logger.error(error_message)
            time.sleep(config['data_handling']['initial_backoff'])

def calculate_backoff(attempts):
    return min(config['data_handling']['max_backoff'], 
               config['data_handling']['initial_backoff'] * (2 ** attempts))

def read_serial_data():
    global error_count
    ser = None
    try:
        ser = serial.Serial(config['serial']['port'], config['serial']['baud_rate'], timeout=1)
        ser.reset_input_buffer()
        for _ in range(5):
            line = ser.readline()
            if line:
                decoded_line = line.decode('utf-8', errors='replace').strip()
                if decoded_line.isdigit():
                    return int(decoded_line)
            time.sleep(0.2)
        return None
    except serial.SerialException as e:
        error_message = f"Error reading from serial port: {e}"
        logger.error(error_message)
        error_count += 1
        return None
    finally:
        if ser is not None and ser.is_open:
            ser.close()

@app.route('/get-data')
def get_data():
    global request_count
    request_count += 1
    raw_data = read_serial_data()
    
    if raw_data is not None:
        eval_result = raw_data >= config['serial']['threshold']
        return jsonify({"eval": eval_result, "raw": raw_data})
    else:
        error_message = "Unable to read valid data from Arduino"
        logger.error(error_message)
        return jsonify({"error": error_message, "eval": False, "raw": None}), 500

def crop_and_resize(image, target_width, target_height):
    height, width = image.shape[:2]
    aspect_ratio = width / height

    if aspect_ratio > target_width / target_height:
        new_width = int(height * target_width / target_height)
        start_x = (width - new_width) // 2
        cropped = image[:, start_x:start_x+new_width]
    else:
        new_height = int(width * target_height / target_width)
        start_y = (height - new_height) // 2
        cropped = image[start_y:start_y+new_height, :]

    resized = cv2.resize(cropped, (target_width, target_height), interpolation=cv2.INTER_AREA)
    return resized

@app.route('/get-photo')
def get_photo():
    try:
        cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)
        if not cap.isOpened():
            raise IOError("Cannot open webcam")
        
        for i in range(config['capture']['frame']):
            ret, frame = cap.read()

        cap.release()
        if not ret:
            raise IOError("Cannot capture image")
        processed_frame = crop_and_resize(frame, config['capture']['width'], config['capture']['height'])
        _, buffer = cv2.imencode('.jpg', processed_frame)
        image_bytes = io.BytesIO(buffer)
        return send_file(image_bytes, mimetype='image/jpeg')
    except Exception as e:
        error_message = f"Error capturing photo: {str(e)}"
        logger.error(error_message)
        return jsonify({"error": error_message}), 500

@app.route('/debug-info')
def debug_info():
    uptime = time.time() - start_time
    return jsonify({
        "configuration": config,
        "statistics": {
            "uptime_seconds": round(uptime, 2),
            "total_requests": request_count,
            "error_count": error_count
        }
    })

if __name__ == '__main__':
    threading.Thread(target=retry_queued_data, daemon=True).start()
    app.run(host=config['app']['host'], port=config['app']['port'], debug=config['app']['debug'])