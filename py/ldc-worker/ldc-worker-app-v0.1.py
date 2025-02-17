from fastapi import FastAPI, WebSocket
from fastapi.middleware.cors import CORSMiddleware
import uvicorn
import logging
from typing import List
import sys
import ctypes
from scapy.all import sniff, TCP, Raw, conf
import json
import asyncio
from datetime import datetime

# Set up logging
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Constants
MM2_TO_FT2 = 0.00001076391  # Conversion factor from mm² to ft²
WEBSOCKET_PORT = 32998

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

def is_admin():
    try:
        return ctypes.windll.shell32.IsUserAnAdmin()
    except:
        return False

def get_loopback_interface():
    interfaces = conf.ifaces
    for iface in interfaces.values():
        if "Loopback" in iface.name or "loopback" in iface.name.lower():
            return iface.name
    return None

def convert_to_ft2(mm2_value):
    """Convert from mm² to ft² and round to 2 decimal places"""
    return round(float(mm2_value) * MM2_TO_FT2, 2)

async def broadcast_to_clients(data):
    """Broadcast data to all connected WebSocket clients"""
    for connection in active_connections:
        try:
            await connection.send_json(data)
            logger.debug(f"Successfully sent to client {id(connection)}")
        except Exception as e:
            logger.error(f"Failed to send to client {id(connection)}: {str(e)}")
            active_connections.remove(connection)

def parse_chunked_http_payload(payload):
    """Parse an HTTP payload with chunked transfer encoding to extract JSON data."""
    try:
        headers, body = payload.split('\r\n\r\n', 1)
    except ValueError:
        raise ValueError("Invalid HTTP payload format - couldn't separate headers and body")

    lines = body.strip().split('\r\n')
    json_lines = []
    i = 1
    while i < len(lines):
        if lines[i].strip() == '0':
            break
        if not lines[i].strip().isdigit():
            json_lines.append(lines[i])
        i += 1
    
    json_data = '\n'.join(json_lines)
    
    try:
        return json.loads(json_data)
    except json.JSONDecodeError as e:
        raise ValueError(f"Invalid JSON format: {str(e)}")

def process_packet(packet):
    """Process captured packets and extract relevant data"""
    if packet.haslayer(TCP) and packet.haslayer(Raw):
        try:
            payload = packet[Raw].load.decode('utf-8')
            if "POST /add_statinfo HTTP/1.1" in payload:
                events = parse_chunked_http_payload(payload)
                
                for event in events:
                    data = event.get("data", [])
                    if data and len(data) >= 34 and data[0] == 14:
                        # Extract and convert data
                        relevant_data = {
                            'code': data[2],
                            'area_ab': convert_to_ft2(data[33]),
                            'area_qt': convert_to_ft2(data[15] + data[19] + data[23]),
                            'timestamp': datetime.now().isoformat()
                        }
                        logger.debug(f"Event Type 14 detected: {relevant_data}")
                        broadcast_to_clients(relevant_data)
        except UnicodeDecodeError as e:
            pass

        except Exception as e:
            logger.error(f"Error processing packet: {e}")

@app.websocket("/ws")
async def websocket_endpoint(websocket: WebSocket):
    await websocket.accept()
    client_id = id(websocket)
    logger.info(f"New WebSocket connection established. Client ID: {client_id}")
    logger.info(f"Total active connections: {len(active_connections) + 1}")
    
    active_connections.append(websocket)
    try:
        while True:
            # Keep the connection alive
            await websocket.receive_text()
    except Exception as e:
        logger.error(f"WebSocket error with client {client_id}: {str(e)}")
    finally:
        active_connections.remove(websocket)
        logger.info(f"Client {client_id} disconnected")
        logger.info(f"Remaining active connections: {len(active_connections)}")

async def start_packet_sniffing():
    """Start packet sniffing in a separate thread"""
    loop = asyncio.get_event_loop()
    
    # Get loopback interface
    loopback_iface = get_loopback_interface()
    if not loopback_iface:
        logger.error("Could not find loopback interface")
        sys.exit(1)

    logger.info(f"Starting packet sniffing on interface: {loopback_iface}")
    
    # Run sniffing in a thread pool
    await loop.run_in_executor(
        None,
        lambda: sniff(
            filter="tcp",
            prn=process_packet,
            iface=loopback_iface
        )
    )

@app.on_event("startup")
async def startup_event():
    # Check for admin privileges
    if not is_admin():
        logger.error("This program requires administrator privileges to capture packets.")
        sys.exit(1)
    
    # Start packet sniffing
    asyncio.create_task(start_packet_sniffing())

if __name__ == "__main__":
    print("\n=== WebSocket and Packet Sniffer Server ===")
    print("Installing required packages...")
    print("Run these commands if you haven't already:")
    print("pip install fastapi uvicorn websockets scapy")
    print("\nStarting server...")
    uvicorn.run(app, host="127.0.0.1", port=WEBSOCKET_PORT)