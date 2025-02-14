from fastapi import FastAPI, WebSocket
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
    allow_origins=["*"],  # Adjust this in production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Store active WebSocket connections
active_connections: List[WebSocket] = []

# WebSocket connection handler
@app.websocket("/ws")
async def websocket_endpoint(websocket: WebSocket):
    await websocket.accept()
    client_id = id(websocket)  # Use object id as unique identifier
    logger.info(f"New WebSocket connection established. Client ID: {client_id}")
    logger.info(f"Total active connections: {len(active_connections) + 1}")
    
    active_connections.append(websocket)
    try:
        while True:
            # Keep the connection alive and log any received messages
            message = await websocket.receive_text()
            logger.debug(f"Received message from client {client_id}: {message}")
    except Exception as e:
        logger.error(f"WebSocket error with client {client_id}: {str(e)}")
    finally:
        # Remove connection when client disconnects
        active_connections.remove(websocket)
        logger.info(f"Client {client_id} disconnected")
        logger.info(f"Remaining active connections: {len(active_connections)}")

# HTTP endpoint to receive data from existing system
@app.post("/add_statinfo")
async def add_statinfo(data: dict):
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
    logger.info(f"Received POST request to /add_statinfo at {timestamp}")
    logger.debug(f"Received data: {json.dumps(data, indent=2)}")
    
    # Count successful broadcasts
    successful_broadcasts = 0
    
    # Broadcast the received data to all connected WebSocket clients
    for connection in active_connections:
        try:
            await connection.send_json(data)
            successful_broadcasts += 1
            logger.debug(f"Successfully sent data to client {id(connection)}")
        except Exception as e:
            logger.error(f"Failed to send to client {id(connection)}: {str(e)}")
            try:
                active_connections.remove(connection)
                logger.info(f"Removed failed connection {id(connection)}")
            except:
                pass

    logger.info(f"Broadcast complete - {successful_broadcasts} successful out of {len(active_connections)} total connections")
    return {
        "status": "success",
        "timestamp": timestamp,
        "broadcasts_sent": successful_broadcasts,
        "total_connections": len(active_connections)
    }

@app.on_event("startup")
async def startup_event():
    logger.info("Server starting up...")
    logger.info(f"Server will be running on http://127.0.0.1:32999")
    logger.info(f"WebSocket endpoint will be at ws://127.0.0.1:32999/ws")

if __name__ == "__main__":
    print("\n=== WebSocket Bridge Server ===")
    print("Installing required packages...")
    print("Run these commands if you haven't already:")
    print("pip install fastapi uvicorn websockets")
    print("\nStarting server...")
    uvicorn.run(app, host="127.0.0.1", port=32999)