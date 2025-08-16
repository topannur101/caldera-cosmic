"""
Configuration Manager for Laravel Command Manager
Handles reading/writing command configurations to JSON file
"""

import json
import os
from pathlib import Path
from typing import List, Dict, Any, Optional


class ConfigManager:
    def __init__(self, config_file: str = "config.json"):
        self.config_file = Path(__file__).parent / config_file
        self._ensure_config_file()
    
    def _ensure_config_file(self):
        """Ensure configuration file exists"""
        if not self.config_file.exists():
            self._save_config({"commands": []})
    
    def _load_config(self) -> Dict[str, Any]:
        """Load configuration from file"""
        try:
            with open(self.config_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except (FileNotFoundError, json.JSONDecodeError) as e:
            print(f"Error loading config: {e}")
            return {"commands": []}
    
    def _save_config(self, config: Dict[str, Any]):
        """Save configuration to file"""
        try:
            with open(self.config_file, 'w', encoding='utf-8') as f:
                json.dump(config, f, indent=2, ensure_ascii=False)
        except Exception as e:
            print(f"Error saving config: {e}")
            raise
    
    def get_commands(self) -> List[Dict[str, Any]]:
        """Get all configured commands"""
        config = self._load_config()
        return config.get("commands", [])
    
    def get_command(self, command_id: str) -> Optional[Dict[str, Any]]:
        """Get a specific command by ID"""
        commands = self.get_commands()
        for cmd in commands:
            if cmd.get('id') == command_id:
                return cmd
        return None
    
    def add_command(self, command: Dict[str, Any]) -> bool:
        """Add a new command"""
        try:
            config = self._load_config()
            commands = config.get("commands", [])
            
            # Check if command ID already exists
            if any(cmd.get('id') == command.get('id') for cmd in commands):
                return False
            
            # Validate required fields
            required_fields = ['id', 'name', 'command']
            if not all(field in command for field in required_fields):
                return False
            
            commands.append(command)
            config["commands"] = commands
            self._save_config(config)
            return True
            
        except Exception as e:
            print(f"Error adding command: {e}")
            return False
    
    def update_command(self, command_id: str, updated_command: Dict[str, Any]) -> bool:
        """Update an existing command"""
        try:
            config = self._load_config()
            commands = config.get("commands", [])
            
            for i, cmd in enumerate(commands):
                if cmd.get('id') == command_id:
                    # Preserve the original ID
                    updated_command['id'] = command_id
                    commands[i] = updated_command
                    config["commands"] = commands
                    self._save_config(config)
                    return True
            
            return False
            
        except Exception as e:
            print(f"Error updating command: {e}")
            return False
    
    def remove_command(self, command_id: str) -> bool:
        """Remove a command"""
        try:
            config = self._load_config()
            commands = config.get("commands", [])
            
            original_count = len(commands)
            commands = [cmd for cmd in commands if cmd.get('id') != command_id]
            
            if len(commands) < original_count:
                config["commands"] = commands
                self._save_config(config)
                return True
            
            return False
            
        except Exception as e:
            print(f"Error removing command: {e}")
            return False
    
    def get_enabled_commands(self) -> List[Dict[str, Any]]:
        """Get only enabled commands"""
        commands = self.get_commands()
        return [cmd for cmd in commands if cmd.get('enabled', True)]
    
    def set_command_enabled(self, command_id: str, enabled: bool) -> bool:
        """Enable or disable a command"""
        command = self.get_command(command_id)
        if command:
            command['enabled'] = enabled
            return self.update_command(command_id, command)
        return False
    
    def export_config(self, file_path: str) -> bool:
        """Export configuration to a file"""
        try:
            config = self._load_config()
            with open(file_path, 'w', encoding='utf-8') as f:
                json.dump(config, f, indent=2, ensure_ascii=False)
            return True
        except Exception as e:
            print(f"Error exporting config: {e}")
            return False
    
    def import_config(self, file_path: str) -> bool:
        """Import configuration from a file"""
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                config = json.load(f)
            
            # Validate configuration structure
            if not isinstance(config, dict) or 'commands' not in config:
                return False
            
            # Validate commands
            commands = config.get('commands', [])
            if not isinstance(commands, list):
                return False
            
            for cmd in commands:
                if not isinstance(cmd, dict):
                    return False
                required_fields = ['id', 'name', 'command']
                if not all(field in cmd for field in required_fields):
                    return False
            
            self._save_config(config)
            return True
            
        except Exception as e:
            print(f"Error importing config: {e}")
            return False