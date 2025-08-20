<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ModbusTcpClient\Network\NonBlockingClient;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Composer\Read\ReadCoilsBuilder;
use Carbon\Carbon;

class ModbusConnectionTest extends Command
{
    protected $signature = 'modbus:test-connection 
                            {--ip= : IP address server Modbus}
                            {--port=503 : Port server Modbus}
                            {--unit-id=1 : Unit ID Modbus}
                            {--register= : Register address untuk test}
                            {--quantity=1 : Jumlah register yang akan dibaca}
                            {--type=basic : Jenis test (basic, holding, input, coil)}';

    protected $description = 'Wizard untuk menguji koneksi ke Modbus server';

    public function handle()
    {
        $this->info('==========================================');
        $this->info('   MODBUS CONNECTION TEST WIZARD');
        $this->info('==========================================');
        $this->newLine();

        $ip = $this->option('ip') ?: $this->askForIpAddress();
        $port = $this->option('port') ?: $this->askForPort();
        $unitId = $this->option('unit-id') ?: $this->askForUnitId();
        $testType = $this->option('type') ?: $this->askForTestType();

        $this->newLine();
        $this->info('Konfigurasi Test:');
        $this->table(['Parameter', 'Value'], [
            ['IP Address', $ip],
            ['Port', $port],
            ['Unit ID', $unitId],
            ['Test Type', $testType],
        ]);
        $this->newLine();

        if (!$this->confirm('Lanjutkan test dengan konfigurasi di atas?')) {
            $this->warn('Test dibatalkan.');
            return 0;
        }

        $this->performTest($ip, $port, $unitId, $testType);

        return 0;
    }

