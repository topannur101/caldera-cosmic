import json

class ConfigManager:
    def __init__(self, config_file='config.json'):
        self.config_file = config_file
        self.config = self.load_config()

    def load_config(self):
        try:
            with open(self.config_file, 'r') as file:
                return json.load(file)
        except FileNotFoundError:
            return self.create_default_config()

    def save_config(self):
        with open(self.config_file, 'w') as file:
            json.dump(self.config, file, indent=4)

    def create_default_config(self):
        default_config = {
            'app': {'start_on_startup': False},
            'serial': {
                'port': '/dev/ttyUSB0',
                'baud_rate': 9600,
                'connect_on_startup': False,
                'interval': 2000
            },
            'capture': {'camera_index': 0},
            'data_handling': {}
        }
        self.config = default_config
        self.save_config()
        return default_config

    def get_config(self, section=None):
        if section:
            return self.config.get(section, {})
        return self.config

    def update_config(self, section, updates):
        if section not in self.config:
            self.config[section] = {}
        self.config[section].update(updates)
        self.save_config()