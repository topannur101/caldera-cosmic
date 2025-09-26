<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Composer\Write\WriteRegistersBuilder;
use ModbusTcpClient\Composer\Read\ReadCoilsBuilder;
use Carbon\Carbon;

class ModbusConnectionTest extends Command
{
    // ==========================================
    // PROPERTIES SECTION
    // ==========================================
    
    protected $signature = 'modbus:test-connection {--skip-wizard}';
    protected $description = 'Interactive wizard untuk menguji koneksi ke Modbus server dengan konfigurasi detail';
    private $config = [];
    private $lastResponseTime = 0;

    // ==========================================
    // MAIN FLOW METHODS SECTION
    // Method-method utama yang mengatur alur wizard
    // ==========================================

    public function handle()
    {
        $this->displayHeader();

        if ($this->option('skip-wizard')) {
            $this->info('Mode non-interactive tidak tersedia. Gunakan wizard interaktif.');
            return 1;
        }

        // Flow wizard berurutan
        $this->stepBasicSettings();
        $this->stepTestTypeSelection();
        
        // Hanya test 'basic' yang tidak perlu address configuration
        if ($this->config['test_type'] !== 'basic') {
            $this->stepAddressConfiguration();
        }

        $this->stepAdvancedOptions();
        $this->stepConfigurationSummary();
        $this->executeTest();

        return 0;
    }

    private function displayHeader()
    {
        $this->info('==========================================');
        $this->info('   MODBUS CONNECTION TEST WIZARD');
        $this->info('      Granular Configuration Mode');
        $this->info('==========================================');
        $this->newLine();
        $this->info('Wizard ini akan memandu Anda langkah demi langkah');
        $this->info('untuk mengkonfigurasi dan menguji koneksi Modbus.');
        $this->newLine();
    }

    private function stepBasicSettings()
    {
        $this->info('🔡 LANGKAH 1: PENGATURAN KONEKSI DASAR');
        $this->line('─────────────────────────────────────────');
        $this->newLine();

        $this->config['ip'] = $this->askForIpAddress();
        $this->config['port'] = $this->askForPort();
        $this->config['unit_id'] = $this->askForUnitId();

        $this->newLine();
        $this->info('✅ Pengaturan koneksi dasar selesai');
        $this->newLine();
    }

    private function stepTestTypeSelection()
    {
        $this->info('🔧 LANGKAH 2: PEMILIHAN JENIS TEST');
        $this->line('─────────────────────────────────────────');
        $this->newLine();

        $testTypes = [
            'basic' => 'Basic TCP Connection Test (hanya test konektivitas)',
            'holding' => 'Holding Registers (Function Code 03)',
            'input' => 'Input Registers (Function Code 04)',
            'coil' => 'Coils/Discrete Outputs (Function Code 01)',
            'discrete' => 'Discrete Inputs (Function Code 02)',
            'custom' => 'Custom Test (pilih multiple function codes)',
            'diagnostic' => 'Diagnostic Test (comprehensive testing)',
            'monitor' => '🎯 ESP32 Counter Monitor (Real-time)'
        ];

        $this->info('Tersedia jenis test berikut:');
        foreach ($testTypes as $key => $description) {
            $this->line("  {$key}: {$description}");
        }
        $this->newLine();

        $this->config['test_type'] = $this->choice(
            'Pilih jenis test yang ingin dilakukan:',
            array_keys($testTypes)
        );

        $this->info("✅ Dipilih: {$testTypes[$this->config['test_type']]}");
        $this->newLine();
    }

    private function stepAddressConfiguration()
    {
        $this->info('📊 LANGKAH 3: KONFIGURASI ADDRESS DAN REGISTER');
        $this->line('───────────────────────────────────────────────');
        $this->newLine();

        // Dispatch ke method konfigurasi yang sesuai
        switch ($this->config['test_type']) {
            case 'holding':
            case 'input':
                $this->configureRegisterTest();
                break;
            case 'coil':
            case 'discrete':
                $this->configureCoilTest();
                break;
            case 'custom':
                $this->configureCustomTest();
                break;
            case 'diagnostic':
                $this->configureDiagnosticTest();
                break;
            case 'monitor':
                $this->configureMonitorTest();
                break;
        }

        $this->newLine();
        $this->info('✅ Konfigurasi address selesai');
        $this->newLine();
    }

