"""
Command Manager for Laravel Artisan Commands
Handles starting, stopping, and monitoring of artisan commands
"""

import os
import signal
import subprocess
import threading
import time
import psutil
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Any
import logging
from logging.handlers import RotatingFileHandler


class CommandManager:
    def __init__(self, config_manager):
        self.config_manager = config_manager
        self.running_processes = {}  # command_id -> process info
        self.logs_dir = Path(__file__).parent / "logs"
        self.logs_dir.mkdir(exist_ok=True)
        
        # Setup logging
        self._setup_logging()
        
        # Start monitoring thread
        self.monitoring = True
        self.monitor_thread = threading.Thread(target=self._monitor_processes, daemon=True)
        self.monitor_thread.start()
    
    def _setup_logging(self):
        """Setup logging configuration"""
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        self.logger = logging.getLogger(__name__)
    
    def _get_log_file_path(self, command_id: str) -> Path:
        """Get log file path for a command"""
        return self.logs_dir / f"{command_id}.log"
    
    def _setup_command_logger(self, command_id: str) -> logging.Logger:
        """Setup logger for a specific command with rotation"""
        log_file = self._get_log_file_path(command_id)
        
        # Create logger
        logger = logging.getLogger(f"command.{command_id}")
        logger.setLevel(logging.INFO)
        
        # Remove existing handlers to avoid duplicates
        for handler in logger.handlers[:]:
            logger.removeHandler(handler)
        
        # Create rotating file handler (5MB max, keep 3 files)
        handler = RotatingFileHandler(
            log_file,
            maxBytes=5 * 1024 * 1024,  # 5MB
            backupCount=3,
            encoding='utf-8'
        )
        
        # Set format
        formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
        handler.setFormatter(formatter)
        
        logger.addHandler(handler)
        logger.propagate = False  # Don't propagate to root logger
        
        return logger
    
    def start_command(self, command_id: str) -> bool:
        """Start a command"""
        try:
            # Check if command exists in config
            command_config = self.config_manager.get_command(command_id)
            if not command_config:
                self.logger.error(f"Command {command_id} not found in configuration")
                return False
            
            # Check if command is already running
            if command_id in self.running_processes:
                process_info = self.running_processes[command_id]
                if self._is_process_running(process_info['pid']):
                    self.logger.warning(f"Command {command_id} is already running")
                    return False
                else:
                    # Clean up dead process
                    del self.running_processes[command_id]
            
            # Setup logger for this command
            command_logger = self._setup_command_logger(command_id)
            
            # Start the process
            command = command_config['command']
            self.logger.info(f"Starting command: {command}")
            command_logger.info(f"Starting command: {command}")
            
            # Split command for subprocess
            cmd_parts = command.split()
            
            # Get explicitly configured working directory (no fallbacks)
            working_dir = self.config_manager.get_configured_working_directory()
            
            # Require explicit working directory configuration
            if not working_dir:
                error_msg = "Working directory must be explicitly configured before starting commands. Please set it in the Configuration tab."
                self.logger.error(error_msg)
                command_logger.error(error_msg)
                return False
            
            self.logger.info(f"Using working directory: {working_dir}")
            command_logger.info(f"Using working directory: {working_dir}")
            
            # Verify we can access the working directory
            try:
                os.chdir(working_dir)
                os.chdir(os.getcwd())  # Change back to current directory
            except (OSError, PermissionError) as e:
                error_msg = f"Cannot access working directory {working_dir}: {e}"
                self.logger.error(error_msg)
                command_logger.error(error_msg)
                return False
            
            # Prepare subprocess arguments
            popen_kwargs = {
                'stdout': subprocess.PIPE,
                'stderr': subprocess.STDOUT,
                'universal_newlines': True,
                'bufsize': 1,
                'cwd': working_dir
            }
            
            # Hide console window on Windows
            if os.name == 'nt':  # Windows
                popen_kwargs['creationflags'] = subprocess.CREATE_NO_WINDOW
            
            # Start process
            process = subprocess.Popen(cmd_parts, **popen_kwargs)
            
            # Store process information
            self.running_processes[command_id] = {
                'pid': process.pid,
                'process': process,
                'command': command,
                'started_at': datetime.now().isoformat(),
                'logger': command_logger,
                'last_output': ''
            }
            
            # Start output monitoring thread
            output_thread = threading.Thread(
                target=self._monitor_command_output,
                args=(command_id, process),
                daemon=True
            )
            output_thread.start()
            
            self.logger.info(f"Command {command_id} started with PID {process.pid}")
            command_logger.info(f"Process started with PID {process.pid}")
            
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to start command {command_id}: {e}")
            return False
    
    def stop_command(self, command_id: str) -> bool:
        """Stop a command gracefully"""
        try:
            if command_id not in self.running_processes:
                self.logger.warning(f"Command {command_id} is not running")
                return False
            
            process_info = self.running_processes[command_id]
            pid = process_info['pid']
            process = process_info['process']
            command_logger = process_info['logger']
            
            self.logger.info(f"Stopping command {command_id} (PID: {pid})")
            command_logger.info("Stopping command gracefully...")
            
            # Try graceful shutdown first
            try:
                if os.name == 'nt':  # Windows
                    process.terminate()
                else:  # Unix-like
                    os.kill(pid, signal.SIGTERM)
                
                # Wait for graceful shutdown (5 seconds)
                try:
                    process.wait(timeout=5)
                    command_logger.info("Command stopped gracefully")
                except subprocess.TimeoutExpired:
                    # Force kill if graceful shutdown fails
                    self.logger.warning(f"Force killing command {command_id}")
                    command_logger.warning("Graceful shutdown failed, force killing...")
                    
                    if os.name == 'nt':  # Windows
                        process.kill()
                    else:  # Unix-like
                        os.kill(pid, signal.SIGKILL)
                    
                    process.wait(timeout=2)
                    command_logger.info("Command force killed")
                
            except ProcessLookupError:
                # Process already dead
                command_logger.info("Process was already terminated")
            
            # Clean up
            del self.running_processes[command_id]
            
            self.logger.info(f"Command {command_id} stopped successfully")
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to stop command {command_id}: {e}")
            return False
    
    def stop_all_commands(self):
        """Stop all running commands"""
        command_ids = list(self.running_processes.keys())
        for command_id in command_ids:
            self.stop_command(command_id)
    
    def validate_working_directory_for_operation(self) -> tuple[bool, str]:
        """Validate working directory before any operation"""
        return self.config_manager.validate_current_working_directory()
    
    def start_multiple_commands(self, command_ids: List[str]) -> Dict[str, bool]:
        """Start multiple commands with working directory validation"""
        results = {}
        
        # Check if working directory is configured before starting any commands
        if not self.config_manager.is_working_directory_configured():
            error_msg = "Working directory must be explicitly configured before starting commands. Please set it in the Configuration tab."
            self.logger.error(error_msg)
            # Mark all commands as failed
            for command_id in command_ids:
                results[command_id] = False
            return results
        
        # Start each command
        for command_id in command_ids:
            try:
                results[command_id] = self.start_command(command_id)
            except Exception as e:
                self.logger.error(f"Failed to start command {command_id}: {e}")
                results[command_id] = False
        
        return results
    
    def get_command_status(self, command_id: str) -> Dict[str, Any]:
        """Get status of a command"""
        if command_id not in self.running_processes:
            return {
                'status': 'stopped',
                'pid': None,
                'started_at': None,
                'last_output': ''
            }
        
        process_info = self.running_processes[command_id]
        pid = process_info['pid']
        
        if self._is_process_running(pid):
            return {
                'status': 'running',
                'pid': pid,
                'started_at': process_info['started_at'],
                'last_output': process_info['last_output']
            }
        else:
            # Process died, clean up
            del self.running_processes[command_id]
            return {
                'status': 'stopped',
                'pid': None,
                'started_at': None,
                'last_output': process_info.get('last_output', '')
            }
    
    def get_command_logs(self, command_id: str, lines: int = 100) -> List[str]:
        """Get recent logs for a command"""
        log_file = self._get_log_file_path(command_id)
        
        if not log_file.exists():
            return []
        
        try:
            with open(log_file, 'r', encoding='utf-8') as f:
                all_lines = f.readlines()
                return [line.rstrip() for line in all_lines[-lines:]]
        except Exception as e:
            self.logger.error(f"Failed to read logs for {command_id}: {e}")
            return []
    
    def _is_process_running(self, pid: int) -> bool:
        """Check if a process is still running"""
        try:
            return psutil.pid_exists(pid)
        except Exception:
            return False
    
    def _monitor_command_output(self, command_id: str, process):
        """Monitor command output and log it"""
        try:
            process_info = self.running_processes.get(command_id)
            if not process_info:
                return
            
            command_logger = process_info['logger']
            
            for line in iter(process.stdout.readline, ''):
                if not line:
                    break
                
                line = line.rstrip()
                if line:
                    command_logger.info(line)
                    
                    # Update last output
                    if command_id in self.running_processes:
                        self.running_processes[command_id]['last_output'] = line
            
            # Process ended
            return_code = process.wait()
            command_logger.info(f"Process ended with return code: {return_code}")
            
        except Exception as e:
            self.logger.error(f"Error monitoring output for {command_id}: {e}")
    
    def _monitor_processes(self):
        """Monitor running processes and clean up dead ones"""
        while self.monitoring:
            try:
                dead_commands = []
                
                for command_id, process_info in self.running_processes.items():
                    pid = process_info['pid']
                    if not self._is_process_running(pid):
                        dead_commands.append(command_id)
                        process_info['logger'].warning("Process died unexpectedly")
                
                # Clean up dead processes
                for command_id in dead_commands:
                    del self.running_processes[command_id]
                    self.logger.info(f"Cleaned up dead process for command {command_id}")
                
                time.sleep(10)  # Check every 10 seconds
                
            except Exception as e:
                self.logger.error(f"Error in process monitoring: {e}")
                time.sleep(10)
    
    def get_all_statuses(self) -> Dict[str, Dict[str, Any]]:
        """Get status of all configured commands"""
        commands = self.config_manager.get_commands()
        statuses = {}
        
        for cmd in commands:
            command_id = cmd['id']
            statuses[command_id] = self.get_command_status(command_id)
        
        return statuses