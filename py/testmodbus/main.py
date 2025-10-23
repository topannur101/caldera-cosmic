#!/usr/bin/env python3
"""
ESP32 + HF2211A Gateway Simulator (Fixed Windows Version)
Simulator untuk menggantikan ESP32 yang mengirim data via Modbus RTU ke HF2211A gateway
yang kemudian dikonversi ke Modbus TCP untuk dibaca oleh PHP script.

Register Layout:
- Register 0: Counter (auto increment, simulasi sensor count)
- Register 6: Reset register (write 1 untuk reset counter)
- Register 1-5: Additional data registers (optional)
- Register 7-99: Extra registers untuk testing

Author: Assistant
Date: 2025
Fixed untuk Windows networking issues
"""

import threading
import time
import logging
import socket
import subprocess
from datetime import datetime
from dataclasses import dataclass
from typing import Dict, Any, Optional

try:
    # Import yang kompatibel dengan berbagai versi pymodbus
    from pymodbus.server.sync import StartTcpServer
    from pymodbus.device import ModbusDeviceIdentification
    from pymodbus.datastore import ModbusSequentialDataBlock, ModbusSlaveContext, ModbusServerContext
    import colorama
    from colorama import Fore, Back, Style
    
    print("✅ All imports successful!")
    
except ImportError as e:
    print("❌ Missing required packages. Install with:")
    print("pip install pymodbus==2.5.3 colorama")
    print(f"Error details: {e}")
    print("\nAlternative commands to try:")
    print("pip uninstall pymodbus")
    print("pip install pymodbus==2.5.3")
    exit(1)

# Initialize colorama for colored output
colorama.init()

def show_troubleshooting():
    """Show troubleshooting guide"""
    print(f"\n{Fore.CYAN}🔧 TROUBLESHOOTING GUIDE{Style.RESET_ALL}")
    print("═" * 50)
    
    print(f"\n{Fore.RED}❌ Error: IP address not valid in context{Style.RESET_ALL}")
    print("   💡 Gunakan Mode 1 (Quick Start) dengan localhost")
    print("   💡 Atau pilih IP dari daftar yang tersedia di Mode 2")
    
    print(f"\n{Fore.RED}❌ Error: Port already in use{Style.RESET_ALL}")  
    print("   💡 Coba port lain: 502, 1503, 8080")
    print("   💡 Tutup program lain yang menggunakan port")
    print("   💡 Restart komputer jika perlu")
    
    print(f"\n{Fore.RED}❌ Error: Permission denied{Style.RESET_ALL}")
    print("   💡 Jalankan sebagai Administrator")
    print("   💡 Gunakan port > 1024 (contoh: 8080)")
    
    print(f"\n{Fore.YELLOW}⚠️  Windows Firewall Issues{Style.RESET_ALL}")
    print("   💡 Allow Python dalam Windows Firewall")
    print("   💡 Atau temporary disable Windows Firewall")
    
    print(f"\n{Fore.GREEN}✅ Quick Fix Commands:{Style.RESET_ALL}")
    print("   python main.py → 1 (Quick Start)")
    print("   Atau gunakan: localhost + port 8080")
    
    input(f"\n{Fore.CYAN}Tekan Enter untuk kembali ke menu...{Style.RESET_ALL}")

@dataclass
class SimulatorConfig:
    """Konfigurasi untuk simulator"""
    host: str = "127.0.0.1"    # Localhost untuk testing
    port: int = 503            # Port Modbus TCP standard
    counter_register: int = 0
    reset_register: int = 6
    counter_increment: int = 1
    counter_interval: float = 2.0  # Interval increment dalam detik
    counter_max: int = 65535       # Max value untuk counter (16-bit)
    counter_start: int = 0         # Starting value
    log_level: str = "INFO"
    