    private function stepAdvancedOptions()
    {
        $this->info('⚙️ LANGKAH 4: PENGATURAN LANJUTAN');
        $this->line('─────────────────────────────────────────');
        $this->newLine();

        $timeout = $this->ask('Timeout untuk request (dalam detik)', '5');
        $this->config['timeout'] = is_numeric($timeout) ? (int)$timeout : 5;

        $retries = $this->ask('Jumlah percobaan ulang jika gagal', '1');
        $this->config['retries'] = is_numeric($retries) ? (int)$retries : 1;

        $dataFormats = ['decimal', 'hexadecimal', 'binary', 'all'];
        $this->config['data_format'] = $this->choice(
            'Format tampilan data:',
            $dataFormats,
            0
        );

        // Khusus untuk monitor type, selalu continuous
        if ($this->config['test_type'] === 'monitor') {
            $this->config['continuous'] = true;
            $interval = $this->ask('Interval monitoring (dalam detik)', '2');
            $this->config['monitor_interval'] = is_numeric($interval) ? (int)$interval : 2;
        } else {
            $this->config['continuous'] = $this->confirm('Aktifkan monitoring berkelanjutan?', false);
            if ($this->config['continuous']) {
                $interval = $this->ask('Interval monitoring (dalam detik)', '5');
                $this->config['monitor_interval'] = is_numeric($interval) ? (int)$interval : 5;
            }
        }

        $this->newLine();
        $this->info('✅ Pengaturan lanjutan selesai');
        $this->newLine();
    }

    private function stepConfigurationSummary()
    {
        $this->info('📋 LANGKAH 5: RINGKASAN KONFIGURASI');
        $this->line('─────────────────────────────────────────');
        $this->newLine();

        $summaryData = [
            ['IP Address', $this->config['ip']],
            ['Port', $this->config['port']],
            ['Unit ID', $this->config['unit_id']],
            ['Test Type', $this->config['test_type']],
            ['Timeout', $this->config['timeout'] . 's'],
            ['Retry Attempts', $this->config['retries']],
            ['Data Format', $this->config['data_format']],
            ['Continuous Mode', $this->config['continuous'] ? 'Ya' : 'Tidak']
        ];

        // Tambahkan info spesifik berdasarkan test type
        if (isset($this->config['start_address'])) {
            $summaryData[] = ['Start Address', $this->config['start_address']];
        }
        if (isset($this->config['quantity'])) {
            $summaryData[] = ['Quantity', $this->config['quantity']];
        }
        if ($this->config['test_type'] === 'monitor') {
            $summaryData[] = ['Counter Register', $this->config['counter_register']];
            $summaryData[] = ['Reset Register', $this->config['reset_register']];
            $summaryData[] = ['Monitor Interval', $this->config['monitor_interval'] . 's'];
        }

        $this->table(['Parameter', 'Value'], $summaryData);
        $this->newLine();
    }

    private function executeTest()
    {
        if (!$this->confirm('🚀 Mulai eksekusi test dengan konfigurasi di atas?')) {
            $this->warn('Test dibatalkan.');
            return;
        }

        $this->newLine();
        $this->info('🔄 MEMULAI EKSEKUSI TEST...');
        $this->line('─────────────────────────────────');
        $this->newLine();

        $startTime = microtime(true);

        // Loop untuk continuous monitoring atau single test
        do {
            $this->performTest();
            
            // Untuk monitor type, tidak perlu sleep karena sudah ada internal loop
            if ($this->config['continuous'] && $this->config['test_type'] !== 'monitor') {
                sleep($this->config['monitor_interval']);
            }
        } while ($this->config['continuous'] && $this->config['test_type'] !== 'monitor');

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        $this->info("✅ Test selesai dalam {$duration}ms");
        $this->info('⏰ ' . Carbon::now()->format('Y-m-d H:i:s'));
    }

