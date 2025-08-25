"""
Flask API Server for Laravel Daemon Manager
Provides RESTful API endpoints for Laravel to communicate with
"""

import json
from flask import Flask, jsonify, request
from flask_cors import CORS
import threading
import logging
import sys


class APIServer:
    def __init__(self, command_manager, port=8765):
        self.command_manager = command_manager
        self.port = port
        self.app = Flask(__name__)
        
        # Enable CORS for Laravel communication
        CORS(self.app, origins=['http://localhost:8000', 'http://127.0.0.1:8000'])
        
        # Reduce Flask logging noise
        log = logging.getLogger('werkzeug')
        log.setLevel(logging.WARNING)
        
        self._setup_routes()
        self._server_thread = None
        self._shutdown_flag = threading.Event()
    
    def _setup_routes(self):
        """Setup API routes"""
        
        @self.app.route('/api/commands', methods=['GET'])
        def get_commands():
            """Get all configured commands with their status"""
            try:
                commands = self.command_manager.config_manager.get_commands()
                result = []
                
                for cmd in commands:
                    status = self.command_manager.get_command_status(cmd['id'])
                    result.append({
                        'id': cmd['id'],
                        'name': cmd['name'],
                        'command': cmd['command'],
                        'description': cmd.get('description', ''),
                        'enabled': cmd.get('enabled', True),
                        'status': status['status'],
                        'pid': status.get('pid'),
                        'started_at': status.get('started_at'),
                        'last_output': status.get('last_output', '')
                    })
                
                return jsonify({
                    'success': True,
                    'data': result
                })
                
            except Exception as e:
                return jsonify({
                    'success': False,
                    'error': str(e)
                }), 500
        
        @self.app.route('/api/commands/<command_id>/start', methods=['POST'])
        def start_command(command_id):
            """Start a specific command"""
            try:
                success = self.command_manager.start_command(command_id)
                
                if success:
                    return jsonify({
                        'success': True,
                        'message': f'Command {command_id} started successfully'
                    })
                else:
                    return jsonify({
                        'success': False,
                        'error': 'Failed to start command'
                    }), 400
                    
            except Exception as e:
                return jsonify({
                    'success': False,
                    'error': str(e)
                }), 500
        
        @self.app.route('/api/commands/<command_id>/stop', methods=['POST'])
        def stop_command(command_id):
            """Stop a specific command"""
            try:
                success = self.command_manager.stop_command(command_id)
                
                if success:
                    return jsonify({
                        'success': True,
                        'message': f'Command {command_id} stopped successfully'
                    })
                else:
                    return jsonify({
                        'success': False,
                        'error': 'Failed to stop command'
                    }), 400
                    
            except Exception as e:
                return jsonify({
                    'success': False,
                    'error': str(e)
                }), 500
        
        @self.app.route('/api/commands/<command_id>/status', methods=['GET'])
        def get_command_status(command_id):
            """Get detailed status of a specific command"""
            try:
                status = self.command_manager.get_command_status(command_id)
                
                return jsonify({
                    'success': True,
                    'data': status
                })
                
            except Exception as e:
                return jsonify({
                    'success': False,
                    'error': str(e)
                }), 500
        
        @self.app.route('/api/commands/<command_id>/logs', methods=['GET'])
        def get_command_logs(command_id):
            """Get recent logs for a specific command"""
            try:
                # Get number of lines to return (default 100)
                lines = request.args.get('lines', 100, type=int)
                logs = self.command_manager.get_command_logs(command_id, lines)
                
                return jsonify({
                    'success': True,
                    'data': {
                        'logs': logs,
                        'lines': len(logs)
                    }
                })
                
            except Exception as e:
                return jsonify({
                    'success': False,
                    'error': str(e)
                }), 500
        
        @self.app.route('/api/health', methods=['GET'])
        def health_check():
            """Health check endpoint"""
            return jsonify({
                'success': True,
                'message': 'Laravel Daemon Manager API is running',
                'version': '1.0.0'
            })
        
        @self.app.errorhandler(404)
        def not_found(error):
            return jsonify({
                'success': False,
                'error': 'Endpoint not found'
            }), 404
    
    def run(self):
        """Run the Flask server"""
        try:
            print(f"Starting API server on http://localhost:{self.port}")
            self.app.run(
                host='127.0.0.1',
                port=self.port,
                debug=False,
                use_reloader=False,
                threaded=True
            )
        except Exception as e:
            print(f"Failed to start API server: {e}")
            sys.exit(1)
    
    def stop(self):
        """Stop the Flask server"""
        # Note: Flask doesn't have a built-in way to stop gracefully
        # In production, you might want to use a WSGI server like Gunicorn
        print("API server stopping...")
        self._shutdown_flag.set()