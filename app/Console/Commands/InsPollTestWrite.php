<?php

namespace App\Console\Commands;

use App\Models\InsIbmsDevice;
use App\Models\InsIbmsCount;
use App\Models\UptimeLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use ModbusTcpClient\Composer\Read\ReadRegistersBuilder;
use ModbusTcpClient\Composer\Write\WriteCoilsBuilder;
use ModbusTcpClient\Composer\Write\WriteRegistersBuilder;
use ModbusTcpClient\Network\NonBlockingClient;

class InsPollTestWrite extends Command
{
    // Configuration variables
    private const POLL_INTERVAL_SECONDS = 1;
    private const MODBUS_TIMEOUT_SECONDS = 2;
    private const MODBUS_PORT = 503;
    private const MODBUS_UNIT_ID = 1;
    private const RESET_FLAG_CACHE_TTL_DAYS = 2;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-poll-test-write {--v : Verbose output} {--d : Debug output} {--dry-test : Run simulation without reset write and DB insert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll test write (Back part mold emergency stop) counter data from Modbus servers and track incremental counts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get all active devices
        $configPhDosingMain = [
            'ip_address' => '172.70.88.199',
            'addr_one_hour' => 10,
            'addr_dosing_count' => 10,
            'addr_current_ph' => 0,
        ];

        $configPhDosingSub = [
            'ip_address' => '192.168.1.200',
            'addr_one_hour' => 10,
        ];

        while (true) {
            $dataDosingMain = $this->readInputRegisters($configPhDosingMain['ip_address'], $configPhDosingMain['addr_current_ph'], 'current_ph');
            // Convert to 10.00 format
            // $dataDosingMain = $dataDosingMain / 100;
            echo $dataDosingMain . "\n";

            // send write single register to HMI
            $sendToHmi = $this->writeModbusSingleRegister($configPhDosingSub['ip_address'], 10, $dataDosingMain);
        }
    }


    // main function for read from HMI
    public function readHoldingRegisters(string $ipAddress, int $address, string $name): int
    {
        $request = ReadRegistersBuilder::newReadHoldingRegisters(
            "tcp://{$ipAddress}:" . self::MODBUS_PORT,
            self::MODBUS_UNIT_ID
        )
        ->int16($address, $name)
        ->build();
        
        $response = (new NonBlockingClient(['readTimeoutSec' => self::MODBUS_TIMEOUT_SECONDS]))
            ->sendRequests($request)
            ->getData();
        
        return abs($response[$name]);
    }

    public function readInputRegisters(string $ipAddress, int $address, string $name): int
    {
        $request = ReadRegistersBuilder::newReadInputRegisters(
            "tcp://{$ipAddress}:" . self::MODBUS_PORT,
            self::MODBUS_UNIT_ID
        )
        ->int16($address, $name)
        ->build();
        
        $response = (new NonBlockingClient(['readTimeoutSec' => self::MODBUS_TIMEOUT_SECONDS]))
            ->sendRequests($request)
            ->getData();
        
        return abs($response[$name]);
    }

    private function writeModbusSingleRegister(string $ipAddress, int $address, int $value): void
    {
        $request = WriteRegistersBuilder::newWriteMultipleRegisters(
            "tcp://{$ipAddress}:" . self::MODBUS_PORT,
            self::MODBUS_UNIT_ID
        )
        ->int16($address, $value)
        ->build();

        (new NonBlockingClient(['readTimeoutSec' => self::MODBUS_TIMEOUT_SECONDS]))
            ->sendRequests($request);
            
        $this->info("✓ Write single register to {$ipAddress} address {$address} value {$value}");
    }

    private function writeModbusMultipleRegisters(string $ipAddress, array $registers): void
    {
        $request = MultipleRegistersBuilder::newMultipleRegisters(
            "tcp://{$ipAddress}:" . self::MODBUS_PORT,
            self::MODBUS_UNIT_ID
        )
        ->registers($registers)
        ->build();

        (new NonBlockingClient(['readTimeoutSec' => self::MODBUS_TIMEOUT_SECONDS]))
            ->sendRequests($request);
            
        $this->info("✓ Write multiple registers to {$ipAddress} addresses " . implode(', ', array_keys($registers)) . " values " . implode(', ', $registers));
    }
}