    private function performTest()
    {
        $attempts = 0;
        $success = false;

        while ($attempts < $this->config['retries'] && !$success) {
            $attempts++;
            if ($attempts > 1) {
                $this->warn("🔄 Percobaan ke-{$attempts}...");
            }

            try {
                // Dispatch ke method test yang sesuai
                $success = match ($this->config['test_type']) {
                    'basic' => $this->testBasicConnection(),
                    'holding' => $this->testHoldingRegisters(),
                    'input' => $this->testInputRegisters(),
                    'coil' => $this->testCoils(),
                    'discrete' => $this->testDiscreteInputs(),
                    'custom' => $this->testCustom(),
                    'diagnostic' => $this->testDiagnostic(),
                    'monitor' => $this->testMonitor(),
                    default => false
                };
            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
                if ($attempts < $this->config['retries']) sleep(2);
            }
        }

        if (!$success) {
            $this->error("❌ Gagal setelah {$this->config['retries']} percobaan.");
        }

        return $success;
    }

    // ==========================================
    // TEST METHODS SECTION
    // Semua method testing dikelompokkan di sini
    // ==========================================

    private function testBasicConnection()
    {
        $this->info("🔍 Testing basic TCP connection ke {$this->config['ip']}:{$this->config['port']}");
        
        $startTime = microtime(true);
        $socket = @fsockopen(
            $this->config['ip'], 
            $this->config['port'], 
            $errno, 
            $errstr, 
            $this->config['timeout']
        );
        $endTime = microtime(true);

        if ($socket) {
            fclose($socket);
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $this->info("✅ Koneksi berhasil! Response time: {$responseTime}ms");
            return true;
        } else {
            $this->error("❌ Koneksi gagal: {$errstr} (Error {$errno})");
            return false;
        }
    }

    private function testHoldingRegisters()
    {
        $this->info("🔍 Testing Holding Registers");
        
        try {
            $request = ReadRegistersBuilder::newReadHoldingRegisters(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );
            $this->buildRegisterRequest($request);

            $startTime = microtime(true);
            $response = (new NonBlockingClient(['readTimeoutSec' => $this->config['timeout']]))
                ->sendRequests($request->build());
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Holding Registers berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayRegisterData($data);
            return true;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return false;
        }
    }

    private function testInputRegisters()
    {
        $this->info("🔍 Testing Input Registers");
        
        try {
            $request = ReadRegistersBuilder::newReadInputRegisters(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );
            $this->buildRegisterRequest($request);

            $startTime = microtime(true);
            $response = (new NonBlockingClient(['readTimeoutSec' => $this->config['timeout']]))
                ->sendRequests($request->build());
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Input Registers berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayRegisterData($data);
            return true;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return false;
        }
    }

    private function testCoils()
    {
        $this->info("🔍 Testing Coils");
        
        try {
            $request = ReadCoilsBuilder::newReadCoils(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );
            
            for ($i = 0; $i < $this->config['quantity']; $i++) {
                $address = $this->config['start_address'] + $i;
                $request->coil($address, "coil_{$address}");
            }

            $startTime = microtime(true);
            $response = (new NonBlockingClient(['readTimeoutSec' => $this->config['timeout']]))
                ->sendRequests($request->build());
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Coils berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayCoilData($data);
            return true;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return false;
        }
    }

    private function testDiscreteInputs()
    {
        $this->info("🔍 Testing Discrete Inputs");
        
        try {
            $request = ReadCoilsBuilder::newReadInputDiscretes(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );
            
            for ($i = 0; $i < $this->config['quantity']; $i++) {
                $address = $this->config['start_address'] + $i;
                $request->coil($address, "discrete_{$address}");
            }

            $startTime = microtime(true);
            $response = (new NonBlockingClient(['readTimeoutSec' => $this->config['timeout']]))
                ->sendRequests($request->build());
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Discrete Inputs berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayCoilData($data);
            return true;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return false;
        }
    }

    private function testCustom()
    {
        $this->info("🔍 Testing Custom Function Codes");
        $allSuccess = true;
        
        foreach ($this->config['custom_functions'] as $code) {
            $success = match ($code) {
                '01' => $this->testCoils(),
                '02' => $this->testDiscreteInputs(),
                '03' => $this->testHoldingRegisters(),
                '04' => $this->testInputRegisters(),
                default => false
            };
            $allSuccess &= $success;
            $this->newLine();
        }
        
        return $allSuccess;
    }

