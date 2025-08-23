<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use ModbusTcpClient\Composer\Read\ReadCoilsBuilder;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Network\NonBlockingClient;

class ModbusConnectionTest extends Command
{
    protected $signature = 'modbus:test-connection 
                            {--skip-wizard : Skip interactive wizard}';

    protected $description = 'Interactive wizard untuk menguji koneksi ke Modbus server dengan konfigurasi detail';

    private $config = [];

    public function handle()
    {
        $this->displayHeader();

        if ($this->option('skip-wizard')) {
            $this->info('Mode non-interactive tidak tersedia. Gunakan wizard interaktif.');

            return 1;
        }

        // Step 1: Basic Connection Settings
        $this->stepBasicSettings();

        // Step 2: Test Type Selection
        $this->stepTestTypeSelection();

        // Step 3: Address Configuration (if needed)
        if ($this->config['test_type'] !== 'basic') {
            $this->stepAddressConfiguration();
        }

        // Step 4: Advanced Options
        $this->stepAdvancedOptions();

        // Step 5: Configuration Summary
        $this->stepConfigurationSummary();

        // Step 6: Execute Test
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
        $this->info('📡 LANGKAH 1: PENGATURAN KONEKSI DASAR');
        $this->line('─────────────────────────────────────────');
        $this->newLine();

        // IP Address
        $this->config['ip'] = $this->askForIpAddress();

        // Port
        $this->config['port'] = $this->askForPort();

        // Unit ID
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
        $this->line('─────────────────────────────────────────────');
        $this->newLine();

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
        }

