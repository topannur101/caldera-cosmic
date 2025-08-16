<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

new #[Layout('layouts.app')] 
class extends Component
{
    public $output = '';
    public $selectedDaemon = '';
    
    public $daemons = [
        'queue' => [
            'label' => 'Queue Worker',
            'description' => 'Process queued jobs continuously',
            'command' => 'queue:work',
            'category' => 'Laravel Core'
        ],
        'schedule' => [
            'label' => 'Schedule Worker', 
            'description' => 'Run scheduled tasks continuously',
            'command' => 'schedule:work',
            'category' => 'Laravel Core'
        ],
        'clm' => [
            'label' => 'Climate Monitoring',
            'description' => 'Monitor environmental conditions',
            'command' => 'app:ins-clm-poll --d',
            'category' => 'Insights System'
        ],
        'ctc' => [
            'label' => 'CTC Polling',
            'description' => 'Calendar thickness control monitoring',
            'command' => 'app:ins-ctc-poll --d',
            'category' => 'Insights System'
        ],
        'stc' => [
            'label' => 'STC Routine',
            'description' => 'Stabilization temperature control',
            'command' => 'app:ins-stc-routine --d',
            'category' => 'Insights System'
        ]
    ];

    public function mount()
    {
        if (Auth::id() !== 1) {
            abort(403, 'Access denied. Superuser only.');
        }
    }

    public function startDaemon($daemonKey)
    {
        if (Auth::id() !== 1) {
            return;
        }

        if (!array_key_exists($daemonKey, $this->daemons)) {
            $this->addOutput("❌ Unknown daemon: {$daemonKey}");
            return;
        }

        if ($this->isDaemonRunning($daemonKey)) {
            $this->addOutput("⚠️ {$this->daemons[$daemonKey]['label']} is already running");
            return;
        }

        $command = $this->daemons[$daemonKey]['command'];
        $this->addOutput("🚀 Starting: php artisan {$command}");

        try {
            // Use full path to artisan for Windows compatibility
            $artisanPath = base_path('artisan');
            $fullCommand = "php \"$artisanPath\" {$command}";
            
            $process = Process::start($fullCommand);
            $pid = $process->id();
            
            if ($pid) {
                Cache::put("daemon_pid_{$daemonKey}", $pid, now()->addDays(1));
                Cache::put("daemon_started_{$daemonKey}", now(), now()->addDays(1));
                
                $this->addOutput("✅ {$this->daemons[$daemonKey]['label']} started successfully (PID: {$pid})");
                
                Log::info("Daemon started", [
                    'daemon' => $daemonKey,
                    'command' => $command,
                    'pid' => $pid,
                    'user_id' => Auth::id()
                ]);
            } else {
                $this->addOutput("❌ Failed to get PID for {$this->daemons[$daemonKey]['label']}");
            }
            
        } catch (\Exception $e) {
            $this->addOutput("❌ Failed to start {$this->daemons[$daemonKey]['label']}: " . $e->getMessage());
            Log::error("Failed to start daemon", [
                'daemon' => $daemonKey,
                'error' => $e->getMessage()
            ]);
        }
        
        // Trigger UI refresh
        $this->dispatch('$refresh');
    }

    public function stopDaemon($daemonKey)
    {
        if (Auth::id() !== 1) {
            return;
        }

        if (!array_key_exists($daemonKey, $this->daemons)) {
            $this->addOutput("❌ Unknown daemon: {$daemonKey}");
            return;
        }

        $pid = Cache::get("daemon_pid_{$daemonKey}");
        
        if (!$pid) {
            $this->addOutput("⚠️ No PID found for {$this->daemons[$daemonKey]['label']}");
            return;
        }

        $this->addOutput("🛑 Stopping {$this->daemons[$daemonKey]['label']} (PID: {$pid})");

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $result = Process::run("taskkill /PID {$pid} /F");
            } else {
                $result = Process::run("kill {$pid}");
            }
            
            Cache::forget("daemon_pid_{$daemonKey}");
            Cache::forget("daemon_started_{$daemonKey}");
            
            if ($result->successful()) {
                $this->addOutput("✅ {$this->daemons[$daemonKey]['label']} stopped successfully");
            } else {
                $this->addOutput("⚠️ Stop command executed, process may have already ended");
            }
            