    private function testDiagnostic()
    {
        $this->info("🔍 Comprehensive Diagnostic Test");
        
        // Test koneksi dasar dulu
        $results = ['Basic' => $this->testBasicConnection()];
        if (!$results['Basic']) return false;

        // Test berbagai address ranges untuk setiap function code
        foreach ($this->config['diagnostic_ranges'] as $type => $addresses) {
            $this->info("Testing {$type}...");
            $results[$type] = [];
            
            foreach ($addresses as $addr) {
                // Backup config sementara
                $tempConfig = $this->config;
                $this->config = array_merge($this->config, [
                    'start_address' => $addr,
                    'quantity' => $this->config['diagnostic_deep'] ? 10 : 1
                ]);
                
                // Test dengan quiet mode (tidak verbose)
                $success = match ($type) {
                    'holding' => $this->testHoldingRegistersQuiet(),
                    'input' => $this->testInputRegistersQuiet(),
                    'coils' => $this->testCoilsQuiet(),
                    'discrete' => $this->testDiscreteInputsQuiet(),
                    default => false
                };
                
                // Restore config
                $this->config = $tempConfig;
                $results[$type][$addr] = $success;
                $this->line($success ? "  ✅ {$addr}" : "  ❌ {$addr}");
            }
            $this->newLine();
        }
        
        $this->displayDiagnosticResults($results);
        return true;
    }

    // ==========================================
    // REAL-TIME MONITOR METHOD (ENHANCED)
    // ==========================================

// ==========================================
    // COMPLETE MONITOR METHODS SECTION
    // Add these methods to your ModbusConnectionTest class
    // ==========================================
//=====================================================================
    // private function testMonitor()
    // {
    //     $this->info("🎯 ESP32 Real-Time Counter Monitoring (Auto Reset 4:29 PM)");
    //     $this->info("📋 Continuous data reading dengan konfirmasi reset setiap hari jam 16:29");
    //     $this->line(str_repeat("─", 80));
        
    //     // Display header
    //     $this->line(sprintf("%-12s | %-15s | %-15s | %-12s | %-15s", 
    //         "Time", "Counter", "Response", "Status", "Next Reset"));
    //     $this->line(str_repeat("─", 80));
        
    //     $lastCounterValue = null;
    //     $resetCount = 0;
    //     $lastResetDate = null;
        
    //     // Force disable PHP limits
    //     set_time_limit(0);
    //     ignore_user_abort(true);
        
    //     try {
    //         // PURE CONTINUOUS DATA READING
    //         while (true) {
    //             $currentDateTime = new \DateTime();
    //             $currentTime = $currentDateTime->format('H:i:s');
    //             $currentDate = $currentDateTime->format('Y-m-d');
                
    //             // Check auto reset condition (4:00 PM with confirmation)
    //             $shouldAutoReset = $this->shouldPerformAutoReset($currentDateTime, $lastResetDate);
                
    //             // Read counter value
    //             $counterValue = $this->readCounterValue();
                
    //             if ($counterValue !== false) {
    //                 $responseTime = $this->getLastResponseTime();
    //                 $status = $this->getCounterStatus($counterValue, $lastCounterValue);
    //                 $nextResetInfo = $this->getNextResetInfo($currentDateTime);
                    
    //                 // Display current reading
    //                 $this->line(sprintf("%-12s | %-15s | %-15s | %-12s | %-15s", 
    //                     $currentTime, 
    //                     "📊 {$counterValue}", 
    //                     "{$responseTime}ms",
    //                     $status,
    //                     $nextResetInfo
    //                 ));
                    
    //                 // Perform auto reset with confirmation if needed
    //                 if ($shouldAutoReset) {
    //                     $resetResult = $this->performAutoReset($currentDateTime);
    //                     if ($resetResult) {
    //                         $lastResetDate = $currentDate;
    //                         $resetCount++;
    //                         $lastCounterValue = 0;
    //                     } else {
    //                         // Reset was declined, mark as processed for today
    //                         $lastResetDate = $currentDate;
    //                     }
    //                 } else {
    //                     $lastCounterValue = $counterValue;
    //                 }
                    