    private function askForIpAddress()
    {
        while (true) {
            $ip = $this->ask('Masukkan IP address server Modbus (contoh: 192.168.1.100)');
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                if (strpos($ip, '127.') === 0) {
                    if ($this->confirm("IP address {$ip} adalah loopback address. Lanjutkan?")) {
                        return $ip;
                    }
                    continue;
                }
                return $ip;
            }
            
            $this->error('Format IP address tidak valid. Silakan coba lagi.');
        }
    }

    private function askForPort()
    {
        $port = $this->ask('Masukkan port Modbus server', '503');
        
        if (!is_numeric($port) || $port < 1 || $port > 65535) {
            $this->warn('Port tidak valid, menggunakan default port 503');
            return 503;
        }
        
        return (int) $port;
    }

    private function askForUnitId()
    {
        $unitId = $this->ask('Masukkan Unit ID Modbus', '1');
        
        if (!is_numeric($unitId) || $unitId < 0 || $unitId > 255) {
            $this->warn('Unit ID tidak valid, menggunakan default 1');
            return 1;
        }
        
        return (int) $unitId;
    }

    private function askForTestType()
    {
        $choices = [
            'basic' => 'Test koneksi dasar (TCP connection only)',
            'holding' => 'Test baca Holding Registers',
            'input' => 'Test baca Input Registers',
            'coil' => 'Test baca Coils',
            'all' => 'Test semua jenis (comprehensive)'
        ];

        $choice = $this->choice('Pilih jenis test yang ingin dilakukan:', array_keys($choices));
        
        $this->info("Dipilih: {$choices[$choice]}");
        
        return $choice;
    }

    private function performTest($ip, $port, $unitId, $testType)
    {
        $this->info('Memulai test koneksi...');
        $this->newLine();

        $startTime = microtime(true);

        switch ($testType) {
            case 'basic':
                $this->testBasicConnection($ip, $port);
                break;
            case 'holding':
                $this->testHoldingRegisters($ip, $port, $unitId);
                break;
            case 'input':
                $this->testInputRegisters($ip, $port, $unitId);
                break;
            case 'coil':
                $this->testCoils($ip, $port, $unitId);
                break;
            case 'all':
                $this->testComprehensive($ip, $port, $unitId);
                break;
        }

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        $this->newLine();
        $this->info("Test selesai dalam {$duration}ms");
        $this->info('Timestamp: ' . Carbon::now()->format('Y-m-d H:i:s'));
    }

    private function testBasicConnection($ip, $port)
    {
        $this->info("🔍 Testing basic TCP connection ke {$ip}:{$port}");

        $startTime = microtime(true);
        $socket = @fsockopen($ip, $port, $errno, $errstr, 5);
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

    private function testHoldingRegisters($ip, $port, $unitId)
    {
        $this->info("🔍 Testing Holding Registers");

        $registerAddress = $this->ask('Masukkan register address (default: 0)', '0');
        $quantity = $this->ask('Jumlah register yang akan dibaca (default: 10)', '10');

        try {
            $request = ReadRegistersBuilder::newReadHoldingRegisters("tcp://{$ip}:{$port}", $unitId);
            
            for ($i = 0; $i < $quantity; $i++) {
                $request->int16($registerAddress + $i, "register_" . ($registerAddress + $i));
            }
            
            $startTime = microtime(true);
            $response = (new NonBlockingClient(['readTimeoutSec' => 5]))->sendRequests($request->build());
            $endTime = microtime(true);
            
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Holding Registers berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayRegisterData($data, $registerAddress);

        } catch (\Exception $e) {
            $this->error("❌ Error membaca Holding Registers: " . $e->getMessage());
        }
    }

    private function testInputRegisters($ip, $port, $unitId)
    {
        $this->info("🔍 Testing Input Registers");

        $registerAddress = $this->ask('Masukkan register address (default: 0)', '0');
        $quantity = $this->ask('Jumlah register yang akan dibaca (default: 10)', '10');

        try {
            $request = ReadRegistersBuilder::newReadInputRegisters("tcp://{$ip}:{$port}", $unitId);
            
            for ($i = 0; $i < $quantity; $i++) {
                $request->int16($registerAddress + $i, "register_" . ($registerAddress + $i));
            }
            
            $startTime = microtime(true);
            $response = (new NonBlockingClient(['readTimeoutSec' => 5]))->sendRequests($request->build());
            $endTime = microtime(true);
            
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Input Registers berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayRegisterData($data, $registerAddress);

        } catch (\Exception $e) {
            $this->error("❌ Error membaca Input Registers: " . $e->getMessage());
        }
    }

    private function testCoils($ip, $port, $unitId)
    {
        $this->info("🔍 Testing Coils");

        $coilAddress = $this->ask('Masukkan coil address (default: 0)', '0');
        $quantity = $this->ask('Jumlah coil yang akan dibaca (default: 10)', '10');

        try {
            $request = ReadCoilsBuilder::newReadCoils("tcp://{$ip}:{$port}", $unitId);
            
            for ($i = 0; $i < $quantity; $i++) {
                $request->coil($coilAddress + $i, "coil_" . ($coilAddress + $i));
            }
            
            $startTime = microtime(true);
            $response = (new NonBlockingClient(['readTimeoutSec' => 5]))->sendRequests($request->build());
            $endTime = microtime(true);
            
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            $data = $response->getData();

            $this->info("✅ Coils berhasil dibaca! Response time: {$responseTime}ms");
            $this->displayCoilData($data, $coilAddress);

        } catch (\Exception $e) {
            $this->error("❌ Error membaca Coils: " . $e->getMessage());
        }
    }

    private function testComprehensive($ip, $port, $unitId)
    {
        $this->info("🔍 Comprehensive Test - Testing semua jenis koneksi");
        $this->newLine();

        $results = [
            'Basic Connection' => $this->testBasicConnection($ip, $port),
            'Holding Registers' => false,
            'Input Registers' => false,
            'Coils' => false,
        ];

        $this->newLine();

        if ($results['Basic Connection']) {
            $this->info("Testing Holding Registers...");
            try {
                $request = ReadRegistersBuilder::newReadHoldingRegisters("tcp://{$ip}:{$port}", $unitId)
                    ->int16(0, "test_register_0")
                    ->int16(1, "test_register_1")
                    ->build();
                
                $response = (new NonBlockingClient(['readTimeoutSec' => 3]))->sendRequests($request);
                $results['Holding Registers'] = true;
                $this->info("✅ Holding Registers: OK");
            } catch (\Exception $e) {
                $this->warn("⚠️ Holding Registers: " . $e->getMessage());
            }

            $this->info("Testing Input Registers...");
            try {
                $request = ReadRegistersBuilder::newReadInputRegisters("tcp://{$ip}:{$port}", $unitId)
                    ->int16(0, "test_register_0")
                    ->int16(1, "test_register_1")
                    ->build();
                
                $response = (new NonBlockingClient(['readTimeoutSec' => 3]))->sendRequests($request);
                $results['Input Registers'] = true;
                $this->info("✅ Input Registers: OK");
            } catch (\Exception $e) {
                $this->warn("⚠️ Input Registers: " . $e->getMessage());
            }

            $this->info("Testing Coils...");
            try {
                $request = ReadCoilsBuilder::newReadCoils("tcp://{$ip}:{$port}", $unitId)
                    ->coil(0, "test_coil_0")
                    ->coil(1, "test_coil_1")
                    ->build();
                
                $response = (new NonBlockingClient(['readTimeoutSec' => 3]))->sendRequests($request);
                $results['Coils'] = true;
                $this->info("✅ Coils: OK");
            } catch (\Exception $e) {
                $this->warn("⚠️ Coils: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('📊 RINGKASAN TEST RESULTS:');
        $tableData = [];
        foreach ($results as $test => $result) {
            $tableData[] = [
                $test,
                $result ? '✅ PASS' : '❌ FAIL'
            ];
        }
        $this->table(['Test Type', 'Result'], $tableData);

        $passCount = array_sum($results);
        $totalTests = count($results);
        $this->info("Total: {$passCount}/{$totalTests} tests berhasil");
    }

    private function displayRegisterData($data, $startAddress = 0)
    {
        if (empty($data)) {
            $this->warn('Tidak ada data yang diterima');
            return;
        }

        $tableData = [];
        $index = 0;
        foreach ($data as $key => $value) {
            $address = $startAddress + $index;
            $tableData[] = [
                "Register {$address}",
                $key,
                $value,
                sprintf('0x%04X', $value & 0xFFFF)
            ];
            $index++;
        }

        $this->table(['Address', 'Key', 'Decimal Value', 'Hex Value'], $tableData);
    }

    private function displayCoilData($data, $startAddress = 0)
    {
        if (empty($data)) {
            $this->warn('Tidak ada data yang diterima');
            return;
        }

        $tableData = [];
        $index = 0;
        foreach ($data as $key => $value) {
            $address = $startAddress + $index;
            $tableData[] = [
                "Coil {$address}",
                $key,
                $value ? 'TRUE' : 'FALSE',
                $value ? 'ON' : 'OFF'
            ];
            $index++;
        }

        $this->table(['Address', 'Key', 'Boolean Value', 'Status'], $tableData);
    }
}