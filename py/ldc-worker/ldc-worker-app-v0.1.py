from fastapi import FastAPI, WebSocket, Request
from fastapi.middleware.cors import CORSMiddleware
import uvicorn
import json
from typing import List
import logging
from datetime import datetime

# Set up logging
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

app = FastAPI()

# Enable CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Store active WebSocket connections
active_connections: List[WebSocket] = []

@app.websocket("/ws")
async def websocket_endpoint(websocket: WebSocket):
    await websocket.accept()
    client_id = id(websocket)
    logger.info(f"New WebSocket connection established. Client ID: {client_id}")
    logger.info(f"Total active connections: {len(active_connections) + 1}")
    
    active_connections.append(websocket)
    try:
        while True:
            message = await websocket.receive_text()
            logger.debug(f"Received message from client {client_id}: {message}")
    except Exception as e:
        logger.error(f"WebSocket error with client {client_id}: {str(e)}")
    finally:
        active_connections.remove(websocket)
        logger.info(f"Client {client_id} disconnected")
        logger.info(f"Remaining active connections: {len(active_connections)}")

@app.post("/add_statinfo")
async def add_statinfo(request: Request):
    # Get raw body and parse JSON
    body = await request.body()
    data = json.loads(body)
    logger.info("Received data to /add_statinfo")
    
    # Process each event in the array
    if isinstance(data, list):
        for event in data:
            if 'data' in event and len(event['data']) >= 14:  # Ensure data array exists and has enough elements
                # Look for events with type 14 (seems to be the leather data)
                if event['data'][0] == 14:
                    # Extract relevant data and broadcast to WebSocket clients
                    relevant_data = {
                        'code': event['data'][2],        # Barcode
                        'area_mm2': event['data'][33],   # Area in mm²
                        'timestamp': event['_ts']
                    }
                    logger.debug(f"Broadcasting data: {relevant_data}")
                    
                    # Broadcast to all connected clients
                    for connection in active_connections:
                        try:
                            await connection.send_json(relevant_data)
                            logger.debug(f"Successfully sent to client {id(connection)}")
                        except Exception as e:
                            logger.error(f"Failed to send to client {id(connection)}: {str(e)}")
                            active_connections.remove(connection)

    return {"status": "success"}

if __name__ == "__main__":
    print("\n=== WebSocket Bridge Server ===")
    print("Installing required packages...")
    print("Run these commands if you haven't already:")
    print("pip install fastapi uvicorn websockets")
    print("\nStarting server...")
    uvicorn.run(app, host="127.0.0.1", port=32999)