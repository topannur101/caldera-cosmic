<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Traits\HasDateRangeFilter;
use App\Models\InsDwpDevice;
use App\Models\InsDwpCount;
use App\Models\InsDwpTimeAlarmCount;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    use HasDateRangeFilter;
    public array $machineData = [];
    public array $stdRange = [30, 40];
    public $lastRecord = null;
    public $view = "dashboard";

    #[Url]
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public string $line = "g5";

    public string $lines = "";

    // Add new properties for the top summary boxes
    public int $timeConstraintAlarm = 0;
    public int $longestQueueTime = 0;
    public int $alarmsActive = 0;

    // === NEW: Add property for online monitoring ===
    public array $onlineMonitoringData = ['online' => 100, 'offline' => 0];

    public function mount()
    {
        $this->dispatch("update-menu", $this->view);
        $this->updateData();
    }

    private function getDataLine($line=null)
    {
        $lines = [];
        $dataRaws = InsDwpDevice::orderBy("name")
            ->select("name", "id", "config")
            ->get()->toArray();
        foreach($dataRaws as $dataRaw){
            if (!empty($line)){
                if ($dataRaw['config'][0]['line'] == strtoupper($line)){
                    $lines[] = $dataRaw['config'][0];
                    break;
                }
            }else {
                $lines[] = $dataRaw['config'][0];
            }
        }
        return $lines;
    }

    /**
     * GET DATA MACHINES
     * Description : This code for get data machines on database ins_dwp_device
     */ 
    private function getDataMachines($selectedLine = null)
    {
        if (!$selectedLine) {
            return [];
        }

        // Query for the specific device that handles this line to avoid loading all of them.
        $device = InsDwpDevice::whereJsonContains('config', [['line' => strtoupper($selectedLine)]])
            ->select('config')
            ->first();

        if ($device) {
            foreach ($device->config as $lineConfig) {
                if (strtoupper($lineConfig['line']) === strtoupper($selectedLine)) {
                    return $lineConfig['list_mechine'] ?? [];
                }
            }
        }
        return [];
    }

    public function updateData()
    {
        $machineConfigs = $this->getDataMachines($this->line);
        $machineNames = array_column($machineConfigs, 'name');

        if (empty($machineNames)) {
            $this->machineData = [];
            return;
        }

        // === NEW: Calculate Online Monitoring Stats ===
        $this->onlineMonitoringData = $this->getOnlineMonitoringStats($machineNames);

        // --- Step 1: Get latest sensor reading for each machine (Your query is already efficient) ---
        $latestCountsQuery = InsDwpCount::select('mechine', 'position', 'pv', 'created_at')
            ->whereIn('id', function ($query) use ($machineNames) {
                $query->selectRaw('MAX(id)')
                    ->from('ins_dwp_counts')
                    ->whereIn('mechine', $machineNames)
                    ->groupBy('mechine', 'position');
            });

        // --- Step 2 (OPTIMIZED): Get all Left/Right output counts in a SINGLE query to prevent N+1 problem ---
        $outputsQuery = InsDwpCount::whereIn('mechine', $machineNames)
            ->selectRaw('mechine, position, count(*) as total')
            ->groupBy('mechine', 'position');

        // --- Step 3 (FIXED): Get all recent records for a correct average calculation ---
        $recentRecordsQuery = InsDwpCount::whereIn('mechine', $machineNames)
            ->where('created_at', '>=', now()->subDay())
            ->select('mechine', 'pv');

        // Apply date range filter to relevant queries
        if ($this->start_at && $this->end_at) {
            $start = Carbon::parse($this->start_at);
            $end = Carbon::parse($this->end_at)->endOfDay();
            $latestCountsQuery->whereBetween('created_at', [$start, $end]);
            $outputsQuery->whereBetween('created_at', [$start, $end]);
        }
        
        // Execute the queries
        $latestCounts = $latestCountsQuery->get();
        $allOutputs = $outputsQuery->get();
        $recentRecords = $recentRecordsQuery->get()->groupBy('mechine');

        // --- OPTIMIZATION: Process outputs into an easy-to-use lookup array ---
        $outputCounts = [];
        foreach ($allOutputs as $output) {
            $outputCounts[$output->mechine][$output->position] = $output->total;
        }

        // --- Step 4: Process all fetched data with no more N+1 queries ---
        $newData = [];
        foreach ($machineConfigs as $machine) {
            $machineName = $machine['name'];

            $leftLast = $latestCounts->where('mechine', $machineName)->where('position', 'L')->first();
            $rightLast = $latestCounts->where('mechine', $machineName)->where('position', 'R')->first();

            $leftPv = $leftLast ? (json_decode($leftLast->pv, true) ?? [0, 0]) : [0, 0];
            $rightPv = $rightLast ? (json_decode($rightLast->pv, true) ?? [0, 0]) : [0, 0];

            $leftData = ['side' => $leftPv[1] ?? 0, 'toeHeel' => $leftPv[0] ?? 0];
            $rightData = ['side' => $rightPv[1] ?? 0, 'toeHeel' => $rightPv[0] ?? 0];

            // --- FIXED: Correctly calculate average from all recent records ---
            $allPvs = [];
            if (isset($recentRecords[$machineName])) {
                foreach ($recentRecords[$machineName] as $record) {
                    $decodedPvs = json_decode($record->pv, true) ?? [];
                    if (is_array($decodedPvs)) {
                        $allPvs = array_merge($allPvs, $decodedPvs);
                    }
                }
            }
            $nonZeroValues = array_filter($allPvs, fn($v) => is_numeric($v) && $v > 0);
            $averagePressure = !empty($nonZeroValues) ? array_sum($nonZeroValues) / count($nonZeroValues) : 0;

            $statuses = [
                'leftToeHeel'  => $this->getStatus($leftData['toeHeel']),
                'leftSide'     => $this->getStatus($leftData['side']),
                'rightToeHeel' => $this->getStatus($rightData['toeHeel']),
                'rightSide'    => $this->getStatus($rightData['side']),
            ];

            $newData[] = [
                'id' => 'machine-' . $machineName,
                'name' => $machineName,
                'sensors' => [
                    'left'  => ['toeHeel' => ['value' => $leftData['toeHeel'], 'status' => $statuses['leftToeHeel']], 'side' => ['value' => $leftData['side'], 'status' => $statuses['leftSide']]],
                    'right' => ['toeHeel' => ['value' => $rightData['toeHeel'], 'status' => $statuses['rightToeHeel']], 'side' => ['value' => $rightData['side'], 'status' => $statuses['rightSide']]],
                ],
                'lastDataSensors' => [
                    'left'  => $leftLast ? $leftLast->toArray() : null,
                    'right' => $rightLast ? $rightLast->toArray() : null,
                ],
                'overallStatus' => in_array('alert', $statuses) ? 'alert' : 'normal',
                'average' => $averagePressure,
                // --- OPTIMIZED: Use the pre-fetched output counts ---
                'output' => [
                    'left'  => $outputCounts[$machineName]['L'] ?? 0,
                    'right' => $outputCounts[$machineName]['R'] ?? 0,
                ],
            ];
        }

        $this->machineData = $newData;
        $performanceData = $this->getPerformanceData($newData);

        // update alarm and summary data
        $this->longestQueueTime = $this->getLongestDuration()['duration'] ?? 0;
        $this->alarmsActive = $this->getLongestDuration()['cumulative'] ?? 0;
        $this->dispatch('data-updated', performance: $performanceData);
    }

    // === NEW: Add this entire function to calculate online/offline status ===
    private function getOnlineMonitoringStats(array $machineNames)
    {
        // Define the period based on filters, or default to last 24h
        $start = ($this->start_at) ? Carbon::parse($this->start_at) : now()->subDay();
        $start = $start->startOfDay();
        $end = ($this->end_at) ? Carbon::parse($this->end_at)->endOfDay() : now();
        $end = $end->endOfDay();


        $totalPeriodInSeconds = $end->diffInSeconds($start);
        if ($totalPeriodInSeconds <= 0) {
            return ['online' => 100, 'offline' => 0]; // Avoid division by zero
        }
        // If no machines, entire period is offline
        if (empty($machineNames)) {
            return ['online' => 0, 'offline' => 100];
        }

        // Define how long a gap must be to be considered "Downtime"
        $downtimeThresholdInSeconds = 120; // 2 minutes
        $totalDowntimeInSeconds = 0;
        $lastTimestamp = null;
        $hasRecords = false;

        // Use a cursor to avoid loading all records into memory
        $records = InsDwpCount::whereIn('mechine', $machineNames)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'asc')
            ->select('created_at')
            ->cursor();
            
        foreach ($records as $record) {
            $hasRecords = true;
            if ($lastTimestamp === null) {
                // First record. Check gap from the start of the period.
                $gap = $record->created_at->diffInSeconds($start);
                if ($gap > $downtimeThresholdInSeconds) {
                    $totalDowntimeInSeconds += $gap;
                }
            } else {
                // Subsequent record. Check gap from the last one.
                $gap = $record->created_at->diffInSeconds($lastTimestamp);
                if ($gap > $downtimeThresholdInSeconds) {
                    $totalDowntimeInSeconds += $gap;
                }
            }
            $lastTimestamp = $record->created_at;
        }

        if (!$hasRecords) {
            // No records at all in the period. The whole period is downtime.
            $totalDowntimeInSeconds = $totalPeriodInSeconds;
        } else {
            // After the loop, check the gap from the last record to the end of the period.
            $gap = $end->diffInSeconds($lastTimestamp);
            if ($gap > $downtimeThresholdInSeconds) {
                $totalDowntimeInSeconds += $gap;
            }
        }

        // Calculate percentages
        $onlineSeconds = $totalPeriodInSeconds - $totalDowntimeInSeconds;
        $onlineSeconds = max(0, $onlineSeconds); // Ensure it's not negative

        $onlinePercent = ($onlineSeconds / $totalPeriodInSeconds) * 100;
        $offlinePercent = 100 - $onlinePercent;

        return [
            'online' => round($onlinePercent, 2),
            'offline' => round($offlinePercent, 2),
        ];
    }

    // NEW: A function to calculate data for the new charts
    public function getPerformanceData(array $machineData)
    {
        $totalMachines = count($machineData);
        $outOfStandard = 0;

        // Data for the Horizontal Bar Chart (Performa AVG Pressure)
        $avgPressures = [
            'labels' => [],
            'data' => []
        ];

        foreach($machineData as $machine) {
            if ($machine['overallStatus'] === 'alert') {
                $outOfStandard++;
            }
            $avgPressures['labels'][] = $machine['name'];
            $avgPressures['data'][] = round($machine['average'], 1);
        }

        $dataReads = $this->getPressureReadingStats();
        $outOfStandard = $dataReads['not_standard_count'] ?? $outOfStandard;
        $standardReads = $dataReads['standard_count'] ?? 0;
        $totalReads = $dataReads['total_count'] ?? 1;
        //prevent division by zero
        if ($totalReads == 0) {
            $totalReads = 1;
        }
        // Data for the Donut Chart (Daily Performance) - sourced from DB by date range
        $standard = ($standardReads / $totalReads) * 100;
        $outOfStandard = ($outOfStandard / $totalReads) * 100;
        $dailyPerformance = [
            'standard' => round($standard, 2),
            'outOfStandard' => round($outOfStandard, 2)
        ];

        return [
            'daily' => $dailyPerformance,
            'avgPressures' => $avgPressures
        ];
    }

    public function getPressureReadingStats()
    {
        // Define standard range for clarity
        [$minStd, $maxStd] = $this->stdRange;

        $standardReadings = 0;
        $notStandardReadings = 0;

        // Get machine names for the current line
        $machineConfigs = $this->getDataMachines($this->line);
        $machineNames = array_column($machineConfigs, 'name');

        if (empty($machineNames)) {
            return ['standard_counts' => 0, 'not_standard_counts' => 0];
        }
        
        // Use a cursor to process records one by one to save memory
        $records = InsDwpCount::whereIn('mechine', $machineNames)
            ->select('pv');

        if ($this->start_at && $this->end_at) {
            $start = Carbon::parse($this->start_at);
            $end = Carbon::parse($this->end_at)->endOfDay();
            $records->whereBetween('created_at', [$start, $end]);
        }
        $records = $records->cursor();
        foreach ($records as $record) {
            $pvValues = json_decode($record->pv, true);

            if (is_array($pvValues)) {
                foreach ($pvValues as $value) {
                    if (is_numeric($value)) {
                        if ($value >= $minStd && $value <= $maxStd) {
                            $standardReadings++;
                        } else {
                            $notStandardReadings++;
                        }
                    }
                }
            }
        }

        return [
            'total_count' => $standardReadings + $notStandardReadings,
            'standard_count' => $standardReadings,
            'not_standard_count' => $notStandardReadings,
        ];
    }

    public function checkLastData()
    {
        $this->lastRecord = InsDwpCount::latest()->first();
    }

    private function getDataPVByMachine($machineId, $position = "L", $limit = 20)
    {
        try {
            $records = InsDwpCount::where("mechine", $machineId)
                ->where('position', $position)
                ->limit($limit)
                ->orderBy('created_at', "desc")
                ->get();

            if ($records->isEmpty()) {
                // Return a random value if DB is empty to simulate live data
                return ["side" => 0, "toeHeel" => 0];
            }
            $dataSide = $records->pluck('pv')->map(fn($pv) => json_decode($pv, true)[1] ?? 0);
            $dataToeHeel = $records->pluck('pv')->map(fn($pv) => json_decode($pv, true)[0] ?? 0);

            return [
                "side" => $this->getMedian($dataSide->all()),
                "toeHeel" => $this->getMedian($dataToeHeel->all())
            ];
        } catch (\Exception $e) {
            return ["side" => 0, "toeHeel" => 0];
        }
    }

    private function getStatus($value)
    {
        if ($value > $this->stdRange[1] || $value < $this->stdRange[0]) {
            return 'alert';
        }
        if ($value > ($this->stdRange[1] - 1) || $value < ($this->stdRange[0] + 1)) {
            return 'warning';
        }
        return 'normal';
    }

    private function getOutPutByMechineId($machineId, $position = "L")
    {
        $totalData = InsDwpCount::where("mechine", $machineId)
            ->where('position', $position)
            ->get()
            ->count();
        return $totalData;
    }

    private function getMedian(array $array)
    {
        if (empty($array)) return 0;
        sort($array);
        $count = count($array);
        $middle = floor(($count - 1) / 2);
        return ($count % 2) ? $array[$middle] : ($array[$middle] + $array[$middle + 1]) / 2;
    }

    public function with(): array
    {
        $longestDuration = $this->getLongestDuration();

        return [
            'machineData' => $this->machineData,
            'longestDurationValue' => $longestDuration['duration'] ?? 'N/A',
            'longestDurationCumulative' => $longestDuration['cumulative'] ?? 'N/A',
        ];
    }

    private function getLongestDuration(){
        // GET LONG DURATION DATA from database
        $longDurationData = InsDwpTimeAlarmCount::orderBy('duration', 'desc')->first();
        if (empty($longDurationData)){
            return [];
        }else {
            return $longDurationData->toArray();
        }
    }

    #[On("updated")]
    public function update()
    {
        $this->generateCharts();
    }

    // Livewire lifecycle hooks: when these public properties change, recompute data & charts
    public function updatedStdRange($value)
    {
        // stdRange changed — recompute machine data and charts
        $this->updateData();
    }

    public function updatedStartAt($value)
    {
        $this->updateData();
    }

    public function updatedEndAt($value)
    {
        $this->updateData();
    }

    public function updatedLine($value)
    {
        $this->updateData();
    }

    private function generateCharts()
    {
        $this->dispatch('refresh-charts', [
                    'avgPressures' => $this->getPerformanceData($this->machineData)['avgPressures'],
                    'dailyChartData' => $this->getPerformanceData($this->machineData)['daily'],
                    // === NEW: Pass the online monitoring data to the chart ===
                    'onlineMonitoringData' => $this->onlineMonitoringData,
                ]);
    }
}; ?>