    //             } else {
    //                 $this->error("❌ [{$currentTime}] Failed to read counter - continuing...");
    //             }
                
    //             // Wait for next cycle
    //             $interval = $this->config['monitor_interval'] ?? 2;
    //             sleep($interval);
    //         }
            
    //     } catch (\Exception $e) {
    //         $this->error("❌ Exception: " . $e->getMessage());
    //         $this->error("❌ Restarting monitoring in 3 seconds...");
    //         sleep(3);
            
    //         // RESTART INSTEAD OF STOPPING
    //         return $this->testMonitor();
    //     }
        
    //     // This should NEVER be reached in continuous mode
    //     $this->error("❌ UNEXPECTED: Continuous monitoring stopped!");
    //     return true;
    // }
//=====================================================================
    private function testMonitor()
    {
        // Optional: Set timezone jika belum di-set di php.ini
        // date_default_timezone_set('Asia/Jakarta');

        $this->info("🎯 ESP32 Real-Time Counter Monitoring (Daily Auto Reset @ 00:00)");
        $this->info("📋 Counter update otomatis. Reset otomatis setiap hari jam 00:00 (waktu server lokal).");
        $this->line(str_repeat("─", 95)); // perpanjang karena kolom lebih panjang

        // Display header — ubah jadi "Date & Time" atau tetap "Day & Time" dengan konten lengkap
        $this->line(sprintf("%-35s | %-15s | %-15s | %-12s", 
            "Date, Day & Time", "Counter", "Response", "Status"));
        $this->line(str_repeat("─", 95));
        
        $lastCounterValue = null;
        $cycleCount = 0;
        $resetCount = 0;
        $lastResetKeys = []; // simpan "YYYY-MM-DD|00:00" untuk hindari double reset

        try {
            while (true) {
                // Read current counter value
                $counterValue = $this->readCounterValue();
                
                if ($counterValue !== false) {
                    // 📅 Format: "2025-04-07, Monday, 10:30:45"
                    $dateTimeDisplay = date('Y-m-d, l, H:i:s');

                    $currentDate = date('Y-m-d');   // untuk logika reset
                    $currentTime = date('H:i');     // untuk cek jam 00:00
                    $responseTime = $this->getLastResponseTime();
                    $status = $this->getCounterStatus($counterValue, $lastCounterValue);
                    
                    // Display current reading — dengan format tanggal lengkap
                    $this->line(sprintf("%-35s | %-15s | %-15s | %-12s", 
                        $dateTimeDisplay, 
                        "📊 {$counterValue}", 
                        "{$responseTime}ms",
                        $status
                    ));
                    
                    $lastCounterValue = $counterValue;
                    $cycleCount++;
                    
                    // 🔁 RESET SETIAP HARI JAM 00:00 — HANYA SEKALI PER HARI
                    if ($currentTime === '00:00') {
                        $resetKey = $currentDate . '|00:00';
                        
                        if (!in_array($resetKey, $lastResetKeys)) {
                            // 🕐 Tampilkan waktu lokal server — lengkap dengan tanggal
                            $resetTimeDisplay = date('Y-m-d, l, H:i:s'); // contoh: "2025-04-07, Monday, 00:00:03"

                            $this->newLine();
                            $this->comment("⏰ Reset harian — {$resetTimeDisplay}");

                            $this->info("🔄 Mengirim perintah reset...");
                            if ($this->writeResetRegister()) {
                                $this->info("✅ Reset command sent successfully");
                                $resetCount++;
                                $lastResetKeys[] = $resetKey; // tandai sudah direset hari ini
                            } else {
                                $this->error("❌ Gagal mengirim perintah reset");
                            }

                            $this->line(str_repeat("─", 95)); // sesuaikan panjang
                        }
                    }
                    
                } else {
                    $this->error("❌ Failed to read counter value");
                }
                
                // Wait for next cycle
                $interval = $this->config['monitor_interval'] ?? 2;
                sleep($interval);
            }
        } catch (\Exception $e) {
            $this->error("❌ Monitoring stopped: " . $e->getMessage());
        }
        
        $this->newLine();
        $this->info("📊 Monitoring Summary:");
        $this->info("   • Total monitoring cycles: {$cycleCount}");
        $this->info("   • Total resets performed: {$resetCount}");
        $this->info("   • Final counter value: {$lastCounterValue}");
        $this->info("   • Waktu terakhir: " . date('Y-m-d, l, H:i:s T')); // contoh: 2025-04-07, Monday, 10:30:00 WIB
        
        return true;
    }

