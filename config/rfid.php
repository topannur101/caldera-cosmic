<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RFID WebSocket URL
    |--------------------------------------------------------------------------
    |
    | This is the browser WebSocket endpoint exposed by your Python service.
    | Example: ws://127.0.0.1:8765/
    | If your Laravel app is served via HTTPS, you must use wss://
    |
    */
    'ws_url_rfid' => env('RFID_WS_URL', 'ws://127.0.0.1:8765/'),
    'ws_url_node_1' => env('NODE_1_WS_URL', 'ws://127.0.0.1:8767/'),
];