        $this->newLine();
        $this->info('✅ Konfigurasi address selesai');
        $this->newLine();
    }

    private function stepAdvancedOptions()
    {
        $this->info('⚙️  LANGKAH 4: PENGATURAN LANJUTAN');
        $this->line('─────────────────────────────────────────');
        $this->newLine();

        // Timeout
        $timeout = $this->ask('Timeout untuk request (dalam detik)', '5');
        $this->config['timeout'] = is_numeric($timeout) ? (int) $timeout : 5;

        // Retry attempts
        $retries = $this->ask('Jumlah percobaan ulang jika gagal', '1');
        $this->config['retries'] = is_numeric($retries) ? (int) $retries : 1;

        // Data format
        $dataFormats = ['decimal', 'hexadecimal', 'binary', 'all'];
        $this->config['data_format'] = $this->choice(
            'Format tampilan data:',
            $dataFormats,
            0
        );

        // Continuous monitoring
        $this->config['continuous'] = $this->confirm('Aktifkan monitoring berkelanjutan?', false);

        if ($this->config['continuous']) {
            $interval = $this->ask('Interval monitoring (dalam detik)', '5');
            $this->config['monitor_interval'] = is_numeric($interval) ? (int) $interval : 5;
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
            ['Timeout', $this->config['timeout'].'s'],
            ['Retry Attempts', $this->config['retries']],
            ['Data Format', $this->config['data_format']],
            ['Continuous Mode', $this->config['continuous'] ? 'Ya' : 'Tidak'],
        ];

        if (isset($this->config['start_address'])) {
            $summaryData[] = ['Start Address', $this->config['start_address']];
        }
        if (isset($this->config['quantity'])) {
            $summaryData[] = ['Quantity', $this->config['quantity']];
        }
        if (isset($this->config['data_type'])) {
            $summaryData[] = ['Data Type', $this->config['data_type']];
        }

        $this->table(['Parameter', 'Value'], $summaryData);
        $this->newLine();
    }

    private function executeTest()
    {
        if (! $this->confirm('🚀 Mulai eksekusi test dengan konfigurasi di atas?')) {
            $this->warn('Test dibatalkan.');

            return;
        }

        $this->newLine();
        $this->info('🔄 MEMULAI EKSEKUSI TEST...');
        $this->line('─────────────────────────────────');
        $this->newLine();

        $startTime = microtime(true);

        do {
            $this->performTest();

            if ($this->config['continuous']) {
                $this->info("⏰ Menunggu {$this->config['monitor_interval']} detik untuk test berikutnya...");
                sleep($this->config['monitor_interval']);
                $this->newLine();
            }
        } while ($this->config['continuous'] && ! $this->shouldStop());

        $endTime = microtime(true);
        $totalDuration = round(($endTime - $startTime) * 1000, 2);

        $this->newLine();
        $this->info("✅ Test selesai dalam {$totalDuration}ms");
        $this->info('⏰ Timestamp: '.Carbon::now()->format('Y-m-d H:i:s'));
    }

    private function shouldStop()
    {
        // In continuous mode, we could add logic to check for user interrupt
        // For now, just return false to continue indefinitely
        return false;
    }

    private function performTest()
    {
        $attempts = 0;
        $success = false;

        while ($attempts < $this->config['retries'] && ! $success) {
            $attempts++;

            if ($attempts > 1) {
                $this->warn("🔄 Percobaan ke-{$attempts}...");
            }

            try {
                switch ($this->config['test_type']) {
                    case 'basic':
                        $success = $this->testBasicConnection();
                        break;
                    case 'holding':
                        $success = $this->testHoldingRegisters();
                        break;
                    case 'input':
                        $success = $this->testInputRegisters();
                        break;
                    case 'coil':
                        $success = $this->testCoils();
                        break;
                    case 'discrete':
                        $success = $this->testDiscreteInputs();
                        break;
                    case 'custom':
                        $success = $this->testCustom();
                        break;
                    case 'diagnostic':
                        $success = $this->testDiagnostic();
                        break;
                }
            } catch (\Exception $e) {
                $this->error("❌ Error pada percobaan ke-{$attempts}: ".$e->getMessage());
                if ($attempts < $this->config['retries']) {
                    $this->info('⏳ Menunggu 2 detik sebelum percobaan berikutnya...');
                    sleep(2);
                }
            }
        }

        if (! $success && $attempts >= $this->config['retries']) {
            $this->error("❌ Test gagal setelah {$this->config['retries']} percobaan");
        }

        return $success;
    }

    private function configureRegisterTest()
    {
        $this->info('Konfigurasi untuk Register Test:');
        $this->newLine();

        // Start address
        while (true) {
            $address = $this->ask('Starting address (0-65535)', '0');
            if (is_numeric($address) && $address >= 0 && $address <= 65535) {
                $this->config['start_address'] = (int) $address;
                break;
            }
            $this->error('Address harus berupa angka antara 0-65535');
        }

        // Quantity
        while (true) {
            $quantity = $this->ask('Jumlah register yang akan dibaca (1-125)', '1');
            if (is_numeric($quantity) && $quantity >= 1 && $quantity <= 125) {
                $this->config['quantity'] = (int) $quantity;
                break;
            }
            $this->error('Quantity harus berupa angka antara 1-125');
        }

        // Data type
        $dataTypes = ['int16', 'uint16', 'int32', 'uint32', 'float32'];
        $this->config['data_type'] = $this->choice('Tipe data register:', $dataTypes, 0);

        // Batch reading option
        $this->config['batch_reading'] = $this->confirm('Baca register secara batch (lebih efisien)?', true);
    }

    private function configureCoilTest()
    {
        $this->info('Konfigurasi untuk Coil/Discrete Test:');
        $this->newLine();

        // Start address
        while (true) {
            $address = $this->ask('Starting address (0-65535)', '0');
            if (is_numeric($address) && $address >= 0 && $address <= 65535) {
                $this->config['start_address'] = (int) $address;
                break;
            }
            $this->error('Address harus berupa angka antara 0-65535');
        }

        // Quantity
        while (true) {
            $quantity = $this->ask('Jumlah coil yang akan dibaca (1-2000)', '1');
            if (is_numeric($quantity) && $quantity >= 1 && $quantity <= 2000) {
                $this->config['quantity'] = (int) $quantity;
                break;
            }
            $this->error('Quantity harus berupa angka antara 1-2000');
        }
    }

    private function configureCustomTest()
    {
        $this->info('Konfigurasi untuk Custom Test:');
        $this->newLine();

        $functionCodes = [
            '01' => 'Read Coils',
            '02' => 'Read Discrete Inputs',
            '03' => 'Read Holding Registers',
            '04' => 'Read Input Registers',
        ];

        $this->config['custom_functions'] = [];

        do {
            $selected = $this->choice('Pilih function code:', array_keys($functionCodes));
            $this->config['custom_functions'][] = $selected;
            $this->info("Ditambahkan: {$functionCodes[$selected]}");
        } while ($this->confirm('Tambah function code lain?'));

        $this->configureRegisterTest(); // Use same address config
    }

    private function configureDiagnosticTest()
    {
        $this->info('Konfigurasi untuk Diagnostic Test:');
        $this->newLine();
        $this->info('Test diagnostik akan mencoba semua function code dengan berbagai address range.');

        $this->config['diagnostic_deep'] = $this->confirm('Aktifkan deep diagnostic (test lebih menyeluruh)?', false);
        $this->config['diagnostic_ranges'] = [
            'coils' => [0, 100, 1000],
            'discrete' => [0, 100, 1000],
            'holding' => [0, 100, 1000, 40001],
            'input' => [0, 100, 1000, 30001],
        ];
    }

    private function askForIpAddress()
    {
        while (true) {
            $ip = $this->ask('🌐 Masukkan IP address server Modbus (contoh: 192.168.1.100)');

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                if (strpos($ip, '127.') === 0) {
                    $this->warn("⚠️  IP address {$ip} adalah loopback address");
                    if ($this->confirm('Lanjutkan dengan loopback address?')) {
                        return $ip;
                    }

                    continue;
                }
                $this->info("✅ IP address {$ip} valid");

                return $ip;
            }

            $this->error('❌ Format IP address tidak valid. Silakan coba lagi.');
        }
    }

    private function askForPort()
    {
        while (true) {
            $port = $this->ask('🔌 Masukkan port server (standard Modbus: 502/503)', '503');

            if (is_numeric($port) && $port >= 1 && $port <= 65535) {
                $portNum = (int) $port;
                if ($portNum != 502 && $portNum != 503) {
                    $this->info("ℹ️  Info: Port {$portNum} bukan port standard Modbus (502/503)");
                }
                $this->info("✅ Port {$portNum} diterima");

                return $portNum;
            }

            $this->error('❌ Port harus berupa angka antara 1-65535');
        }
    }

    private function askForUnitId()
    {
        while (true) {
            $unitId = $this->ask('🏷️  Masukkan Unit ID Modbus (0-255)', '1');

            if (is_numeric($unitId) && $unitId >= 0 && $unitId <= 255) {
                $id = (int) $unitId;
                $this->info("✅ Unit ID {$id} diterima");

                return $id;
            }

            $this->error('❌ Unit ID harus berupa angka antara 0-255');
        }
    }

    private function test_basic_connection()
    {
        $ip = $this->config['ip'];
        $port = $this->config['port'];

        $this->info("🔍 Testing basic TCP connection ke {$ip}:{$port}");

        $startTime = microtime(true);
        $socket = @fsockopen($ip, $port, $errno, $errstr, $this->config['timeout']);
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

    private function test_holding_registers()
    {
        $this->info('🔍 Testing Holding Registers');

        try {
            $request = ReadRegistersBuilder::newReadHoldingRegisters(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );

            $this->buildRegisterRequest($request);

            $startTime = microtime(true);
            $response = (new NonBlockingClient([
                'readTimeoutSec' => $this->config['timeout'],
            ]))->sendRequests($request->build());
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Holding Registers berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayRegisterData($data);

            return true;

        } catch (\Exception $e) {
            $this->error('❌ Error membaca Holding Registers: '.$e->getMessage());

            return false;
        }
    }

    private function test_input_registers()
    {
        $this->info('🔍 Testing Input Registers');

        try {
            $request = ReadRegistersBuilder::newReadInputRegisters(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );

            $this->buildRegisterRequest($request);

            $startTime = microtime(true);
            $response = (new NonBlockingClient([
                'readTimeoutSec' => $this->config['timeout'],
            ]))->sendRequests($request->build());
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Input Registers berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayRegisterData($data);

            return true;

        } catch (\Exception $e) {
            $this->error('❌ Error membaca Input Registers: '.$e->getMessage());

            return false;
        }
    }

    private function test_coils()
    {
        $this->info('🔍 Testing Coils');

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
            $response = (new NonBlockingClient([
                'readTimeoutSec' => $this->config['timeout'],
            ]))->sendRequests($request->build());
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Coils berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayCoilData($data);

            return true;

        } catch (\Exception $e) {
            $this->error('❌ Error membaca Coils: '.$e->getMessage());

            return false;
        }
    }

    private function test_discrete_inputs()
    {
        $this->info('🔍 Testing Discrete Inputs');

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
            $response = (new NonBlockingClient([
                'readTimeoutSec' => $this->config['timeout'],
            ]))->sendRequests($request->build());
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Discrete Inputs berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayCoilData($data);

            return true;

        } catch (\Exception $e) {
            $this->error('❌ Error membaca Discrete Inputs: '.$e->getMessage());

            return false;
        }
    }

    private function test_custom()
    {
        $this->info('🔍 Testing Custom Function Codes');
        $allSuccess = true;

        foreach ($this->config['custom_functions'] as $functionCode) {
            switch ($functionCode) {
                case '01':
                    $success = $this->testCoils();
                    break;
                case '02':
                    $success = $this->testDiscreteInputs();
                    break;
                case '03':
                    $success = $this->testHoldingRegisters();
                    break;
                case '04':
                    $success = $this->testInputRegisters();
                    break;
                default:
                    $success = false;
            }

            $allSuccess = $allSuccess && $success;
            $this->newLine();
        }

        return $allSuccess;
    }

    private function test_diagnostic()
    {
        $this->info('🔍 Comprehensive Diagnostic Test');
        $results = [];

        // Test basic connection first
        $results['Basic Connection'] = $this->testBasicConnection();

        if (! $results['Basic Connection']) {
            $this->error('❌ Basic connection failed. Stopping diagnostic.');

            return false;
        }

        $this->newLine();

        // Test each function code with different address ranges
        foreach ($this->config['diagnostic_ranges'] as $type => $addresses) {
            $this->info("Testing {$type} registers/coils...");
            $results[$type] = [];

            foreach ($addresses as $address) {
                try {
                    $tempConfig = $this->config;
                    $tempConfig['start_address'] = $address;
                    $tempConfig['quantity'] = $this->config['diagnostic_deep'] ? 10 : 1;

                    $originalConfig = $this->config;
                    $this->config = $tempConfig;

                    switch ($type) {
                        case 'holding':
                            $success = $this->testHoldingRegistersQuiet();
                            break;
                        case 'input':
                            $success = $this->testInputRegistersQuiet();
                            break;
                        case 'coils':
                            $success = $this->testCoilsQuiet();
                            break;
                        case 'discrete':
                            $success = $this->testDiscreteInputsQuiet();
                            break;
                        default:
                            $success = false;
                    }

                    $this->config = $originalConfig;
                    $results[$type][$address] = $success;

                    if ($success) {
                        $this->info("  ✅ Address {$address}: OK");
                    } else {
                        $this->warn("  ⚠️ Address {$address}: No response");
                    }

                } catch (\Exception $e) {
                    $results[$type][$address] = false;
                    $this->warn("  ❌ Address {$address}: ".$e->getMessage());
                }
            }
            $this->newLine();
        }

        $this->displayDiagnosticResults($results);

        return true;
    }

    private function test_holding_registers_quiet()
    {
        try {
            $request = ReadRegistersBuilder::newReadHoldingRegisters(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );

            $this->buildRegisterRequest($request);

            $response = (new NonBlockingClient([
                'readTimeoutSec' => 2,
            ]))->sendRequests($request->build());

            return ! empty($response->getData());
        } catch (\Exception $e) {
            return false;
        }
    }

    private function test_input_registers_quiet()
    {
        try {
            $request = ReadRegistersBuilder::newReadInputRegisters(
                "tcp://{$this->config['ip']}:{$this->config['port']}",
                $this->config['unit_id']
            );

            $this->buildRegisterRequest($request);

            $response = (new NonBlockingClient([
                'readTimeoutSec' => 2,
            ]))->sendRequests($request->build());

            return ! empty($response->getData());
        } catch (\Exception $e) {
            return false;
        }
    }

    private function test_coils_quiet()
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

            $response = (new NonBlockingClient([
                'readTimeoutSec' => 2,
            ]))->sendRequests($request->build());

            return ! empty($response->getData());
        } catch (\Exception $e) {
            return false;
        }
    }

    private function test_discrete_inputs_quiet()
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

            $response = (new NonBlockingClient([
                'readTimeoutSec' => 2,
            ]))->sendRequests($request->build());

            return ! empty($response->getData());
        } catch (\Exception $e) {
            return false;
        }
    }

    private function buildRegisterRequest($request)
    {
        $method = 'int16'; // default

        switch ($this->config['data_type']) {
            case 'uint16':
                $method = 'uint16';
                break;
            case 'int32':
                $method = 'int32';
                break;
            case 'uint32':
                $method = 'uint32';
                break;
            case 'float32':
                $method = 'float';
                break;
        }

        if ($this->config['batch_reading']) {
            // Batch reading - more efficient
            for ($i = 0; $i < $this->config['quantity']; $i++) {
                $address = $this->config['start_address'] + $i;
                $request->$method($address, "register_{$address}");
            }
        } else {
            // Individual reading
            for ($i = 0; $i < $this->config['quantity']; $i++) {
                $address = $this->config['start_address'] + $i;
                $request->$method($address, "register_{$address}");
            }
        }
    }

    private function displayRegisterData($data)
    {
        if (empty($data)) {
            $this->warn('Tidak ada data yang diterima');

            return;
        }

        $tableData = [];
        $index = 0;
        foreach ($data as $key => $value) {
            $address = $this->config['start_address'] + $index;

            $row = ["Register {$address}", $key];

            // Add data in different formats based on configuration
            switch ($this->config['data_format']) {
                case 'decimal':
                    $row[] = $value;
                    break;
                case 'hexadecimal':
                    $row[] = sprintf('0x%04X', $value & 0xFFFF);
                    break;
                case 'binary':
                    $row[] = sprintf('%016b', $value & 0xFFFF);
                    break;
                case 'all':
                    $row[] = $value;
                    $row[] = sprintf('0x%04X', $value & 0xFFFF);
                    $row[] = sprintf('%016b', $value & 0xFFFF);
                    break;
            }

            $tableData[] = $row;
            $index++;
        }

        $headers = ['Address', 'Key'];
        switch ($this->config['data_format']) {
            case 'decimal':
                $headers[] = 'Decimal Value';
                break;
            case 'hexadecimal':
                $headers[] = 'Hex Value';
                break;
            case 'binary':
                $headers[] = 'Binary Value';
                break;
            case 'all':
                $headers[] = 'Decimal';
                $headers[] = 'Hex';
                $headers[] = 'Binary';
                break;
        }

        $this->table($headers, $tableData);
    }

    private function displayCoilData($data)
    {
        if (empty($data)) {
            $this->warn('Tidak ada data yang diterima');

            return;
        }

        $tableData = [];
        $index = 0;
        foreach ($data as $key => $value) {
            $address = $this->config['start_address'] + $index;
            $tableData[] = [
                "Coil {$address}",
                $key,
                $value ? 'TRUE' : 'FALSE',
                $value ? 'ON' : 'OFF',
            ];
            $index++;
        }

        $this->table(['Address', 'Key', 'Boolean Value', 'Status'], $tableData);
    }

    private function displayDiagnosticResults($results)
    {
        $this->newLine();
        $this->info('📊 DIAGNOSTIC TEST RESULTS:');
        $this->line('─────────────────────────────────');

        foreach ($results as $testType => $result) {
            if ($testType === 'Basic Connection') {
                $status = $result ? '✅ PASS' : '❌ FAIL';
                $this->line("{$testType}: {$status}");
            } else {
                $this->line("\n{$testType}:");
                if (is_array($result)) {
                    foreach ($result as $address => $success) {
                        $status = $success ? '✅ OK' : '❌ FAIL';
                        $this->line("  Address {$address}: {$status}");
                    }
                }
            }
        }
        $this->newLine();
    }
}
