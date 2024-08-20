import sys
from flask import Flask, jsonify, send_file, request
from flask_cors import CORS
import cv2
import io
import json
import time

app = Flask(__name__)
CORS(app)

# Load configuration
with open('config.json', 'r') as config_file:
    config = json.load(config_file)

@app.route('/get-line')
def get_line():
    line = config['app'].get('line')
    if line is not None:
        return str(line), 200, {'Content-Type': 'text/plain'}
    else:
        return str(99), 404, {'Content-Type': 'text/plain'}

@app.route('/send-data', methods=['POST'])
def send_data():
    data = request.json
    # Process the data as needed
    return jsonify({"status": "success", "message": "Data received"}), 200

@app.route('/get-data')
def get_data():
    try:
        with open('arduino_data.json', 'r') as f:
            data = json.load(f)
        
        current_time = time.time()
        if current_time - data['timestamp'] > 5:  # Data is older than 5 seconds
            return jsonify({
                'raw': 0,
                'eval': False,
                'remarks': 'Data expired'
            })

        threshold = config['serial'].get('threshold', 0)  # Default to 0 if not set
        eval_result = data['value'] > threshold

        return jsonify({
            'raw': data['value'],
            'eval': eval_result
        })
    except (FileNotFoundError, ValueError, KeyError, json.JSONDecodeError):
        return jsonify({
            'raw': 0,
            'eval': False,
            'remarks': 'No data available or invalid data'
        }), 500

@app.route('/get-photo')
def get_photo():
    # Use OpenCV to capture a photo
    cap = cv2.VideoCapture(config['capture']['camera_index'])
    ret, frame = cap.read()
    cap.release()

    if ret:
        _, buffer = cv2.imencode('.jpg', frame)
        io_buf = io.BytesIO(buffer)
        return send_file(io_buf, mimetype='image/jpeg')
    else:
        return jsonify({"error": "Failed to capture photo"}), 500

if __name__ == '__main__':
    app.run(host=config['app']['host'], 
            port=config['app']['port'], 
            debug=config['app']['debug'])