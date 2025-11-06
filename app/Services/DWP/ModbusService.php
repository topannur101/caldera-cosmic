<?php

namespace App\Services\DWP;

use App\Models\InsDwpDevice;
use App\Services\DWP\DTOs\ModbusReading;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Network\NonBlockingClient;
use Illuminate\Support\Collection;

class ModbusService
{
    private DwpPollingConfig $config;
    private int $unitId = 1;

    public function __construct(DwpPollingConfig $config = null)
    {
        $this->config = $config ?? new DwpPollingConfig();
    }

    /**
     * Poll all machines from a device and return readings
     */
    public function pollDevice(InsDwpDevice $device): Collection
    {
        $readings = collect();

        foreach ($device->config as $lineConfig) {
            $line = strtoupper(trim($lineConfig['line']));

            foreach ($lineConfig['list_mechine'] as $machineConfig) {
                try {
                    $reading = $this->pollMachine($device, $line, $machineConfig);
                    $readings->push($reading);
                } catch (\Exception $e) {
                    $reading = ModbusReading::failed(
                        line: $line,
                        machineName: $machineConfig['name'],
                        error: $e->getMessage()
                    );
                    $readings->push($reading);
                }
            }
        }

        return $readings;
    }

    /**
     * Poll a specific machine and return modbus reading
     */
    private function pollMachine(InsDwpDevice $device, string $line, array $machineConfig): ModbusReading
    {
        $machineName = $machineConfig['name'];
        $tcpAddress = 'tcp://' . $device->ip_address . ':' . $this->config::MODBUS_PORT;

        $request = ReadRegistersBuilder::newReadInputRegisters($tcpAddress, $this->unitId)
            ->int16($machineConfig['addr_th_l'], 'toe_heel_left')
            ->int16($machineConfig['addr_th_r'], 'toe_heel_right')
            ->int16($machineConfig['addr_side_l'], 'side_left')
            ->int16($machineConfig['addr_side_r'], 'side_right')
            ->build();

        $client = new NonBlockingClient([
            'readTimeoutSec' => $this->config::MODBUS_TIMEOUT_SECONDS
        ]);

        $response = $client->sendRequests($request)->getData();

        return ModbusReading::fromModbusResponse($line, $machineName, $response);
    }

    /**
     * Test connection to a device
     */
    public function testConnection(InsDwpDevice $device): bool
    {
        try {
            $tcpAddress = 'tcp://' . $device->ip_address . ':' . $this->config::MODBUS_PORT;

            // Try to read a single register to test connectivity
            $request = ReadRegistersBuilder::newReadInputRegisters($tcpAddress, $this->unitId)
                ->int16(0, 'test_register')
                ->build();

            $client = new NonBlockingClient([
                'readTimeoutSec' => $this->config::MODBUS_TIMEOUT_SECONDS
            ]);

            $client->sendRequests($request);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get device connection statistics
     */
    public function getDeviceStats(InsDwpDevice $device): array
    {
        return [
            'ip_address' => $device->ip_address,
            'port' => $this->config::MODBUS_PORT,
            'timeout' => $this->config::MODBUS_TIMEOUT_SECONDS,
            'unit_id' => $this->unitId,
            'lines_count' => count($device->getLines()),
            'machines_count' => $this->getMachinesCount($device),
        ];
    }

    /**
     * Count total machines in device configuration
     */
    private function getMachinesCount(InsDwpDevice $device): int
    {
        $count = 0;
        foreach ($device->config as $lineConfig) {
            $count += count($lineConfig['list_mechine']);
        }
        return $count;
    }

    /**
     * Validate device configuration
     */
    public function validateDeviceConfig(InsDwpDevice $device): array
    {
        $errors = [];

        if (empty($device->ip_address)) {
            $errors[] = 'IP address is required';
        }

        if (!filter_var($device->ip_address, FILTER_VALIDATE_IP)) {
            $errors[] = 'Invalid IP address format';
        }

        if (empty($device->config)) {
            $errors[] = 'Device configuration is empty';
        }

        foreach ($device->config as $lineIndex => $lineConfig) {
            if (empty($lineConfig['line'])) {
                $errors[] = "Line name is required for configuration index {$lineIndex}";
            }

            if (empty($lineConfig['list_mechine'])) {
                $errors[] = "Machine list is empty for line {$lineConfig['line']}";
                continue;
            }

            foreach ($lineConfig['list_mechine'] as $machineIndex => $machineConfig) {
                $requiredFields = ['name', 'addr_th_l', 'addr_th_r', 'addr_side_l', 'addr_side_r'];

                foreach ($requiredFields as $field) {
                    if (!isset($machineConfig[$field])) {
                        $errors[] = "Missing {$field} for machine index {$machineIndex} in line {$lineConfig['line']}";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Extract all active cycle keys from devices (for cleanup)
     */
    public function extractActiveCycleKeys(Collection $devices): array
    {
        $keys = [];

        foreach ($devices as $device) {
            foreach ($device->config as $lineConfig) {
                $line = strtoupper(trim($lineConfig['line']));
                foreach ($lineConfig['list_mechine'] as $machineConfig) {
                    $machineName = $machineConfig['name'];
                    $keys[] = "{$line}-{$machineName}-L";
                    $keys[] = "{$line}-{$machineName}-R";
                }
            }
        }

        return array_unique($keys);
    }
}
