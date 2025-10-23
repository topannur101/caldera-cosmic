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
                'name' => 'DWP Station Beta',
                'ip_address' => '192.168.1.100',
                'config' => [
                    [
                        'line' => 'G1',
                        'list_mechine' => [
                                [
                                    'name' => 'mc1',
                                    'addr_th_l' => '199',
                                    'addr_th_r' => '203',
                                    'addr_side_l' => '309',
                                    'addr_side_r' => '313',
                                    'addr_std_th_min' => '109',
                                    'addr_std_th_max' => '119',
                                    'addr_std_side_min' => '209',
                                    'addr_std_side_max' => '219',
                                ],
                                [
                                    'name' => 'mc2',
                                    'addr_th_l' => '199',
                                    'addr_th_r' => '203',
                                    'addr_side_l' => '309',
                                    'addr_side_r' => '314',
                                    'addr_std_th_min' => '109',
                                    'addr_std_th_max' => '119',
                                    'addr_std_side_min' => '209',
                                    'addr_std_side_max' => '219',
                                ],
                                [
                                    'name' => 'mc3',
                                    'addr_th_l' => '199',
                                    'addr_th_r' => '203',
                                    'addr_side_l' => '309',
                                    'addr_side_r' => '314',
                                    'addr_std_th_min' => '110',
                                    'addr_std_th_max' => '120',
                                    'addr_std_side_min' => '210',
                                    'addr_std_side_max' => '220',
                                ],
                                [
                                    'name' => 'mc4',
                                    'addr_th_l' => '199',
                                    'addr_th_r' => '203',
                                    'addr_side_l' => '309',
                                    'addr_side_r' => '314',
                                    'addr_std_th_min' => '110',
                                    'addr_std_th_max' => '120',
                                    'addr_std_side_min' => '210',
                                    'addr_std_side_max' => '220',
                                ],
                            ],
                        'dwp_alarm' => [
                            "addr_long_duration" => "444",
                            "addr_counter" => "65",
                            "addr_reset" => "1",
                            "addr_dd_1" => "6110",
                            "addr_dd_2" => "6111",
                            "addr_dd_3" => "6112",
                            "addr_dd_4" => "6113",
                            "addr_dd_5" => "6114",
                            "addr_control" => "5999",
                            "addr_data_storage" => "6001"
                        ]
                    ]
                ],
                'is_active' => true
            ],
            [
                'name' => 'DWP Station Meta',
                'ip_address' => '192.168.1.102',
                'config' => [
                    [
                        'line' => 'B2',
                        'list_mechine' => [
                            [
                                'name' => 'mc1',
                                'addr_th_l' => '199',
                                'addr_th_r' => '203',
                                'addr_side_l' => '309',
                                'addr_side_r' => '314',
                                'addr_std_th_min' => '109',
                                'addr_std_th_max' => '119',
                                'addr_std_side_min' => '209',
                                'addr_std_side_max' => '219'
                            ],
                            [
                                'name' => 'mc2',
                                'addr_th_l' => '199',
                                'addr_th_r' => '203',
                                'addr_side_l' => '309',
                                'addr_side_r' => '314',
                                'addr_std_th_min' => '109',
                                'addr_std_th_max' => '119',
                                'addr_std_side_min' => '209',
                                'addr_std_side_max' => '219'
                            ],
                            [
                                'name' => 'mc3',
                                'addr_th_l' => '199',
                                'addr_th_r' => '203',
                                'addr_side_l' => '309',
                                'addr_side_r' => '314',
                                'addr_std_th_min' => '109',
                                'addr_std_th_max' => '119',
                                'addr_std_side_min' => '209',
                                'addr_std_side_max' => '219'
                            ],
                            [
                                'name' => 'mc4',
                                'addr_th_l' => '199',
                                'addr_th_r' => '203',
                                'addr_side_l' => '309',
                                'addr_side_r' => '314',
                                'addr_std_th_min' => '109',
                                'addr_std_th_max' => '119',
                                'addr_std_side_min' => '209',
                                'addr_std_side_max' => '219'
                            ]
                        ],
                        'dwp_alarm' => [
                            "addr_long_duration" => "444",
                            "addr_counter" => "65",
                            "addr_reset" => "1",
                            "addr_dd_1" => "6111",
                            "addr_dd_2" => "6111",
                            "addr_dd_3" => "6111",
                            "addr_dd_4" => "6111",
                            "addr_dd_5" => "6111",
                            "addr_control" => "21212",
                            "addr_data_storage" => "6001"
                        ]
                    ]
                ],
                'is_active' => false
            ]
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