            Log::info("Daemon stopped", [
                'daemon' => $daemonKey,
                'pid' => $pid,
                'user_id' => Auth::id()
            ]);
            
        } catch (\Exception $e) {
            $this->addOutput("❌ Failed to stop {$this->daemons[$daemonKey]['label']}: " . $e->getMessage());
            Log::error("Failed to stop daemon", [
                'daemon' => $daemonKey,
                'pid' => $pid,
                'error' => $e->getMessage()
            ]);
        }
        
        // Trigger UI refresh
        $this->dispatch('$refresh');
    }

    public function isDaemonRunning($daemonKey)
    {
        if (!array_key_exists($daemonKey, $this->daemons)) {
            return false;
        }
        
        $pid = Cache::get("daemon_pid_{$daemonKey}");
        
        if (!$pid) {
            return false;
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $result = Process::run("tasklist /PID {$pid} /FO CSV /NH");
                if ($result->successful()) {
                    $output = $result->output();
                    // Check if PID exists and process is still running
                    return !empty($output) && str_contains($output, (string)$pid);
                }
                return false;
            } else {
                $result = Process::run("kill -0 {$pid}");
                return $result->successful();
            }
        } catch (\Exception $e) {
            // If we can't check the process, assume it's not running and clean up
            Cache::forget("daemon_pid_{$daemonKey}");
            Cache::forget("daemon_started_{$daemonKey}");
            return false;
        }
    }

    public function getDaemonUptime($daemonKey)
    {
        $startTime = Cache::get("daemon_started_{$daemonKey}");
        
        if (!$startTime || !$this->isDaemonRunning($daemonKey)) {
            return null;
        }
        
        return $startTime->diffForHumans(null, true);
    }

    public function startAllDaemons()
    {
        $this->addOutput("🚀 Starting all daemons...");
        
        foreach (array_keys($this->daemons) as $daemonKey) {
            if (!$this->isDaemonRunning($daemonKey)) {
                $this->startDaemon($daemonKey);
                usleep(500000); // 0.5 second delay between starts
            }
        }
        
        $this->addOutput("✅ All daemon start commands completed");
        $this->dispatch('$refresh');
    }

    public function stopAllDaemons()
    {
        $this->addOutput("🛑 Stopping all daemons...");
        
        foreach (array_keys($this->daemons) as $daemonKey) {
            if ($this->isDaemonRunning($daemonKey)) {
                $this->stopDaemon($daemonKey);
            }
        }
        
        $this->addOutput("✅ All daemon stop commands completed");
        $this->dispatch('$refresh');
    }

    public function clearOutput()
    {
        $this->output = '';
    }

    private function addOutput($message)
    {
        $timestamp = now()->format('H:i:s');
        $this->output .= "[{$timestamp}] {$message}\n";
    }

    public function groupedDaemons()
    {
        return collect($this->daemons)->map(function($daemon, $key) {
            return array_merge($daemon, ['daemon_key' => $key]);
        })->groupBy('category');
    }

    public function getRunningCount()
    {
        return collect($this->daemons)->keys()->filter(fn($key) => $this->isDaemonRunning($key))->count();
    }

    #[On('refresh-status')]
    public function refreshStatus()
    {
        // This method will trigger a component refresh
        // when called from JavaScript
    }
}; ?>

<x-slot name="title">{{ __('Daemon') . ' — ' . __('Admin') }}</x-slot>

<x-slot name="header">
    <x-nav-admin>{{ __('Daemon') }}</x-nav-admin>
</x-slot>

