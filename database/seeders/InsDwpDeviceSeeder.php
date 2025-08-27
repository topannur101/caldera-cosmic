<?php

namespace Database\Seeders;

use App\Models\InsDwpDevice;
use Illuminate\Database\Seeder;

class InsDwpDeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $devices = [
            [
                'name' => 'DWP Station Alpha',
                'ip_address' => '192.168.1.100',
                'config' => [
                    [
                        'line' => 'A1',
                        'addr_counter' => 1000,
                        'addr_reset' => 2000,
                    ],
                    [
                        'line' => 'A2',
                        'addr_counter' => 1002,
                        'addr_reset' => 2002,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'DWP Station Beta',
                'ip_address' => '192.168.1.101',
                'config' => [
                    [
                        'line' => 'B1',
                        'addr_counter' => 1000,
                        'addr_reset' => 2000,
                    ],
                    [
                        'line' => 'B2',
                        'addr_counter' => 1002,
                        'addr_reset' => 2002,
                    ],
                    [
                        'line' => 'B3',
                        'addr_counter' => 1004,
                        'addr_reset' => 2004,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'DWP Station Gamma',
                'ip_address' => '192.168.1.102',
                'config' => [
                    [
                        'line' => 'G1',
                        'addr_counter' => 1000,
                        'addr_reset' => 2000,
                    ],
                    [
                        'line' => 'G2',
                        'addr_counter' => 1002,
                        'addr_reset' => 2002,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'DWP Station Delta (Maintenance)',
                'ip_address' => '192.168.1.103',
                'config' => [
                    [
                        'line' => 'D1',
                        'addr_counter' => 1000,
                        'addr_reset' => 2000,
                    ],
                ],
                'is_active' => false, // Inactive for testing filter scenarios
            ],
        ];

        foreach ($devices as $deviceData) {
            InsDwpDevice::updateOrCreate(
                ['ip_address' => $deviceData['ip_address']],
                $deviceData
            );
        }

        $this->command->info('✓ Created ' . count($devices) . ' DWP devices with ' . 
            collect($devices)->sum(fn($d) => count($d['config'])) . ' total lines');
    }
}