<?php

namespace App\Console\Commands;

use App\Models\InsIbmsCount;
use App\Models\InsIbmsDevice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleRegisterRequest;

class InsIbmsHistory extends Command
{
    protected $signature = 'app:ins-ibms-history {--d : Debug output}';

    protected $description = 'Send IBMS history data to HMI once (3 pages x 5 rows)';

    protected $modbusPort = 503;

    public function handle()
    {
        $devices = InsIbmsDevice::active()->get();

        if ($devices->isEmpty()) {
            $this->error('✗ No active IBMS devices found');
            return 1;
        }

        $sentDevices = 0;

        foreach ($devices as $device) {
            $config = $device->config ?? [];
            $config['ip_address'] = $device->ip_address;

            try {
                $this->sendHistoryData($config);
                $sentDevices++;
                $this->info("✓ History sent to {$device->name} ({$device->ip_address})");
            } catch (\Throwable $th) {
                $this->error("✗ Error sending history to {$device->name}: " . $th->getMessage());
            }
        }

        $this->info("✓ IBMS history push completed ({$sentDevices}/" . count($devices) . ' devices)');

        return 0;
    }

    private function sendHistoryData(array $config): void
    {
        if (empty($config['ip_address'])) {
            return;
        }

        $startDate = Carbon::today();
        $endDate = Carbon::today()->endOfDay();
        $datas = InsIbmsCount::whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->take(15)
            ->get()
            ->toArray();

        $addressConfig = [
            'history_1' => [
                'timestamp' => [311, 312, 313, 314, 315],
                'machine_name' => [321, 322, 323, 324, 325],
                'duration_seconds' => [331, 332, 333, 334, 335],
                'status' => [341, 342, 343, 344, 345],
            ],
            'history_2' => [
                'timestamp' => [411, 412, 413, 414, 415],
                'machine_name' => [421, 422, 423, 424, 425],
                'duration_seconds' => [431, 432, 433, 434, 435],
                'status' => [441, 442, 443, 444, 445],
            ],
            'history_3' => [
                'timestamp' => [511, 512, 513, 514, 515],
                'machine_name' => [521, 522, 523, 524, 525],
                'duration_seconds' => [531, 532, 533, 534, 535],
                'status' => [541, 542, 543, 544, 545],
            ],
        ];

        foreach ($addressConfig as $pageKey => $pageConfig) {
            foreach (['timestamp', 'machine_name', 'duration_seconds', 'status'] as $field) {
                foreach ($pageConfig[$field] as $registerAddress) {
                    try {
                        $this->writeSingleRegister($config, $registerAddress, 0);
                    } catch (\Throwable $th) {
                        if ($this->option('d')) {
                            $this->error("✗ Error clearing {$pageKey}.{$field} register {$registerAddress}: " . $th->getMessage());
                        }
                    }
                }
            }
        }

        $pageKeys = array_keys($addressConfig);

        foreach ($datas as $index => $data) {
            if ($index >= 15) {
                break;
            }

            $pageIndex = intdiv($index, 5);
            $rowIndex = $index % 5;
            $pageKey = $pageKeys[$pageIndex] ?? null;

            if ($pageKey === null) {
                continue;
            }

            $timestamp = (int) Carbon::parse($data['created_at'])->format('Hi');
            $machineName = (string) ($data['data']['name'] ?? 'unknown');
            preg_match('/(\d+)/', $machineName, $machineMatches);
            $machineCode = isset($machineMatches[1]) ? (int) $machineMatches[1] : ($rowIndex + 1);

            $durationSecondsRaw = (int) ($data['data']['duration_seconds'] ?? 0);
            $durationSecondsRaw = max(0, $durationSecondsRaw);
            $durationMinutes = intdiv($durationSecondsRaw, 60);
            $durationSecondsRemainder = $durationSecondsRaw % 60;
            $durationMmSs = (int) sprintf('%02d%02d', $durationMinutes % 100, $durationSecondsRemainder);

            $status = match ($data['data']['status'] ?? 'unknown') {
                'too_early' => 1,
                'on_time' => 2,
                'to_late', 'too_late', 'late' => 3,
                default => 0,
            };

            try {
                $this->writeSingleRegister($config, $addressConfig[$pageKey]['timestamp'][$rowIndex], $timestamp);
                $this->writeSingleRegister($config, $addressConfig[$pageKey]['machine_name'][$rowIndex], $machineCode);
                $this->writeSingleRegister($config, $addressConfig[$pageKey]['duration_seconds'][$rowIndex], $durationMmSs);
                $this->writeSingleRegister($config, $addressConfig[$pageKey]['status'][$rowIndex], $status);
            } catch (\Throwable $th) {
                if ($this->option('d')) {
                    $this->error("✗ Error writing history data {$pageKey} row {$rowIndex}: " . $th->getMessage());
                }
            }
        }
    }

    private function writeSingleRegister(array $config, int $address, int $value): void
    {
        $unitId = 1;

        $connection = BinaryStreamConnection::getBuilder()
            ->setHost($config['ip_address'])
            ->setPort($this->modbusPort)
            ->build();

        $request = new WriteSingleRegisterRequest($address, $value, $unitId);

        try {
            $connection->connect();
            $connection->send($request);
        } finally {
            $connection->close();
        }
    }

}
