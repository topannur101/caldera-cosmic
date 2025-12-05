<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Models\LogDwpUptime;
use App\Models\InsDwpDevice;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout("layouts.app")] class extends Component {
    use WithPagination;
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public $device_id = null;

    public string $view = "uptime-monitoring";

    #[Url]
    public string $status_filter = "all";

    #[Url]
    public string $sort_by = "logged_at";

    #[Url]
    public string $sort_direction = "desc";

    public $timeRange = 24; // hours
    public $devices = [];
    public $deviceStats = [];
    public $refreshInterval = 30000; // 30 seconds in milliseconds
    public $todaySummary = [];

    public function mount()
    {
        $this->loadDevices();
        $this->calculateStats();
        $this->calculateTodaySummary();

        // update menu
        $this->dispatch("update-menu", $this->view);
    }

    public function loadDevices()
    {
        $this->devices = InsDwpDevice::where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function calculateStats()
    {
        $startDate = $this->start_at ? Carbon::parse($this->start_at) : now()->subHours($this->timeRange);
        $endDate = $this->end_at ? Carbon::parse($this->end_at) : now();

        $query = InsDwpDevice::where('is_active', true);
        
        if ($this->device_id) {
            $query->where('id', $this->device_id);
        }

        $devices = $query->get();
        $stats = [];

        foreach ($devices as $device) {
            $logs = LogDwpUptime::where('ins_dwp_device_id', $device->id)
                ->whereBetween('logged_at', [$startDate, $endDate])
                ->orderBy('logged_at', 'asc')
                ->get();

            $totalLogs = $logs->count();
            $onlineCount = $logs->where('status', 'online')->count();
            $offlineCount = $logs->where('status', 'offline')->count();
            $timeoutCount = $logs->where('status', 'timeout')->count();
            
            // Get latest status
            $latestLog = $logs->last();
            $currentStatus = $latestLog?->status ?? 'unknown';
            $currentTime = now();
            
            if ($logs->isEmpty()) {
                $stats[$device->id] = [
                    'device' => $device,
                    'current_status' => 'unknown',
                    'total_logs' => 0,
                    'online_count' => 0,
                    'offline_count' => 0,
                    'timeout_count' => 0,
                    'uptime_percentage' => 0,
                    'avg_response_time' => 0,
                    'longest_downtime' => 0,
                    'last_logged_at' => null,
                    'downtime_seconds' => 0,
                    'online_seconds' => 0,
                ];
                continue;
            }
            
            // === SUM ALL DURATIONS FROM DATABASE ===
            $onlineSeconds = 0;
            $offlineSeconds = 0;
            $timeoutSeconds = 0;
            $downtimePeriods = [];
            
            foreach ($logs as $log) {
                $duration = $log->duration_seconds ?? 0;
                
                // The duration represents time in PREVIOUS status
                if ($log->status === 'offline' || $log->status === 'timeout') {
                    // When switching TO offline/timeout, duration shows online time
                    $onlineSeconds += $duration;
                    if ($duration > 0) {
                        $downtimePeriods[] = $duration; // Track this as a downtime event
                    }
                } elseif ($log->status === 'online') {
                    // When switching TO online, duration shows offline/timeout time
                    $prevLog = $logs->where('logged_at', '<', $log->logged_at)
                                    ->sortByDesc('logged_at')
                                    ->first();
                    
                    if ($prevLog) {
                        if ($prevLog->status === 'timeout') {
                            $timeoutSeconds += $duration;
                        } else {
                            $offlineSeconds += $duration;
                        }
                    }
                }
            }
            
            // Add current ongoing status duration
            $lastLog = $logs->last();
            if ($lastLog) {
                $ongoingDuration = Carbon::parse($lastLog->logged_at)->diffInSeconds($currentTime);
                
                if ($lastLog->status === 'online') {
                    $onlineSeconds += $ongoingDuration;
                } elseif ($lastLog->status === 'offline') {
                    $offlineSeconds += $ongoingDuration;
                } elseif ($lastLog->status === 'timeout') {
                    $timeoutSeconds += $ongoingDuration;
                }
            }
            
            $downtimeSeconds = $offlineSeconds + $timeoutSeconds;
            $totalTrackedSeconds = $onlineSeconds + $offlineSeconds + $timeoutSeconds;
            
            // Calculate uptime percentage
            $uptimePercentage = $totalTrackedSeconds > 0 
                ? ($onlineSeconds / $totalTrackedSeconds) * 100 
                : 0;

            // Calculate average response time (average duration between status changes)
            $avgResponseTime = $totalLogs > 1 
                ? $totalTrackedSeconds / $totalLogs 
                : 0;

            // Get longest downtime
            $longestDowntime = count($downtimePeriods) > 0 ? max($downtimePeriods) : 0;

            $stats[$device->id] = [
                'device' => $device,
                'current_status' => $currentStatus,
                'total_logs' => $totalLogs,
                'online_count' => $onlineCount,
                'offline_count' => $offlineCount,
                'timeout_count' => $timeoutCount,
                'uptime_percentage' => round($uptimePercentage, 2),
                'avg_response_time' => round($avgResponseTime, 2),
                'longest_downtime' => $longestDowntime,
                'last_logged_at' => $latestLog?->logged_at,
                'downtime_seconds' => $downtimeSeconds,
                'online_seconds' => $onlineSeconds,
            ];
        }

        $this->deviceStats = $stats;
    }

    public function calculateTodaySummary()
    {
        $startOfDay = now()->startOfDay();
        $endOfDay = now()->endOfDay();
        $currentTime = now();

        $query = InsDwpDevice::where('is_active', true);
        
        if ($this->device_id) {
            $query->where('id', $this->device_id);
        }

        $devices = $query->get();
        
        $totalOnlineSeconds = 0;
        $totalOfflineSeconds = 0;
        $totalTimeoutSeconds = 0;
        $totalDevices = $devices->count();
        
        $totalOnlineCount = 0;
        $totalOfflineCount = 0;
        $totalTimeoutCount = 0;

        foreach ($devices as $device) {
            $logs = LogDwpUptime::where('ins_dwp_device_id', $device->id)
                ->whereBetween('logged_at', [$startOfDay, $endOfDay])
                ->orderBy('logged_at', 'asc')
                ->get();

            if ($logs->isEmpty()) {
                continue;
            }

            // === SUM ALL DURATIONS FROM DATABASE ===
            // duration_seconds represents time spent in PREVIOUS status before this log
            // So when status changes to 'offline', duration_seconds shows how long it was 'online'
            
            $deviceOnlineSeconds = 0;
            $deviceOfflineSeconds = 0;
            $deviceTimeoutSeconds = 0;
            
            foreach ($logs as $log) {
                $duration = $log->duration_seconds ?? 0;
                
                // The duration represents time in PREVIOUS status
                // So we categorize by the CURRENT status to know what just ended
                if ($log->status === 'offline' || $log->status === 'timeout') {
                    // When switching TO offline/timeout, duration shows online time
                    $deviceOnlineSeconds += $duration;
                } elseif ($log->status === 'online') {
                    // When switching TO online, duration shows offline/timeout time
                    // Check the previous log to determine which
                    $prevLog = $logs->where('logged_at', '<', $log->logged_at)
                                    ->sortByDesc('logged_at')
                                    ->first();
                    
                    if ($prevLog) {
                        if ($prevLog->status === 'timeout') {
                            $deviceTimeoutSeconds += $duration;
                        } else {
                            $deviceOfflineSeconds += $duration;
                        }
                    }
                }
            }
            
            // Add current ongoing status duration
            $lastLog = $logs->last();
            if ($lastLog) {
                $ongoingDuration = Carbon::parse($lastLog->logged_at)->diffInSeconds($currentTime);
                
                if ($lastLog->status === 'online') {
                    $deviceOnlineSeconds += $ongoingDuration;
                } elseif ($lastLog->status === 'offline') {
                    $deviceOfflineSeconds += $ongoingDuration;
                } elseif ($lastLog->status === 'timeout') {
                    $deviceTimeoutSeconds += $ongoingDuration;
                }
            }
            
            $totalOnlineSeconds += $deviceOnlineSeconds;
            $totalOfflineSeconds += $deviceOfflineSeconds;
            $totalTimeoutSeconds += $deviceTimeoutSeconds;
            
            // Count occurrences
            foreach ($logs as $log) {
                if ($log->status === 'online') $totalOnlineCount++;
                elseif ($log->status === 'offline') $totalOfflineCount++;
                elseif ($log->status === 'timeout') $totalTimeoutCount++;
            }
        }

        // Calculate percentages based on total tracked time
        $totalTrackedSeconds = $totalOnlineSeconds + $totalOfflineSeconds + $totalTimeoutSeconds;
        
        // If no tracked seconds, calculate based on expected time
        $expectedTotalSeconds = $totalDevices * $startOfDay->diffInSeconds($currentTime);
        
        $this->todaySummary = [
            'online_seconds' => $totalOnlineSeconds,
            'offline_seconds' => $totalOfflineSeconds,
            'timeout_seconds' => $totalTimeoutSeconds,
            'online_count' => $totalOnlineCount,
            'offline_count' => $totalOfflineCount,
            'timeout_count' => $totalTimeoutCount,
            'online_percentage' => $totalTrackedSeconds > 0 ? round(($totalOnlineSeconds / $totalTrackedSeconds) * 100, 2) : 0,
            'offline_percentage' => $totalTrackedSeconds > 0 ? round(($totalOfflineSeconds / $totalTrackedSeconds) * 100, 2) : 0,
            'timeout_percentage' => $totalTrackedSeconds > 0 ? round(($totalTimeoutSeconds / $totalTrackedSeconds) * 100, 2) : 0,
            'total_devices' => $totalDevices,
            'total_tracked_seconds' => $totalTrackedSeconds,
            'expected_seconds' => $expectedTotalSeconds,
        ];
    }

    #[On('refresh-stats')]
    public function refreshStats()
    {
        $this->calculateStats();
        $this->calculateTodaySummary();
    }

    public function updatedTimeRange()
    {
        if (!$this->start_at && !$this->end_at) {
            $this->start_at = now()->subHours($this->timeRange)->format('Y-m-d H:i:s');
            $this->end_at = now()->format('Y-m-d H:i:s');
        }
        $this->calculateStats();
    }

    public function updatedDeviceId()
    {
        $this->calculateStats();
        $this->calculateTodaySummary();
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedStartAt()
    {
        $this->calculateStats();
    }

    public function updatedEndAt()
    {
        $this->calculateStats();
    }

    public function sortBy($field)
    {
        if ($this->sort_by === $field) {
            $this->sort_direction = $this->sort_direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort_by = $field;
            $this->sort_direction = 'desc';
        }
    }

    public function exportToCSV()
    {
        $startDate = $this->start_at ? Carbon::parse($this->start_at) : now()->subHours($this->timeRange);
        $endDate = $this->end_at ? Carbon::parse($this->end_at) : now();

        $query = LogDwpUptime::with('device')
            ->whereBetween('logged_at', [$startDate, $endDate]);

        if ($this->device_id) {
            $query->where('ins_dwp_device_id', $this->device_id);
        }

        if ($this->status_filter !== 'all') {
            $query->where('status', $this->status_filter);
        }

        $logs = $query->orderBy($this->sort_by, $this->sort_direction)->get();

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Device Name', 'IP Address', 'Status', 'Logged At', 'Duration (seconds)', 'Message']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->device->name ?? 'N/A',
                    $log->device->ip_address ?? 'N/A',
                    $log->status,
                    $log->logged_at->format('Y-m-d H:i:s'),
                    $log->duration_seconds ?? 0,
                    $log->message ?? '',
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="uptime-logs-' . now()->format('Y-m-d-His') . '.csv"',
        ]);
    }

    public function with(): array
    {
        $startDate = $this->start_at ? Carbon::parse($this->start_at) : now()->subHours($this->timeRange);
        $endDate = $this->end_at ? Carbon::parse($this->end_at) : now();

        $query = LogDwpUptime::with('device')
            ->whereBetween('logged_at', [$startDate, $endDate]);

        if ($this->device_id) {
            $query->where('ins_dwp_device_id', $this->device_id);
        }

        if ($this->status_filter !== 'all') {
            $query->where('status', $this->status_filter);
        }

        $logs = $query->orderBy($this->sort_by, $this->sort_direction)
            ->paginate(20);

        return [
            'logs' => $logs,
        ];
    }
}; ?>