    private function getCounterStatus($current, $previous)
    {
        if ($previous === null) {
            return "🚀 START";
        }
        
        if ($current < $previous) {
            return "🔄 RESET!";
        } elseif ($current > $previous) {
            $diff = $current - $previous;
            return "⬆️ UP (+{$diff})";
        } else {
            return "➡️ SAME";
        }
    }

    private function readCounterValue()
    {
        try {
            $counterAddr = $this->config['counter_register'] ?? 0;
            $request = ReadRegistersBuilder::newReadHoldingRegisters(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            )->uint16($counterAddr, 'counter_value');

            $startTime = microtime(true);
            $response = (new NonBlockingClient(['readTimeoutSec' => $this->config['timeout']]))
                ->sendRequests($request->build());
            $endTime = microtime(true);

            // Store response time for display
            $this->lastResponseTime = round(($endTime - $startTime) * 1000, 2);
            
            $data = $response->getData();
            return $data['counter_value'] ?? false;
            
        } catch (\Exception $e) {
            $this->lastResponseTime = 0;
            return false;
        }
    }

    private function getLastResponseTime()
    {
        return $this->lastResponseTime ?? 0;
    }

    private function writeResetRegister()
    {
        try {
            $resetAddr = $this->config['reset_register'] ?? 6;
            
            $this->info("📡 Connecting to {$this->config['ip']}:{$this->config['port']}...");
            
            $request = WriteRegistersBuilder::newWriteMultipleRegisters(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            )->uint16($resetAddr, 1); // Write value 1 to reset register

            $startTime = microtime(true);
            $response = (new NonBlockingClient([
                'readTimeoutSec' => $this->config['timeout'],
                'connectTimeoutSec' => 5
            ]))->sendRequests($request->build());
            $endTime = microtime(true);
            
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $this->info("⚡ Write response time: {$responseTime}ms");

            return true;
            
        } catch (\Exception $e) {
            $this->error("❌ Reset error: " . $e->getMessage());
            $this->error("🔧 Check ESP32 connection and register address");
            return false;
        }
    }
//=========================================================================

