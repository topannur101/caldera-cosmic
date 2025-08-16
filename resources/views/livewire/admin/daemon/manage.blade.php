<?php

use App\Services\PythonCommandService;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {

    public $commands = [];
    public $summary = [];
    public $apiAvailable = false;
    public $loading = false;
    public $selectedLogs = [];
    public $showLogsModal = false;
    public $selectedCommandName = '';

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->loading = true;
        
        try {
            $service = app(PythonCommandService::class);
            
            $this->apiAvailable = $service->isAvailable();
            
            if ($this->apiAvailable) {
                $this->commands = $service->getCommands();
                $this->summary = $service->getSummary();
            } else {
                $this->commands = [];
                $this->summary = [
                    'total' => 0,
                    'running' => 0,
                    'stopped' => 0,
                    'enabled' => 0,
                    'disabled' => 0,
                    'api_available' => false
                ];
            }
        } catch (Exception $e) {
            $this->apiAvailable = false;
            $this->commands = [];
            session()->flash('error', 'Failed to connect to Python Command Manager: ' . $e->getMessage());
        }
        
        $this->loading = false;
    }

    public function startCommand($commandId)
    {
        if (!$this->apiAvailable) {
            session()->flash('error', 'Python Command Manager is not available.');
            return;
        }

        try {
            $service = app(PythonCommandService::class);
            $result = $service->startCommand($commandId);
            
            if ($result['success']) {
                session()->flash('success', $result['message']);
            } else {
                session()->flash('error', $result['message']);
            }
            
            $this->loadData();
        } catch (Exception $e) {
            session()->flash('error', 'Failed to start command: ' . $e->getMessage());
        }
    }

    public function stopCommand($commandId)
    {
        if (!$this->apiAvailable) {
            session()->flash('error', 'Python Command Manager is not available.');
            return;
        }

        try {
            $service = app(PythonCommandService::class);
            $result = $service->stopCommand($commandId);
            
            if ($result['success']) {
                session()->flash('success', $result['message']);
            } else {
                session()->flash('error', $result['message']);
            }
            
            $this->loadData();
        } catch (Exception $e) {
            session()->flash('error', 'Failed to stop command: ' . $e->getMessage());
        }
    }

    public function viewLogs($commandId)
    {
        if (!$this->apiAvailable) {
            session()->flash('error', 'Python Command Manager is not available.');
            return;
        }

        try {
            $service = app(PythonCommandService::class);
            $command = $service->getCommand($commandId);
            
            if ($command) {
                $this->selectedLogs = $service->getCommandLogs($commandId, 200);
                $this->selectedCommandName = $command['name'];
                $this->showLogsModal = true;
            }
        } catch (Exception $e) {
            session()->flash('error', 'Failed to load logs: ' . $e->getMessage());
        }
    }

    public function closeLogsModal()
    {
        $this->showLogsModal = false;
        $this->selectedLogs = [];
        $this->selectedCommandName = '';
    }

    public function startAllEnabled()
    {
        if (!$this->apiAvailable) {
            session()->flash('error', 'Python Command Manager is not available.');
            return;
        }

        try {
            $service = app(PythonCommandService::class);
            $enabledCommands = collect($this->commands)
                ->where('enabled', true)
                ->where('status', 'stopped')
                ->pluck('id')
                ->toArray();

            if (empty($enabledCommands)) {
                session()->flash('info', 'No stopped enabled commands to start.');
                return;
            }

            $results = $service->startMultipleCommands($enabledCommands);
            $successful = collect($results)->where('success', true)->count();
            $total = count($results);

            session()->flash('success', "Started {$successful} of {$total} commands.");
            $this->loadData();
        } catch (Exception $e) {
            session()->flash('error', 'Failed to start commands: ' . $e->getMessage());
        }
    }

    public function stopAllRunning()
    {
        if (!$this->apiAvailable) {
            session()->flash('error', 'Python Command Manager is not available.');
            return;
        }

        try {
            $service = app(PythonCommandService::class);
            $runningCommands = collect($this->commands)
                ->where('status', 'running')
                ->pluck('id')
                ->toArray();

            if (empty($runningCommands)) {
                session()->flash('info', 'No running commands to stop.');
                return;
            }

            $results = $service->stopMultipleCommands($runningCommands);
            $successful = collect($results)->where('success', true)->count();
            $total = count($results);

            session()->flash('success', "Stopped {$successful} of {$total} commands.");
            $this->loadData();
        } catch (Exception $e) {
            session()->flash('error', 'Failed to stop commands: ' . $e->getMessage());
        }
    }

    public function getStatusBadgeClass($status)
    {
        return match($status) {
            'running' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'stopped' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
        };
    }

    public function getStatusIcon($status)
    {
        return match($status) {
            'running' => 'icon-play-circle',
            'stopped' => 'icon-stop-circle',
            default => 'icon-help-circle'
        };
    }
}; ?>

<x-slot name="title">{{ __('Daemon') . ' — ' . __('Admin') }}</x-slot>

<x-slot name="header">
    <x-nav-admin>{{ __('Daemon') }}</x-nav-admin>
</x-slot>