class NetworkHelper:
    """Helper class untuk network operations"""
    
    @staticmethod
    def get_available_ips():
        """Get available IP addresses di sistem"""
        available_ips = ['127.0.0.1']  # Always available
        
        try:
            # Get hostname IP
            hostname_ip = socket.gethostbyname(socket.gethostname())
            if hostname_ip != '127.0.0.1' and hostname_ip not in available_ips:
                available_ips.append(hostname_ip)
            
            # Try to get all network interfaces (Windows)
            try:
                result = subprocess.run(['ipconfig'], capture_output=True, text=True, timeout=5)
                lines = result.stdout.split('\n')
                for line in lines:
                    if 'IPv4 Address' in line and ':' in line:
                        ip = line.split(':')[-1].strip()
                        if ip and ip != '127.0.0.1' and ip not in available_ips:
                            # Validate IP format
                            if NetworkHelper.validate_ip_address(ip):
                                available_ips.append(ip)
            except:
                pass
                    
        except Exception as e:
            print(f"{Fore.YELLOW}⚠️  Could not detect network IPs: {e}{Style.RESET_ALL}")
        
        return available_ips

    @staticmethod
    def validate_ip_address(ip):
        """Validate IP address"""
        try:
            socket.inet_aton(ip)
            parts = ip.split('.')
            return len(parts) == 4 and all(0 <= int(part) <= 255 for part in parts)
        except:
            return False

    @staticmethod
    def test_ip_binding(ip, port):
        """Test if we can bind to IP:Port"""
        try:
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            sock.bind((ip, port))
            sock.close()
            return True
        except Exception as e:
            return False, str(e)

    @staticmethod
    def find_free_port(host='127.0.0.1', start_port=503, max_attempts=10):
        """Find a free port starting from start_port"""
        for port in range(start_port, start_port + max_attempts):
            result = NetworkHelper.test_ip_binding(host, port)
            if result is True:
                return port
        return None