    // ==========================================
    // QUIET TEST METHODS SECTION
    // Method test tanpa output verbose (untuk diagnostic)
    // ==========================================
    private function testHoldingRegistersQuiet()
    {
        try {
            $request = ReadRegistersBuilder::newReadHoldingRegisters(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );
            $this->buildRegisterRequest($request);

            $response = (new NonBlockingClient(['readTimeoutSec' => $this->config['timeout']]))
                ->sendRequests($request->build());
                
            $data = $response->getData();
            return !empty($data);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function testInputRegistersQuiet()
    {
        try {
            $request = ReadRegistersBuilder::newReadInputRegisters(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );
            $this->buildRegisterRequest($request);

            $response = (new NonBlockingClient(['readTimeoutSec' => $this->config['timeout']]))
                ->sendRequests($request->build());
                
            $data = $response->getData();
            return !empty($data);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function testCoilsQuiet()
    {
        try {
            $request = ReadCoilsBuilder::newReadCoils(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );
            
            for ($i = 0; $i < $this->config['quantity']; $i++) {
                $address = $this->config['start_address'] + $i;
                $request->coil($address, "coil_{$address}");
            }

            $response = (new NonBlockingClient(['readTimeoutSec' => $this->config['timeout']]))
                ->sendRequests($request->build());
                
            $data = $response->getData();
            return !empty($data);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function testDiscreteInputsQuiet()
    {
        try {
            $request = ReadCoilsBuilder::newReadInputDiscretes(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );
            
            for ($i = 0; $i < $this->config['quantity']; $i++) {
                $address = $this->config['start_address'] + $i;
                $request->coil($address, "discrete_{$address}");
            }

            $response = (new NonBlockingClient(['readTimeoutSec' => $this->config['timeout']]))
                ->sendRequests($request->build());
                
            $data = $response->getData();
            return !empty($data);
        } catch (\Exception $e) {
            return false;
        }
    }

    // ==========================================
    // CONFIGURATION METHODS SECTION
    // Method untuk konfigurasi berbagai jenis test
    // ==========================================

    private function configureRegisterTest()
    {
        $this->info('Konfigurasi untuk Register Test:');
        $this->newLine();
        
        $this->config['start_address'] = (int)$this->validateNumericInput(
            'Starting address (0-65535)', 0, 65535, '0'
        );
        
        $this->config['quantity'] = (int)$this->validateNumericInput(
            'Jumlah register (1-125)', 1, 125, '1'
        );
        
        $types = ['int16', 'uint16', 'int32', 'uint32', 'float32'];
        $this->config['data_type'] = $this->choice('Tipe data:', $types, 0);
        
        $this->config['batch_reading'] = $this->confirm('Batch reading?', true);
    }

    private function configureCoilTest()
    {
        $this->info('Konfigurasi untuk Coil/Discrete Test:');
        $this->newLine();
        
        $this->config['start_address'] = (int)$this->validateNumericInput(
            'Starting address (0-65535)', 0, 65535, '0'
        );
        
        $this->config['quantity'] = (int)$this->validateNumericInput(
            'Jumlah coil (1-2000)', 1, 2000, '1'
        );
    }

    private function configureCustomTest()
    {
        $this->info('Konfigurasi Custom Test:');
        $this->newLine();
        
        $codes = [
            '01' => 'Coils',
            '02' => 'Discrete Inputs',
            '03' => 'Holding Registers',
            '04' => 'Input Registers'
        ];
        
        $this->config['custom_functions'] = [];
        
        do {
            $selected = $this->choice('Pilih function code:', array_keys($codes));
            $this->config['custom_functions'][] = $selected;
            $this->info("✓ {$codes[$selected]} ditambahkan");
        } while ($this->confirm('Tambah function code lagi?'));
        
        // Setelah pilih function codes, konfigurasi address
        $this->configureRegisterTest();
    }

    private function configureDiagnosticTest()
    {
        $this->info('Konfigurasi Diagnostic Test:');
        $this->newLine();
        
        $this->config['diagnostic_deep'] = $this->confirm(
            'Deep diagnostic (test 10 register per address)?', false
        );
        
        // Preset address ranges untuk testing
        $this->config['diagnostic_ranges'] = [
            'coils' => [0, 100, 1000],
            'discrete' => [0, 100, 1000],
            'holding' => [0, 100, 1000, 40001],
            'input' => [0, 100, 1000, 30001]
        ];
        
        $this->info('Address ranges yang akan ditest:');
        foreach ($this->config['diagnostic_ranges'] as $type => $addresses) {
            $this->line("  {$type}: " . implode(', ', $addresses));
        }
    }

    private function configureMonitorTest()
    {
        $this->info('🎯 KONFIGURASI ESP32 COUNTER MONITOR');
        $this->line('──────────────────────────────────────────────');
        $this->newLine();

        $this->config['counter_register'] = (int)$this->validateNumericInput(
            'Masukkan alamat MODBUS untuk register counter (contoh: 0, 100)', 
            0, 65535, '0'
        );

        $this->config['reset_register'] = (int)$this->validateNumericInput(
            'Masukkan alamat MODBUS untuk register reset (contoh: 6)', 
            0, 65535, '6'
        );

        $this->newLine();
        $this->info("✅ Konfigurasi monitor selesai:");
        $this->info("   • Counter Register: {$this->config['counter_register']}");
        $this->info("   • Reset Register: {$this->config['reset_register']}");
        $this->info("   • Mode: Real-time monitoring dengan 'r' untuk reset");
        $this->newLine();
    }

    // ==========================================
    // UTILITY METHODS SECTION
    // Method pembantu dan utilities
    // ==========================================

    private function buildRegisterRequest($request)
    {
        $method = match ($this->config['data_type']) {
            'uint16' => 'uint16',
            'int32' => 'int32',
            'uint32' => 'uint32',
            'float32' => 'float',
            default => 'int16'
        };

        for ($i = 0; $i < $this->config['quantity']; $i++) {
            $addr = $this->config['start_address'] + $i;
            $request->$method($addr, "reg_{$addr}");
        }
    }

    private function displayRegisterData($data)
    {
        if (empty($data)) {
            $this->warn('No data received');
            return;
        }

        $rows = [];
        $idx = 0;
        
        foreach ($data as $key => $val) {
            $addr = $this->config['start_address'] + $idx++;
            $row = ["Reg {$addr}", $key];
            
            match ($this->config['data_format']) {
                'decimal' => $row[] = $val,
                'hexadecimal' => $row[] = sprintf('0x%04X', $val),
                'binary' => $row[] = sprintf('%016b', $val),
                'all' => [
                    $row[] = $val,
                    $row[] = sprintf('0x%04X', $val),
                    $row[] = sprintf('%016b', $val)
                ]
            };
            
            $rows[] = $row;
        }
        
        $headers = ['Address', 'Key'];
        match ($this->config['data_format']) {
            'all' => array_push($headers, 'Decimal', 'Hex', 'Binary'),
            default => $headers[] = ucfirst($this->config['data_format']) . ' Value'
        };
        
        $this->table($headers, $rows);
    }

    private function displayCoilData($data)
    {
        if (empty($data)) {
            $this->warn('No data received');
            return;
        }

        $rows = [];
        $idx = 0;
        
        foreach ($data as $key => $val) {
            $addr = $this->config['start_address'] + $idx++;
            $rows[] = [
                "Coil {$addr}",
                $key,
                $val ? 'TRUE' : 'FALSE',
                $val ? 'ON' : 'OFF'
            ];
        }
        
        $this->table(['Address', 'Key', 'Boolean', 'Status'], $rows);
    }

    private function displayDiagnosticResults($results)
    {
        $this->newLine();
        $this->info('📊 HASIL DIAGNOSTIK:');
        $this->line('──────────────────────');
        
        foreach ($results as $type => $result) {
            if (is_bool($result)) {
                $status = $result ? '✅ PASS' : '❌ FAIL';
                $this->line("{$type}: {$status}");
            } elseif (is_array($result)) {
                $this->line("{$type}:");
                foreach ($result as $addr => $success) {
                    $status = $success ? '✅ OK' : '❌ FAIL';
                    $this->line("  Address {$addr}: {$status}");
                }
            }
        }
        
        $this->newLine();
    }

    private function validateNumericInput($prompt, $min, $max, $default)
    {
        while (true) {
            $val = $this->ask($prompt, $default);
            if (is_numeric($val) && $val >= $min && $val <= $max) {
                return (int)$val;
            }
            $this->error("Nilai harus angka antara {$min}-{$max}");
        }
    }

    private function askForIpAddress()
    {
        while (true) {
            $ip = $this->ask('🌐 Masukkan IP address server Modbus (contoh: 192.168.1.100)');
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                if (strpos($ip, '127.') === 0) {
                    $this->warn("⚠️  Loopback address terdeteksi");
                    if (!$this->confirm('Lanjutkan dengan loopback address?')) {
                        continue;
                    }
                }
                $this->info("✅ IP address valid");
                return $ip;
            }
            
            $this->error('❌ Format IP address tidak valid');
        }
    }

    private function askForPort()
    {
        while (true) {
            $port = $this->ask('🔌 Port (standard Modbus: 502/503)', '503');
            
            if (is_numeric($port) && $port >= 1 && $port <= 65535) {
                $num = (int)$port;
                if (!in_array($num, [502, 503])) {
                    $this->info("ℹ️  Port {$num} bukan port standard Modbus");
                }
                $this->info("✅ Port {$num} diterima");
                return $num;
            }
            
            $this->error('❌ Port harus berupa angka antara 1-65535');
        }
    }

    private function askForUnitId()
    {
        while (true) {
            $id = $this->ask('🏷️  Unit ID (0-255)', '1');
            
            if (is_numeric($id) && $id >= 0 && $id <= 255) {
                $num = (int)$id;
                $this->info("✅ Unit ID {$num} diterima");
                return $num;
            }
            
            $this->error('❌ Unit ID harus berupa angka antara 0-255');
        }
    }
}