<div>
    <!-- filter section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 flex items-center">
            <div>
                <div class="flex mb-2 text-xs text-neutral-500">
                    <x-dropdown align="left" width="48">
                        <x-slot name="trigger">
                            <x-text-button class="uppercase ml-3">
                                {{ __("Rentang") }}
                                <i class="icon-chevron-down ms-1"></i>
                            </x-text-button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link href="#" wire:click.prevent="setToday">
                                    {{ __("Hari ini") }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                    {{ __("Kemarin") }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">
                                    {{ __("Minggu ini") }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">
                                    {{ __("Minggu lalu") }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                    {{ __("Bulan ini") }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                    {{ __("Bulan lalu") }}
                                </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" type="date" class="w-40" />
                    <x-text-input wire:model.live="end_at" type="date" class="w-40" />
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-4 h-16"></div>
            <div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line") }}</label>
                <x-select wire:model.live="line" class="w-full lg:w-32">
                    @foreach($this->getDataLine() as $lineData)
                        <option value="{{$lineData['line']}}">{{$lineData['line']}}</option>
                    @endforeach
                </x-select>
            </div>
        </div>
        
        <div class="grid grid-cols-1 gap-6">
            <div class="grid grid-cols-3 bg-white dark:bg-neutral-800 p-6 rounded-lg shadow-md">
                <div class="flex flex-col w-10 font-semibold text-neutral-700 dark:text-neutral-200 text-sm">
                    Time
                    Constraint
                    Alarm
                </div>
                <div class="flex flex-col font-semibold text-neutral-700 dark:text-neutral-200 text-sm">
                    <p>Long Queue time</p>
                    <p class="text-3xl">{{ $this->longestQueueTime }}</p>
                </div>
                <div class="flex flex-col font-semibold text-neutral-700 dark:text-neutral-200 text-sm">
                    <p>Alarm Active</p>
                    <p class="text-3xl">{{ $this->alarmsActive }}</p>
                </div>
            </div>
        </div>
    </div>
    <!-- end filter section -->

    <!-- Charts section -->
    <div class="relative">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" wire:poll.15s="updateData">
            <div class="lg:col-span-1 flex flex-col gap-9">
                <div class="bg-white dark:bg-neutral-800 p-6 rounded-lg shadow-md mb-2">
                    <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200 mb-4">Performance Machine</h2>
                    <div class="flex items-center justify-around gap-4">
                        <div class="relative h-40 w-40">
                            <canvas id="dailyPerformanceChart" wire:ignore></canvas>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <span class="w-4 h-4 rounded bg-green-500"></span>
                                <span>Standart</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-4 h-4 rounded bg-red-500"></span>
                                <span>Out Of Standart</span>
                            </div>
                        </div>
                    </div>
                </div>
    
                <div class="bg-white dark:bg-neutral-800 p-6 rounded-lg shadow-md">
                    <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200 mb-4">
                        Online System Monitoring
                        <span class="text-sm text-neutral-600 dark:text-neutral-400">8 hours online</span>
                    </h2>
                    <div class="flex items-center justify-around gap-4">
                    <div class="relative h-40 w-40">
                        <canvas id="onlineSystemMonitoring" wire:ignore></canvas>
                    </div>
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded bg-green-500"></span>
                            <span>Online</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded bg-gray-300"></span>
                            <span>Offline</span>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
    
            <div class="lg:col-span-2 relative">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @forelse ($machineData as $machine)
                        <div class="relative p-6 bg-white dark:bg-neutral-800 border-4 shadow-md rounded-xl ml-8 mb-8
                            @if($machine['overallStatus'] == 'alert') border-red-500 @else border-transparent @endif">
                            <div class="absolute top-[40px] -left-5 px-2 py-2 bg-white dark:bg-neutral-800
                                border-4 rounded-lg text-2xl font-bold
                                @if($machine['overallStatus'] == 'alert') border-red-500 @else bg-green-500 @endif">
                                #{{ $machine['name'] }}
                            </div>
                            <div class="rounded-lg transition-colors duration-300">
                                <div class="grid grid-cols-2 gap-2 text-center">
                                    <div>
                                        <h4 class="font-semibold text-neutral-700 dark:text-neutral-200">LEFT</h4>
                                        <p class="text-sm text-neutral-600 dark:text-neutral-400">Toe/Hell</p>
                                        <div class="p-2 rounded-md text-white font-bold text-lg mb-2 @if($machine['sensors']['left']['toeHeel']['status'] == 'alert') bg-red-500 @else bg-green-500 @endif">
                                            {{ $machine['sensors']['left']['toeHeel']['value'] }}
                                        </div>
                                        <p class="text-sm text-neutral-600 dark:text-neutral-400">Side</p>
                                        <div class="p-2 rounded-md text-white font-bold text-lg @if($machine['sensors']['left']['side']['status'] == 'alert') bg-red-500 @else bg-green-500 @endif">
                                            {{ $machine['sensors']['left']['side']['value'] }}
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-neutral-700 dark:text-neutral-200">RIGHT</h4>
                                        <p class="text-sm text-neutral-600 dark:text-neutral-400">Toe/Hell</p>
                                        <div class="p-2 rounded-md text-white font-bold text-lg mb-2 @if($machine['sensors']['right']['toeHeel']['status'] == 'alert') bg-red-500 @else bg-green-500 @endif">
                                            {{ $machine['sensors']['right']['toeHeel']['value'] }}
                                        </div>
                                        <p class="text-sm text-neutral-600 dark:text-neutral-400">Side</p>
                                        <div class="p-2 rounded-md text-white font-bold text-lg @if($machine['sensors']['right']['side']['status'] == 'alert') bg-red-500 @else bg-green-500 @endif">
                                            {{ $machine['sensors']['right']['side']['value'] }}
                                        </div>
                                    </div>
                                </div>
                                <!-- make output section -->
                                <div class="grid grid-cols-2 gap-2 text-center mt-4">
                                    <div>
                                        <h2 class="text-md text-neutral-600 dark:text-neutral-400">Output</h2>
                                        <div class="p-2 rounded-md dark:bg-neutral-900 font-bold text-lg mb-2">
                                            {{ $machine['output']['left'] ?? 0 }}
                                        </div>
                                    </div>
                                    <div>
                                        <h2 class="text-md text-neutral-600 dark:text-neutral-400">Output</h2>
                                        <div class="p-2 rounded-md dark:bg-neutral-900 font-bold text-lg mb-2">
                                            {{ $machine['output']['right'] ?? 0 }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="md:col-span-2 text-center text-neutral-500">No machine data available for the selected line.</p>
                    @endforelse
                </div>
            </div>
        </div>
    <div class="absolute top-[150px] -translate-y-2/3 right-0 translate-x-1/2 bg-white dark:bg-neutral-900 p-2 rounded-lg shadow-lg flex flex-col items-center justify-center w-10 border">
            <h3 class="text-lg font-semibold text-slate-800">STD</h3>
            <div class="text-xl font-bold text-blue-700 my-2">
                <p>{{ $stdRange[0] }} ~ {{ $stdRange[1] }}</p>
            </div>
            <p class="text-sm text-slate-500">kg</p>
        </div>
        @if(count($machineData) > 2)
    <div class="absolute top-[450px] -translate-y-2/3 right-0 translate-x-1/2 bg-white dark:bg-neutral-900 p-2 rounded-lg shadow-lg flex flex-col items-center justify-center w-10 border">
            <h3 class="text-lg font-semibold text-slate-800">STD</h3>
            <div class="text-xl font-bold text-blue-700 my-2">
                <p>{{ $stdRange[0] }} ~ {{ $stdRange[1] }}</p>
            </div>
            <p class="text-sm text-slate-500">kg</p>
        </div>
        @endif
    </div>
    @script
    <script>
        let dailyPerformanceChart, onlineSystemMonitoringChart;
        // store last data so we can re-init when theme toggles
        let __lastDaily = null;
        let __lastAvg = null;
        let __lastOnline = null;

        function isDarkMode() {
            try {
                return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches || document.documentElement.classList.contains('dark');
            } catch (e) { return false; }
        }

        function chartOptionsForTheme() {
            const dark = isDarkMode();
            return {
                textColor: dark ? '#e6edf3' : '#0f172a',
                gridColor: dark ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.06)'
            };
        }

        function initCharts(dailyPerformanceData, onlineData) {
            __lastDaily = dailyPerformanceData;
            __lastOnline = onlineData;

            const theme = chartOptionsForTheme();
            // Daily Performance Chart
            const dailyPerformanceCtx = document.getElementById('dailyPerformanceChart');
            if (dailyPerformanceChart) dailyPerformanceChart.destroy();
            dailyPerformanceChart = new Chart(dailyPerformanceCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Standart', 'Out Of Standart'],
                    datasets: [{
                        data: [dailyPerformanceData.standard, dailyPerformanceData.outOfStandard],
                        backgroundColor: ['#22c55e', '#ef4444'],
                        textColor: theme.textColor,
                        hoverOffset: 30,
                        borderWidth: 0
                    }]
                },
                options: {
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { bodyColor: theme.textColor, titleColor: theme.textColor }
                    }
                }
            });

            // Online System Monitoring Chart PIE
            const onlineSystemMonitoringCtx = document.getElementById('onlineSystemMonitoring');
        if (onlineSystemMonitoringChart) onlineSystemMonitoringChart.destroy();
            onlineSystemMonitoringChart = new Chart(onlineSystemMonitoringCtx, {
            type: 'pie', // You can also use 'doughnut' here if you prefer
            data: {
                // === NEW: Use dynamic labels ===
                labels: ['Online', 'Offline'],
                datasets: [{
                    label: 'System Online Monitoring',
                    // === NEW: Use dynamic data from the `onlineData` argument ===
                    data: [onlineData.online, onlineData.offline],
                    borderWidth: 1,
                    backgroundColor: ['#22c55e', '#d1d5db'], // Green (Online), Gray (Offline)
                    borderRadius: 5,
                }]
            },
            // === NEW: Added options for tooltips ===
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }, // Your HTML already has a legend
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                // Add a '%' sign
                                if (context.parsed !== null) {
                                    label += context.parsed.toFixed(2) + '%';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
        }
        // Listen for refresh event
        $wire.on('refresh-charts', function(payload) {
            // Normalize payload: Livewire may pass the data directly, or inside .detail, or as the first array item
            let data = payload;
            console.log('[DWP Dashboard] refresh-charts payload received', payload);
            if (payload && payload.detail) data = payload.detail;
            if (Array.isArray(payload) && payload.length) data = payload[0];
            if (payload && payload[0] && (payload[0].avgPressures || payload[0].dailyChartData)) data = payload[0];

            const onlineData = data?.onlineMonitoringData ?? null;
            const dailyChartData = data?.dailyChartData ?? data?.daily ?? data?.performance?.daily ?? null;

            if (!dailyChartData && !onlineData) {
                console.warn('[DWP Dashboard] refresh-charts payload missing expected properties', data);
                return;
            }

            try {
                initCharts(dailyChartData ?? { standard: 100, outOfStandard: 0 }, onlineData ?? { online: 100, offline: 0 });
            } catch (e) {
                console.error('[DWP Dashboard] error while initializing charts', e, data);
            }
        });

        // watch for theme changes: prefers-color-scheme and document class toggles
        try {
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                    if (__lastDaily && __lastOnline) initCharts(__lastDaily, __lastOnline);
                });
            }
        } catch (e) {}

        // If your app toggles .dark on <html>, observe it and re-init charts
        try {
            const obs = new MutationObserver(() => { if (__lastDaily && __lastOnline) initCharts(__lastDaily, __lastOnline); });
            obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        } catch (e) {}

        // Initial load
        $wire.$dispatch('updated');
    </script>
    @endscript
</div>