class ModbusESP32Simulator:
    """
    Simulator ESP32 + HF2211A Gateway (Fixed Windows Version)
    
    Mensimulasikan:
    1. Counter sensor yang terus bertambah di register 0
    2. Reset functionality via register 6 
    3. Additional registers untuk testing
    4. Real-time logging dan monitoring
    """
    
    def __init__(self, config: SimulatorConfig = None):
        self.config = config or SimulatorConfig()
        self.setup_logging()
        
        # State variables
        self.counter_value = self.config.counter_start
        self.is_running = False
        self.last_reset_time = None
        self.total_resets = 0
        self.start_time = datetime.now()
        
        # Threading untuk counter simulation
        self.counter_thread = None
        self.stop_event = threading.Event()
        
        # Setup Modbus data store
        self.setup_datastore()
        
        self.logger.info(f"{Fore.GREEN}🚀 ESP32 Simulator initialized{Style.RESET_ALL}")
        self.logger.info(f"   📊 Counter Register: {self.config.counter_register}")
        self.logger.info(f"   🔄 Reset Register: {self.config.reset_register}")
        self.logger.info(f"   ⏱️  Increment Interval: {self.config.counter_interval}s")

    def setup_logging(self):
        """Setup logging dengan format yang bagus"""
        logging.basicConfig(
            level=getattr(logging, self.config.log_level),
            format='%(asctime)s - %(levelname)s - %(message)s',
            datefmt='%H:%M:%S'
        )
        self.logger = logging.getLogger(__name__)

    def setup_datastore(self):
        """Setup Modbus datastore dengan register-register yang diperlukan"""
        
        # Initialize register values (100 registers)
        register_count = 100
        initial_values = [0] * register_count
        
        # Set specific values
        initial_values[self.config.counter_register] = self.counter_value  # Counter register
        initial_values[self.config.reset_register] = 0                     # Reset register
        
        # Additional test registers dengan nilai unik
        test_values = {
            1: 1001, 2: 2002, 3: 3003, 4: 4004, 5: 5005,
            7: 777, 8: 888, 9: 999, 10: 1010,
            20: 2020, 50: 5050, 99: 9999
        }
        
        for reg, val in test_values.items():
            if reg < register_count:
                initial_values[reg] = val
        
        # Create data blocks
        holding_registers = ModbusSequentialDataBlock(0, initial_values)
        input_registers = ModbusSequentialDataBlock(0, initial_values)  # Same data
        
        # Coils (100 coils, semua False initially)
        coils = ModbusSequentialDataBlock(0, [False] * 100)
        
        # Discrete inputs (100 discrete, alternating pattern)
        discrete_pattern = [i % 2 == 0 for i in range(100)]
        discrete_inputs = ModbusSequentialDataBlock(0, discrete_pattern)
        
        # Create slave context
        self.slave_context = ModbusSlaveContext(
            di=discrete_inputs,    # Discrete Inputs (FC 02)
            co=coils,             # Coils (FC 01)
            hr=holding_registers, # Holding Registers (FC 03)
            ir=input_registers    # Input Registers (FC 04)
        )
        
        # Server context
        self.server_context = ModbusServerContext(slaves=self.slave_context, single=True)
        
        self.logger.info(f"{Fore.GREEN}✅ Datastore initialized with {register_count} registers{Style.RESET_ALL}")

    def update_counter_register(self):
        """Update counter value di datastore"""
        try:
            # Update holding register (FC 03)
            self.slave_context.setValues(3, self.config.counter_register, [self.counter_value])
            # Also update input register (FC 04) 
            self.slave_context.setValues(4, self.config.counter_register, [self.counter_value])
            
        except Exception as e:
            self.logger.error(f"❌ Error updating counter register: {e}")

    def check_reset_register(self):
        """Cek apakah ada reset command di register 6"""
        try:
            # Read reset register value (FC 03)
            reset_values = self.slave_context.getValues(3, self.config.reset_register, 1)
            
            if reset_values and reset_values[0] == 1:
                # Reset detected!
                self.counter_value = self.config.counter_start
                self.total_resets += 1
                self.last_reset_time = datetime.now()
                
                # Clear reset register back to 0
                self.slave_context.setValues(3, self.config.reset_register, [0])
                
                # Update counter register
                self.update_counter_register()
                
                self.logger.warning(f"{Fore.YELLOW}🔄 COUNTER RESET! Value: {self.counter_value} | Total resets: {self.total_resets}{Style.RESET_ALL}")
                
                return True
                
        except Exception as e:
            self.logger.error(f"❌ Error checking reset register: {e}")
            
        return False

    def counter_simulation_loop(self):
        """Loop simulasi counter yang berjalan di background thread"""
        self.logger.info(f"{Fore.CYAN}🔄 Counter simulation started{Style.RESET_ALL}")
        
        while not self.stop_event.is_set():
            try:
                # Check for reset command first
                if self.check_reset_register():
                    # If reset occurred, continue without incrementing
                    time.sleep(self.config.counter_interval)
                    continue
                
                # Increment counter
                self.counter_value += self.config.counter_increment
                
                # Handle overflow (16-bit register)
                if self.counter_value > self.config.counter_max:
                    self.counter_value = 0
                    self.logger.info(f"{Fore.MAGENTA}🔄 Counter overflow, reset to 0{Style.RESET_ALL}")
                
                # Update register
                self.update_counter_register()
                
                # Log counter value periodically
                if self.counter_value % 10 == 0 or self.counter_value < 10:
                    runtime = datetime.now() - self.start_time
                    self.logger.info(f"{Fore.GREEN}📊 Counter: {self.counter_value} | Runtime: {str(runtime).split('.')[0]}{Style.RESET_ALL}")
                
                # Wait for next increment
                time.sleep(self.config.counter_interval)
                
            except Exception as e:
                self.logger.error(f"❌ Error in counter simulation: {e}")
                time.sleep(1)  # Wait a bit before retrying

    def print_status_header(self):
        """Print header informasi simulator"""
        print(f"\n{Fore.CYAN}{'='*70}{Style.RESET_ALL}")
        print(f"{Fore.CYAN}🔌 ESP32 + HF2211A GATEWAY SIMULATOR{Style.RESET_ALL}")
        print(f"{Fore.CYAN}{'='*70}{Style.RESET_ALL}")
        print(f"📡 Server: {self.config.host}:{self.config.port}")
        print(f"📊 Counter Register: {self.config.counter_register}")
        print(f"🔄 Reset Register: {self.config.reset_register}")
        print(f"⏱️  Increment Interval: {self.config.counter_interval}s")
        print(f"🎯 Max Counter Value: {self.config.counter_max}")
        print(f"\n{Fore.YELLOW}📋 CARA PENGGUNAAN:{Style.RESET_ALL}")
        print("1. Gunakan PHP script untuk connect ke simulator")
        print("2. Register 0: Counter otomatis (auto-increment)")
        print("3. Write value 1 ke Register 6: Reset counter ke 0")
        print("4. Register 1-5, 7-99: Test registers dengan nilai tetap")
        print(f"\n{Fore.GREEN}✅ Server siap menerima koneksi Modbus TCP!{Style.RESET_ALL}")
        print(f"   Tekan Ctrl+C untuk stop")
        print(f"{Fore.CYAN}{'='*70}{Style.RESET_ALL}\n")

    def print_statistics(self):
        """Print statistik simulator"""
        runtime = datetime.now() - self.start_time
        reset_info = f"Last: {self.last_reset_time.strftime('%H:%M:%S')}" if self.last_reset_time else "Never"
        
        print(f"\n{Fore.BLUE}📊 SIMULATOR STATISTICS:{Style.RESET_ALL}")
        print(f"   ⏱️  Runtime: {str(runtime).split('.')[0]}")
        print(f"   📊 Current Counter: {self.counter_value}")
        print(f"   🔄 Total Resets: {self.total_resets}")
        print(f"   📅 {reset_info}")

    def run_server(self):
        """Run Modbus TCP server with improved error handling"""
        
        # Setup device identification
        identity = ModbusDeviceIdentification()
        identity.VendorName = 'ESP32 Simulator'
        identity.ProductCode = 'SIM-ESP32-HF2211A'
        identity.VendorUrl = 'https://github.com/simulator'
        identity.ProductName = 'ESP32+HF2211A Gateway Simulator'
        identity.ModelName = 'Modbus TCP Simulator'
        identity.MajorMinorRevision = '1.0.0'
        
        try:
            # Start counter simulation thread
            self.counter_thread = threading.Thread(target=self.counter_simulation_loop)
            self.counter_thread.daemon = True
            self.counter_thread.start()
            self.is_running = True
            
            # Print status
            self.print_status_header()
            
            # Start server (blocking call)
            self.logger.info(f"{Fore.GREEN}🚀 Starting Modbus TCP server...{Style.RESET_ALL}")
            
            StartTcpServer(
                context=self.server_context,
                identity=identity,
                address=(self.config.host, self.config.port),
            )
            
        except OSError as e:
            error_code = str(e)
            
            # Handle different Windows networking errors
            if "10049" in error_code or "not valid in its context" in error_code:
                self.logger.error(f"❌ IP Address Error: {self.config.host} tidak valid pada sistem ini")
                self.logger.info(f"{Fore.YELLOW}💡 Mencoba fallback ke localhost...{Style.RESET_ALL}")
                
                # Try fallback to localhost
                try:
                    self.config.host = "127.0.0.1"
                    self.logger.info(f"{Fore.GREEN}🚀 Retrying with localhost (127.0.0.1)...{Style.RESET_ALL}")
                    
                    StartTcpServer(
                        context=self.server_context,
                        identity=identity,
                        address=(self.config.host, self.config.port),
                    )
                except Exception as fallback_error:
                    self.show_error_solutions("IP_BINDING", str(fallback_error))
                    
            elif "10048" in error_code or "already in use" in error_code:
                self.show_error_solutions("PORT_IN_USE", error_code)
                
            elif "10013" in error_code or "permission denied" in error_code.lower():
                self.show_error_solutions("PERMISSION", error_code)
                
            else:
                self.logger.error(f"❌ Server error: {e}")
                self.show_error_solutions("GENERAL", error_code)
                
        except KeyboardInterrupt:
            self.logger.info(f"{Fore.YELLOW}🛑 Shutdown requested by user{Style.RESET_ALL}")
        except Exception as e:
            self.logger.error(f"❌ Unexpected server error: {e}")
            self.show_error_solutions("GENERAL", str(e))
        finally:
            self.shutdown()

    def show_error_solutions(self, error_type, error_detail):
        """Show specific error solutions"""
        self.logger.error(f"❌ Error details: {error_detail}")
        
        print(f"\n{Fore.CYAN}💡 SOLUSI UNTUK ERROR INI:{Style.RESET_ALL}")
        
        if error_type == "IP_BINDING":
            print(f"1. {Fore.GREEN}Gunakan Mode 1 (Quick Start){Style.RESET_ALL} - paling aman")
            print("2. Restart simulator dan pilih IP dari daftar yang tersedia")
            print("3. Pastikan tidak ada typo dalam IP address")
            
        elif error_type == "PORT_IN_USE":
            free_port = NetworkHelper.find_free_port(self.config.host)
            if free_port:
                print(f"1. {Fore.GREEN}Gunakan port {free_port}{Style.RESET_ALL} (tersedia)")
            print("2. Gunakan port: 502, 8080, 1503, 9000")
            print("3. Tutup program lain yang menggunakan port")
            print("4. Restart komputer jika perlu")
            
        elif error_type == "PERMISSION":
            print("1. Jalankan sebagai Administrator (Run as Administrator)")
            print("2. Gunakan port > 1024 (contoh: 8080)")
            print("3. Disable Windows Firewall sementara")
            
        else:
            print("1. Restart simulator")
            print("2. Gunakan Mode 1 (Quick Start)")
            print("3. Cek troubleshooting guide")
        
        print(f"\n{Fore.YELLOW}🔄 Cara restart simulator:{Style.RESET_ALL}")
        print("python main.py → 1 (Quick Start)")

    def shutdown(self):
        """Graceful shutdown"""
        self.logger.info(f"{Fore.YELLOW}🛑 Shutting down simulator...{Style.RESET_ALL}")
        
        self.is_running = False
        self.stop_event.set()
        
        if self.counter_thread and self.counter_thread.is_alive():
            self.counter_thread.join(timeout=5)
            
        self.print_statistics()
        self.logger.info(f"{Fore.GREEN}✅ Simulator stopped gracefully{Style.RESET_ALL}")