<div class="py-12 text-neutral-800 dark:text-neutral-200" wire:poll.5s="loadData">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                        {{ __('Daemon Management') }}
                    </h1>
                    <p class="mt-2 text-neutral-600 dark:text-neutral-400">
                        {{ __('Manage Laravel artisan daemon processes') }}
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    @if($loading)
                        <x-spinner class="text-caldy-500" />
                    @endif
                    <button wire:click="loadData" 
                            class="bg-caldy-500 hover:bg-caldy-600 text-white px-4 py-2 rounded-lg">
                        <i class="icon-refresh mr-2"></i>{{ __('Refresh') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session()->has('success'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg dark:bg-green-900 dark:border-green-700 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900 dark:border-red-700 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if (session()->has('info'))
            <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg dark:bg-blue-900 dark:border-blue-700 dark:text-blue-200">
                {{ session('info') }}
            </div>
        @endif

        <!-- API Status Alert -->
        @if(!$apiAvailable)
            <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg dark:bg-yellow-900 dark:border-yellow-700 dark:text-yellow-200">
                <div class="flex items-center">
                    <i class="icon-alert-triangle mr-2"></i>
                    <div>
                        <strong>{{ __('Python Command Manager Unavailable') }}</strong>
                        <p class="text-sm">{{ __('Please ensure the Python command manager is running on port 8765.') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="icon-server text-2xl text-neutral-400"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Total Commands') }}</dt>
                                <dd class="text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ $summary['total'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="icon-play-circle text-2xl text-green-400"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Running') }}</dt>
                                <dd class="text-lg font-medium text-green-600 dark:text-green-400">{{ $summary['running'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="icon-stop-circle text-2xl text-red-400"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Stopped') }}</dt>
                                <dd class="text-lg font-medium text-red-600 dark:text-red-400">{{ $summary['stopped'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="icon-check-circle text-2xl text-blue-400"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Enabled') }}</dt>
                                <dd class="text-lg font-medium text-blue-600 dark:text-blue-400">{{ $summary['enabled'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        @if($apiAvailable && !empty($commands))
            <div class="mb-6 bg-white dark:bg-neutral-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-4">{{ __('Bulk Actions') }}</h3>
                <div class="flex space-x-4">
                    <button wire:click="startAllEnabled" 
                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                        <i class="icon-play mr-2"></i>{{ __('Start All Enabled') }}
                    </button>
                    <button wire:click="stopAllRunning" 
                            class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">
                        <i class="icon-stop mr-2"></i>{{ __('Stop All Running') }}
                    </button>
                </div>
            </div>
        @endif

        <!-- Commands Table -->
        <div class="bg-white dark:bg-neutral-800 shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Commands') }}
                </h3>
                <p class="mt-1 max-w-2xl text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('Laravel artisan daemon processes and their current status') }}
                </p>
            </div>
            
            @if(!empty($commands))
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                                    {{ __('Command') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                                    {{ __('Status') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                                    {{ __('Process') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                                    {{ __('Started At') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach($commands as $command)
                                <tr class="{{ !$command['enabled'] ? 'opacity-50' : '' }}">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-neutral-200 dark:bg-neutral-600 flex items-center justify-center">
                                                    <i class="{{ $this->getStatusIcon($command['status']) }} text-lg"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                                    {{ $command['name'] }}
                                                    @if(!$command['enabled'])
                                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                            {{ __('Disabled') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="text-sm text-neutral-500 dark:text-neutral-400">
                                                    {{ $command['command'] }}
                                                </div>
                                                @if(!empty($command['description']))
                                                    <div class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                                                        {{ $command['description'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getStatusBadgeClass($command['status']) }}">
                                            {{ ucfirst($command['status']) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-400">
                                        @if($command['pid'])
                                            PID: {{ $command['pid'] }}
                                        @else
                                            {{ __('N/A') }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500 dark:text-neutral-400">
                                        @if($command['started_at'])
                                            {{ \Carbon\Carbon::parse($command['started_at'])->format('M j, Y H:i:s') }}
                                        @else
                                            {{ __('N/A') }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            @if($command['status'] === 'stopped' && $command['enabled'])
                                                <button wire:click="startCommand('{{ $command['id'] }}')" 
                                                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                                    <i class="icon-play mr-1"></i>{{ __('Start') }}
                                                </button>
                                            @elseif($command['status'] === 'running')
                                                <button wire:click="stopCommand('{{ $command['id'] }}')" 
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    <i class="icon-stop mr-1"></i>{{ __('Stop') }}
                                                </button>
                                            @endif
                                            
                                            <button wire:click="viewLogs('{{ $command['id'] }}')" 
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                <i class="icon-file-text mr-1"></i>{{ __('Logs') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-8 text-center">
                    <i class="icon-server mx-auto h-12 w-12 text-neutral-400"></i>
                    <h3 class="mt-2 text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('No commands configured') }}</h3>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        @if($apiAvailable)
                            {{ __('Configure commands in the Python Command Manager application.') }}
                        @else
                            {{ __('Python Command Manager is not available.') }}
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>
    <!-- Logs Modal -->
    @if($showLogsModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" 
            x-data="{ show: @entangle('showLogsModal') }"
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">
            
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-neutral-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="bg-white dark:bg-neutral-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-neutral-900 dark:text-neutral-100">
                                {{ __('Command Logs') }} - {{ $selectedCommandName }}
                            </h3>
                            <button wire:click="closeLogsModal" 
                                    class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
                                <i class="icon-x text-xl"></i>
                            </button>
                        </div>
                        
                        <div class="bg-neutral-900 text-green-400 font-mono text-sm p-4 rounded-lg max-h-96 overflow-y-auto">
                            @if(!empty($selectedLogs))
                                @foreach($selectedLogs as $log)
                                    <div>{{ $log }}</div>
                                @endforeach
                            @else
                                <div class="text-neutral-400">{{ __('No logs available') }}</div>
                            @endif
                        </div>
                    </div>
                    
                    <div class="bg-neutral-50 dark:bg-neutral-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button wire:click="closeLogsModal" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-caldy-600 text-base font-medium text-white hover:bg-caldy-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-caldy-500 sm:ml-3 sm:w-auto sm:text-sm">
                            {{ __('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
