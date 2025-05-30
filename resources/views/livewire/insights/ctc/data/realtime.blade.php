<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {

    public int $selectedDevice = 0;
    public array $devices = [];
    public array $liveDevices = [];

    public function mount()
    {
        $this->loadMockData();
    }

    private function loadMockData(): void
    {
        $this->devices = [
            ['id' => 1, 'line' => 3],
            ['id' => 2, 'line' => 4],
        ];

        $this->liveDevices = [
            [
                'id' => 1,
                'line' => 3,
                'is_active' => true,
                'current_batch' => [
                    'id' => 'batch_001',
                    'rubber_batch_code' => 'RB240501A',
                    'recipe_name' => 'AF1 GS (ONE COLOR)',
                    'started_at' => now()->subMinutes(15),
                    'duration' => '00:15:32',
                    'measurement_count' => 932,
                    'recipe_recommendation' => [
                        'recommended_id' => 6,
                        'actual_id' => 6,
                        'is_following' => true
                    ]
                ],
                'current_reading' => [
                    'sensor_left' => 3.05,
                    'sensor_right' => 3.02,
                    'left_status' => 'within',
                    'right_status' => 'within'
                ]
            ],
            [
                'id' => 2,
                'line' => 4,
                'is_active' => false,
                'current_batch' => null,
                'current_reading' => [
                    'sensor_left' => 0.00,
                    'sensor_right' => 0.00,
                    'left_status' => 'offline',
                    'right_status' => 'offline'
                ]
            ]
        ];
    }

    public function selectDevice(int $deviceId): void
    {
        $this->selectedDevice = $deviceId;
        
        // Mock chart data update
        $mockChartData = [
            'timestamps' => array_map(fn($i) => now()->subSeconds(50-$i)->format('H:i:s'), range(0, 50)),
            'sensor_left' => array_map(fn() => 3.0 + (rand(-20, 20) / 100), range(0, 50)),
            'sensor_right' => array_map(fn() => 3.0 + (rand(-20, 20) / 100), range(0, 50)),
            'std_min' => 3.0,
            'std_max' => 3.1,
            'std_mid' => 3.05
        ];

        $this->js("
            updateLiveChart(" . json_encode($mockChartData) . ");
        ");
    }

    public function showBatchHistory(int $deviceId): void
    {
        $this->redirect(route('insights.ctc.data.batch', ['device_id' => $deviceId]));
    }

    public function with(): array
    {
        $selectedDeviceData = collect($this->liveDevices)->firstWhere('id', $this->selectedDevice);
        
        return [
            'selectedDeviceData' => $selectedDeviceData
        ];
    }
};

?>

<div wire:poll.5s>
    {{-- Quick Line Selection --}}
    <div class="flex items-center justify-center mb-6 gap-2 text-xs">
        @foreach ($devices as $device)
            <div class="btn-group">
                <x-text-button 
                    class="px-3 py-2 bg-caldy-600 {{ collect($liveDevices)->firstWhere('id', $device['id'])['is_active'] ? 'bg-opacity-80 text-white' : 'bg-opacity-15 text-caldy-700' }}"
                    wire:click="selectDevice({{ $device['id'] }})">
                    {{ __('Line') . ' ' . $device['line'] }}
                </x-text-button>
            </div>
        @endforeach
    </div>

    {{-- Live Status Cards Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
        @foreach ($liveDevices as $device)
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden 
                {{ $selectedDevice === $device['id'] ? 'ring-2 ring-caldy-500' : '' }}">
                <div class="flex">
                    {{-- Device Header --}}
                    <div class="flex items-center border-r border-neutral-100 dark:border-neutral-700 p-4 font-mono text-2xl 
                        {{ $device['is_active'] ? 'bg-green-500 bg-opacity-20' : 'bg-neutral-100 dark:bg-neutral-700' }}">
                        <div>
                            {{ sprintf('%02d', $device['line']) }}
                            @if($device['current_batch'] && $device['current_batch']['recipe_recommendation']['is_following'])
                                <i class="icon-check-circle text-green-500 text-sm" title="{{ __('Mengikuti rekomendasi') }}"></i>
                            @elseif($device['current_batch'])
                                <i class="icon-alert-circle text-yellow-500 text-sm" title="{{ __('Override oleh operator') }}"></i>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Live Metrics --}}
                    <div class="grow p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div class="text-sm font-medium">
                                {{ $device['current_batch']['rubber_batch_code'] ?? __('Tidak ada batch') }}
                            </div>
                            <div class="text-xs text-neutral-500">
                                {{ $device['current_batch']['recipe_name'] ?? '' }}
                            </div>
                        </div>
                        
                        {{-- Real-time thickness display --}}
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div>
                                <div class="text-xs text-neutral-500">{{ __('Kiri') }}</div>
                                <div class="text-lg font-mono 
                                    {{ $device['current_reading']['left_status'] === 'within' ? 'text-green-600' : ($device['current_reading']['left_status'] === 'offline' ? 'text-neutral-400' : 'text-red-600') }}">
                                    {{ number_format($device['current_reading']['sensor_left'], 2) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-neutral-500">{{ __('Kanan') }}</div>
                                <div class="text-lg font-mono 
                                    {{ $device['current_reading']['right_status'] === 'within' ? 'text-green-600' : ($device['current_reading']['right_status'] === 'offline' ? 'text-neutral-400' : 'text-red-600') }}">
                                    {{ number_format($device['current_reading']['sensor_right'], 2) }}
                                </div>
                            </div>
                        </div>
                        
                        {{-- Batch progress --}}
                        @if($device['current_batch'])
                            <div class="mt-3 flex justify-between text-xs text-neutral-500">
                                <span>{{ $device['current_batch']['duration'] }}</span>
                                <span>{{ number_format($device['current_batch']['measurement_count']) }} {{ __('pembacaan') }}</span>
                            </div>
                        @endif
                    </div>
                    
                    {{-- Action buttons --}}
                    <div class="flex flex-col border-l border-neutral-100 dark:border-neutral-700 p-2">
                        <x-text-button 
                            wire:click="selectDevice({{ $device['id'] }})"
                            class="px-3 py-2 mb-1 {{ $selectedDevice === $device['id'] ? 'bg-caldy-500 text-white' : '' }}">
                            <i class="icon-activity"></i>
                        </x-text-button>
                        <x-text-button 
                            wire:click="showBatchHistory({{ $device['id'] }})"
                            class="px-3 py-2">
                            <i class="icon-history"></i>
                        </x-text-button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Live Chart Area (Selected Device) --}}
    @if($selectedDevice && $selectedDeviceData)
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">
                    {{ __('Line') . ' ' . $selectedDeviceData['line'] . ' - ' . __('Data langsung') }}
                </h3>
                <div class="text-sm text-neutral-500">
                    {{ __('Resep: ') . ($selectedDeviceData['current_batch']['recipe_name'] ?? __('Tidak ada')) }}
                </div>
            </div>
            <div id="live-chart-container" class="h-80" wire:ignore></div>
        </div>
    @endif

    {{-- Recipe Recommendation Panel --}}
    @if($selectedDevice && $selectedDeviceData && $selectedDeviceData['current_batch'])
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('Status Rekomendasi Resep') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <div class="text-xs text-neutral-500 uppercase">{{ __('Batch Karet') }}</div>
                    <div class="font-medium">{{ $selectedDeviceData['current_batch']['rubber_batch_code'] }}</div>
                </div>
                <div>
                    <div class="text-xs text-neutral-500 uppercase">{{ __('Resep Digunakan') }}</div>
                    <div class="font-medium">{{ $selectedDeviceData['current_batch']['recipe_name'] }}</div>
                </div>
                <div>
                    <div class="text-xs text-neutral-500 uppercase">{{ __('Status') }}</div>
                    <div class="flex items-center gap-2">
                        @if($selectedDeviceData['current_batch']['recipe_recommendation']['is_following'])
                            <i class="icon-check-circle text-green-500"></i>
                            <span class="text-green-600">{{ __('Mengikuti rekomendasi') }}</span>
                        @else
                            <i class="icon-alert-circle text-yellow-500"></i>
                            <span class="text-yellow-600">{{ __('Override operator') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    function updateLiveChart(data) {
        // Mock chart implementation - will be replaced with actual charting library
        const container = document.getElementById('live-chart-container');
        if (container) {
            container.innerHTML = `
                <div class="flex items-center justify-center h-full bg-neutral-50 dark:bg-neutral-700 rounded">
                    <div class="text-center">
                        <div class="text-lg font-medium mb-2">Live Chart Placeholder</div>
                        <div class="text-sm text-neutral-500">
                            Latest: L:${data.sensor_left[data.sensor_left.length-1].toFixed(2)} 
                            R:${data.sensor_right[data.sensor_right.length-1].toFixed(2)}
                        </div>
                        <div class="text-xs text-neutral-400 mt-2">
                            Standard: ${data.std_min} - ${data.std_max}
                        </div>
                    </div>
                </div>
            `;
        }
    }
</script>