class InteractiveMenu:
    """Menu interaktif untuk konfigurasi simulator dengan IP detection"""
    
    def __init__(self):
        self.config = SimulatorConfig()
    
    def display_banner(self):
        print(f"{Fore.CYAN}")
        print("╔═══════════════════════════════════════════════════════════════╗")
        print("║              ESP32 + HF2211A GATEWAY SIMULATOR                ║") 
        print("║                   (Fixed Windows Version)                     ║")
        print("║  Simulator untuk menggantikan ESP32 + HF2211A Gateway        ║")
        print("║  Compatible dengan PHP Modbus Connection Test                 ║")
        print("╚═══════════════════════════════════════════════════════════════╝")
        print(f"{Style.RESET_ALL}")
    
    def confirm(self, question: str) -> bool:
        """Helper untuk yes/no confirmation"""
        while True:
            answer = input(f"{question} (yes/no): ").strip().lower()
            if answer in ['yes', 'y']:
                return True
            elif answer in ['no', 'n']:  
                return False
            else:
                print("Please answer 'yes' or 'no'")

    def get_user_config(self):
        """Konfigurasi interaktif dengan validasi IP dan network detection"""
        print(f"\n{Fore.GREEN}⚙️  KONFIGURASI SIMULATOR (MODE 2){Style.RESET_ALL}")
        print("─" * 50)
        
        # Show available IPs
        available_ips = NetworkHelper.get_available_ips()
        print(f"\n{Fore.CYAN}📡 IP addresses yang tersedia pada sistem ini:{Style.RESET_ALL}")
        for i, ip in enumerate(available_ips):
            status = "✅ RECOMMENDED" if ip == "127.0.0.1" else "⚠️  Advanced"
            print(f"  {i+1}. {ip:<15} ({status})")
        print(f"  {len(available_ips)+1}. Custom IP (expert mode)")
        
        # IP Configuration dengan validasi
        while True:
            choice = input(f"\n🌐 Pilih IP address (1-{len(available_ips)+1}) [1 for localhost]: ").strip()
            
            if not choice:  # Default to localhost
                selected_ip = "127.0.0.1"
                break
            elif choice.isdigit():
                idx = int(choice) - 1
                if 0 <= idx < len(available_ips):
                    selected_ip = available_ips[idx]
                    break
                elif idx == len(available_ips):  # Custom IP
                    custom_ip = input("Masukkan custom IP address: ").strip()
                    if NetworkHelper.validate_ip_address(custom_ip):
                        selected_ip = custom_ip
                        break
                    else:
                        print(f"{Fore.RED}❌ IP address tidak valid{Style.RESET_ALL}")
                        continue
                else:
                    print(f"{Fore.RED}❌ Pilihan tidak valid{Style.RESET_ALL}")
                    continue
            else:  # Direct IP input
                if NetworkHelper.validate_ip_address(choice):
                    selected_ip = choice
                    break
                else:
                    print(f"{Fore.RED}❌ IP address tidak valid{Style.RESET_ALL}")
                    continue
        
        # Test IP binding
        test_port = 50999  # High port for testing
        can_bind = NetworkHelper.test_ip_binding(selected_ip, test_port)
        if isinstance(can_bind, tuple):  # Error occurred
            print(f"{Fore.YELLOW}⚠️  Warning: Cannot bind to {selected_ip}:{test_port}{Style.RESET_ALL}")
            print(f"   Reason: {can_bind[1]}")
            if not self.confirm(f"Continue with {selected_ip} anyway?"):
                return self.get_user_config()  # Restart configuration
        
        self.config.host = selected_ip
        print(f"{Fore.GREEN}✅ IP Address: {selected_ip}{Style.RESET_ALL}")
        
        # Port Configuration dengan validasi dan suggestions 
        while True:
            suggested_ports = [503, 502, 8080, 1503]
            print(f"\n🔌 Port suggestions: {', '.join(map(str, suggested_ports))}")
            port_input = input(f"Port (default: {self.config.port}): ").strip()
            
            if not port_input:
                port = self.config.port
                break
            elif port_input.isdigit():
                port = int(port_input)
                if 1 <= port <= 65535:
                    # Test port availability
                    can_bind = NetworkHelper.test_ip_binding(selected_ip, port)
                    if isinstance(can_bind, tuple):
                        print(f"{Fore.YELLOW}⚠️  Port {port} might be in use: {can_bind[1]}{Style.RESET_ALL}")
                        
                        # Suggest alternative port
                        free_port = NetworkHelper.find_free_port(selected_ip, port + 1)
                        if free_port:
                            print(f"💡 Suggested free port: {free_port}")
                            if self.confirm(f"Use port {free_port} instead?"):
                                port = free_port
                                break
                        
                        if not self.confirm("Use this port anyway?"):
                            continue
                    break
                else:
                    print(f"{Fore.RED}❌ Port harus antara 1-65535{Style.RESET_ALL}")
            else:
                print(f"{Fore.RED}❌ Port harus berupa angka{Style.RESET_ALL}")
        
        self.config.port = port
        print(f"{Fore.GREEN}✅ Port: {port}{Style.RESET_ALL}")
        
        # Counter Configuration
        interval = input(f"⏱️  Counter increment interval dalam detik (default: {self.config.counter_interval}): ").strip()
        if interval:
            try:
                self.config.counter_interval = float(interval)
                print(f"{Fore.GREEN}✅ Interval: {self.config.counter_interval}s{Style.RESET_ALL}")
            except ValueError:
                print(f"{Fore.YELLOW}⚠️  Invalid interval, using default: {self.config.counter_interval}s{Style.RESET_ALL}")
        
        # Starting value
        start_val = input(f"🎯 Counter starting value (default: {self.config.counter_start}): ").strip()
        if start_val and start_val.isdigit():
            self.config.counter_start = int(start_val)
            print(f"{Fore.GREEN}✅ Starting value: {self.config.counter_start}{Style.RESET_ALL}")
            
        print(f"\n{Fore.GREEN}✅ Konfigurasi selesai!{Style.RESET_ALL}")
        return self.config
    
    def run(self):
        """Run interactive menu"""
        self.display_banner()
        
        print(f"{Fore.YELLOW}📋 MODE OPERASI:{Style.RESET_ALL}")
        print("1. Quick Start (konfigurasi default - RECOMMENDED)")
        print("2. Custom Configuration (advanced settings)")  
        print("3. Troubleshooting Guide")
        print("4. Exit")
        
        choice = input(f"\n{Fore.CYAN}Pilih mode (1-4): {Style.RESET_ALL}").strip()
        
        if choice == "1":
            print(f"{Fore.GREEN}🚀 Starting dengan konfigurasi default (localhost:503)...{Style.RESET_ALL}")
            return self.config
        elif choice == "2":
            return self.get_user_config()
        elif choice == "3":
            show_troubleshooting()
            return self.run()  # Return to menu
        elif choice == "4":
            print(f"{Fore.YELLOW}👋 Goodbye!{Style.RESET_ALL}")
            return None
        else:
            print(f"{Fore.RED}❌ Pilihan tidak valid{Style.RESET_ALL}")
            return self.run()


def main():
    """Main function"""
    try:
        print(f"{Fore.GREEN}🎉 Starting ESP32 Simulator (Fixed Windows Version)...{Style.RESET_ALL}")
        
        # Interactive menu
        menu = InteractiveMenu()
        config = menu.run()
        
        if config is None:
            return
        
        # Create and run simulator
        simulator = ModbusESP32Simulator(config)
        
        # Run server (blocking call)
        simulator.run_server()
        
    except KeyboardInterrupt:
        print(f"\n{Fore.YELLOW}🛑 Program dihentikan oleh user{Style.RESET_ALL}")
    except Exception as e:
        print(f"{Fore.RED}❌ Fatal error: {e}{Style.RESET_ALL}")
        print(f"\n{Fore.CYAN}🆘 Butuh bantuan? Coba:{Style.RESET_ALL}")
        print("python main.py → 3 (Troubleshooting Guide)")


if __name__ == "__main__":
    main()