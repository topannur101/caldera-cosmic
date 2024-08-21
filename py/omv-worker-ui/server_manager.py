import subprocess
import sys

class ServerManager:
    def __init__(self):
        self.server_process = None

    def start_server(self):
        if self.server_process is None or self.server_process.poll() is not None:
            self.server_process = subprocess.Popen([sys.executable, 'server.py'])
            return True
        return False

    def stop_server(self):
        if self.server_process:
            self.server_process.terminate()
            self.server_process = None
            return True
        return False

    def is_running(self):
        return self.server_process is not None and self.server_process.poll() is None