<div x-data="{ 
    autoRefresh: true,
    refreshInterval: @entangle('refreshInterval'),
    init() {
        this.startAutoRefresh();
    },
    startAutoRefresh() {
        if (this.autoRefresh) {
            setInterval(() => {
                if (this.autoRefresh) {
                    $wire.dispatch('refresh-stats');
                }
            }, this.refreshInterval);
        }
    }
}" class="space-y-6">
    
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ __('DWP Uptime Monitoring') }}
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Monitor device online/offline status and request timeouts') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button 
                @click="autoRefresh = !autoRefresh"
                :class="autoRefresh ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
                class="px-4 py-2 rounded-lg font-medium text-sm flex items-center gap-2 transition">
                <svg class="w-4 h-4" :class="autoRefresh ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span x-text="autoRefresh ? '{{ __('Auto Refresh ON') }}' : '{{ __('Auto Refresh OFF') }}'"></span>
            </button>
            <button 
                wire:click="exportToCSV" 
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                {{ __('Export CSV') }}
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Filters') }}</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Device Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {{ __('Device') }}
                </label>
                <select wire:model.live="device_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">{{ __('All Devices') }}</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}">{{ $device->name }} ({{ $device->ip_address }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {{ __('Status') }}
                </label>
                <select wire:model.live="status_filter" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="all">{{ __('All Status') }}</option>
                    <option value="online">{{ __('Online') }}</option>
                    <option value="offline">{{ __('Offline') }}</option>
                    <option value="timeout">{{ __('Timeout') }}</option>
                </select>
            </div>

            <!-- Start Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {{ __('Start Date') }}
                </label>
                <input type="datetime-local" wire:model.live="start_at" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>

            <!-- End Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {{ __('End Date') }}
                </label>
                <input type="datetime-local" wire:model.live="end_at" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>
        </div>

        <!-- Quick Time Range Buttons -->
        <div class="flex flex-wrap gap-2">
            <button wire:click="$set('timeRange', 1); updatedTimeRange()" class="px-3 py-1 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                {{ __('Last 1 Hour') }}
            </button>
            <button wire:click="$set('timeRange', 6); updatedTimeRange()" class="px-3 py-1 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                {{ __('Last 6 Hours') }}
            </button>
            <button wire:click="$set('timeRange', 24); updatedTimeRange()" class="px-3 py-1 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                {{ __('Last 24 Hours') }}
            </button>
            <button wire:click="$set('timeRange', 168); updatedTimeRange()" class="px-3 py-1 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                {{ __('Last 7 Days') }}
            </button>
            <button wire:click="$set('timeRange', 720); updatedTimeRange()" class="px-3 py-1 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                {{ __('Last 30 Days') }}
            </button>
        </div>
    </div>

    <!-- Today's Summary -->
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 dark:from-blue-700 dark:to-blue-800 rounded-lg shadow-lg p-6 text-white">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-2xl font-bold">{{ __('Ringkasan Hari Ini') }}</h3>
                <p class="text-blue-100 text-sm">{{ now()->format('l, d F Y') }}</p>
                @if(($todaySummary['total_tracked_seconds'] ?? 0) == 0)
                    <p class="text-yellow-200 text-xs mt-1">⚠️ {{ __('Belum ada data tracking untuk hari ini') }}</p>
                @else
                    <p class="text-blue-100 text-xs mt-1">
                        📊 {{ __('Total Logs') }}: {{ ($todaySummary['online_count'] ?? 0) + ($todaySummary['offline_count'] ?? 0) + ($todaySummary['timeout_count'] ?? 0) }}
                    </p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-blue-100 text-sm">{{ __('Total Device') }}</p>
                <p class="text-3xl font-bold">{{ $todaySummary['total_devices'] ?? 0 }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Online Summary -->
            <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-lg p-4 border border-white border-opacity-30">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 bg-green-500 rounded-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-blue-100">{{ __('Total Online') }}</p>
                        <p class="text-2xl font-bold">{{ gmdate('H:i:s', $todaySummary['online_seconds'] ?? 0) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-blue-100">{{ __('Persentase') }}</span>
                    <span class="font-semibold">{{ number_format($todaySummary['online_percentage'] ?? 0, 1) }}%</span>
                </div>
                <div class="mt-2 w-full bg-white bg-opacity-20 rounded-full h-2">
                    <div class="bg-green-400 h-2 rounded-full" style="width: {{ $todaySummary['online_percentage'] ?? 0 }}%"></div>
                </div>
            </div>

            <!-- Offline Summary -->
            <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-lg p-4 border border-white border-opacity-30">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 bg-red-500 rounded-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-blue-100">{{ __('Total Offline') }}</p>
                        <p class="text-2xl font-bold">{{ gmdate('H:i:s', $todaySummary['offline_seconds'] ?? 0) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-blue-100">{{ __('Persentase') }}</span>
                    <span class="font-semibold">{{ number_format($todaySummary['offline_percentage'] ?? 0, 1) }}%</span>
                </div>
                <div class="mt-2 w-full bg-white bg-opacity-20 rounded-full h-2">
                    <div class="bg-red-400 h-2 rounded-full" style="width: {{ $todaySummary['offline_percentage'] ?? 0 }}%"></div>
                </div>
            </div>

            <!-- Timeout Summary -->
            <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-lg p-4 border border-white border-opacity-30">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 bg-yellow-500 rounded-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-blue-100">{{ __('Total Timeout (RTO)') }}</p>
                        <p class="text-2xl font-bold">{{ gmdate('H:i:s', $todaySummary['timeout_seconds'] ?? 0) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-blue-100">{{ __('Persentase') }}</span>
                    <span class="font-semibold">{{ number_format($todaySummary['timeout_percentage'] ?? 0, 1) }}%</span>
                </div>
                <div class="mt-2 w-full bg-white bg-opacity-20 rounded-full h-2">
                    <div class="bg-yellow-400 h-2 rounded-full" style="width: {{ $todaySummary['timeout_percentage'] ?? 0 }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Device Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($deviceStats as $stat)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border-l-4 
                {{ $stat['current_status'] === 'online' ? 'border-green-500' : 
                   ($stat['current_status'] === 'timeout' ? 'border-yellow-500' : 'border-red-500') }}">
                
                <!-- Device Header -->
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $stat['device']->name }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $stat['device']->ip_address }}
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        {{ $stat['current_status'] === 'online' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 
                           ($stat['current_status'] === 'timeout' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' : 
                            'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100') }}">
                        {{ strtoupper($stat['current_status']) }}
                    </span>
                </div>

                <!-- Uptime Progress -->
                <div class="mb-4">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('Uptime') }}</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $stat['uptime_percentage'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all {{ $stat['uptime_percentage'] >= 95 ? 'bg-green-500' : ($stat['uptime_percentage'] >= 80 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                             style="width: {{ $stat['uptime_percentage'] }}%"></div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Online') }}</p>
                        <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $stat['online_count'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Offline') }}</p>
                        <p class="text-lg font-semibold text-red-600 dark:text-red-400">{{ $stat['offline_count'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Timeout (RTO)') }}</p>
                        <p class="text-lg font-semibold text-yellow-600 dark:text-yellow-400">{{ $stat['timeout_count'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Total Logs') }}</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $stat['total_logs'] }}</p>
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="space-y-2 text-sm border-t border-gray-200 dark:border-gray-700 pt-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('Avg Response Time') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $stat['avg_response_time'] }}s</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('Longest Downtime') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ gmdate('H:i:s', $stat['longest_downtime']) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('Last Update') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ $stat['last_logged_at'] ? $stat['last_logged_at']->diffForHumans() : 'N/A' }}
                        </span>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3 text-center py-12 text-gray-500 dark:text-gray-400">
                {{ __('No device data available') }}
            </div>
        @endforelse
    </div>

    <!-- Detailed Logs Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Detailed Logs') }}</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th wire:click="sortBy('logged_at')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-1">
                                {{ __('Logged At') }}
                                @if($sort_by === 'logged_at')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="{{ $sort_direction === 'asc' ? 'M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' : 'M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z' }}" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('Device') }}
                        </th>
                        <th wire:click="sortBy('status')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-1">
                                {{ __('Status') }}
                                @if($sort_by === 'status')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="{{ $sort_direction === 'asc' ? 'M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' : 'M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z' }}" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('duration_seconds')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-1">
                                {{ __('Duration') }}
                                @if($sort_by === 'duration_seconds')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="{{ $sort_direction === 'asc' ? 'M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' : 'M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z' }}" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('Message') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $log->logged_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="text-gray-900 dark:text-white font-medium">{{ $log->device->name ?? 'N/A' }}</div>
                                <div class="text-gray-500 dark:text-gray-400 text-xs">{{ $log->device->ip_address ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                    {{ $log->status === 'online' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 
                                       ($log->status === 'timeout' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' : 
                                        'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100') }}">
                                    {{ strtoupper($log->status) }}
                                    @if($log->status === 'timeout')
                                        <span class="ml-1">(RTO)</span>
                                    @endif
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $log->duration_seconds ? gmdate('H:i:s', $log->duration_seconds) : '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                {{ $log->message ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                {{ __('No logs found for the selected criteria') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $logs->links() }}
        </div>
    </div>

    <!-- Loading Indicator -->
    <div wire:loading class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 flex items-center gap-3">
            <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-900 dark:text-white font-medium">{{ __('Loading...') }}</span>
        </div>
    </div>
</div>