<div class="py-12 text-neutral-800 dark:text-neutral-200">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Warning Banner -->
        <div class="relative text-neutral h-32 sm:rounded-lg overflow-hidden mb-8 border border-dashed border-red-300 dark:border-red-500 bg-red-50 dark:bg-red-900/20">
            <div class="absolute top-0 left-0 flex h-full items-center px-4 lg:px-8 text-red-600 dark:text-red-400">
                <div>
                    <div class="uppercase font-bold mb-2"><i class="icon-triangle-alert me-2"></i>{{ __('Daemon Process Manager') }}</div>
                    <div>{{ __('Manage background processes that run continuously. Processes continue running after you leave this page.') }}</div>
                </div>
            </div>
        </div>

        <!-- Status Overview -->
        <div class="mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-neutral-800 p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <div class="text-2xl font-bold text-green-600">{{ $this->getRunningCount() }}</div>
                            <div class="text-sm text-neutral-500">{{ __('Running') }}</div>
                        </div>
                        <div class="text-green-600">
                            <i class="icon-play-circle text-2xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-neutral-800 p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <div class="text-2xl font-bold text-neutral-600">{{ count($this->daemons) - $this->getRunningCount() }}</div>
                            <div class="text-sm text-neutral-500">{{ __('Stopped') }}</div>
                        </div>
                        <div class="text-neutral-600">
                            <i class="icon-stop-circle text-2xl"></i>
                        </div>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <div class="flex gap-3 h-full">
                        <button 
                            wire:click="startAllDaemons"
                            class="flex-1 bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors"
                        >
                            <i class="icon-play me-2"></i>{{ __('Start All') }}
                        </button>
                        <button 
                            wire:click="stopAllDaemons"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-medium py-3 px-4 rounded-lg transition-colors"
                        >
                            <i class="icon-stop me-2"></i>{{ __('Stop All') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Daemon Controls -->
            <div class="lg:col-span-2">
                <h2 class="text-2xl font-bold mb-6">{{ __('Daemon Processes') }}</h2>
                
                @foreach($this->groupedDaemons() as $category => $categoryDaemons)
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3 text-caldy-600 dark:text-caldy-400">{{ $category }}</h3>
                        <div class="bg-white dark:bg-neutral-800 shadow overflow-hidden sm:rounded-lg divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach($categoryDaemons as $daemon)
                                @php 
                                    $daemonKey = $daemon['daemon_key'];
                                    $isRunning = $this->isDaemonRunning($daemonKey);
                                @endphp
                                <div class="p-6">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <h4 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ $daemon['label'] }}</h4>
                                                @if($isRunning)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        <span class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></span>
                                                        {{ __('Running') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200">
                                                        <span class="w-2 h-2 bg-neutral-400 rounded-full mr-1"></span>
                                                        {{ __('Stopped') }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-2">{{ $daemon['description'] }}</p>
                                            <code class="text-xs text-neutral-600 dark:text-neutral-300 bg-neutral-100 dark:bg-neutral-700 px-2 py-1 rounded">
                                                php artisan {{ $daemon['command'] }}
                                            </code>
                                            @if($isRunning && $this->getDaemonUptime($daemonKey))
                                                <div class="text-xs text-green-600 dark:text-green-400 mt-2">
                                                    <i class="icon-clock me-1"></i>{{ __('Uptime') }}: {{ $this->getDaemonUptime($daemonKey) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="ml-6 flex gap-2">
                                            @if($isRunning)
                                                <button 
                                                    wire:click="stopDaemon('{{ $daemonKey }}')"
                                                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-md transition-colors"
                                                >
                                                    <i class="icon-stop me-1"></i>{{ __('Stop') }}
                                                </button>
                                            @else
                                                <button 
                                                    wire:click="startDaemon('{{ $daemonKey }}')"
                                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-md transition-colors"
                                                >
                                                    <i class="icon-play me-1"></i>{{ __('Start') }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Output Panel -->
            <div>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold">{{ __('Activity Log') }}</h2>
                    @if($output)
                        <button 
                            wire:click="clearOutput"
                            class="px-3 py-1 bg-neutral-500 hover:bg-neutral-600 text-white text-sm rounded-md"
                        >
                            {{ __('Clear') }}
                        </button>
                    @endif
                </div>
                
                <div class="bg-neutral-900 dark:bg-neutral-950 rounded-lg p-4 h-96 overflow-y-auto">
                    @if($output)
                        <pre class="text-green-400 text-sm font-mono whitespace-pre-wrap">{{ $output }}</pre>
                    @else
                        <div class="text-neutral-500 text-sm italic">{{ __('No activity yet. Start or stop daemons to see logs here.') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Info Panel -->
        <div class="mt-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-3">
                <i class="icon-info-circle me-2"></i>{{ __('Important Notes') }}
            </h3>
            <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-2">
                <li><strong>{{ __('Background Processes') }}:</strong> {{ __('Daemons run independently and continue after you close this page') }}</li>
                <li><strong>{{ __('System Resources') }}:</strong> {{ __('Monitor server performance when running multiple daemons') }}</li>
                <li><strong>{{ __('Logs') }}:</strong> {{ __('Check Laravel logs for detailed daemon output and errors') }}</li>
                <li><strong>{{ __('Server Restart') }}:</strong> {{ __('Daemons will stop if the server restarts - restart them manually') }}</li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh status every 10 seconds
    setInterval(function() {
        Livewire.dispatch('refresh-status');
    }, 10000);
    
    // Also refresh when window becomes visible again
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            Livewire.dispatch('refresh-status');
        }
    });
});
</script>