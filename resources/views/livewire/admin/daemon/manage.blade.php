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
            session()->flash('error', 'Gagal menghubungkan ke Pengelola Daemon Caldera: ' . $e->getMessage());
        }
        
        $this->loading = false;
    }

    public function startCommand($commandId)
    {
        if (!$this->apiAvailable) {
            session()->flash('error', 'Pengelola Daemon Caldera tidak tersedia.');
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
            session()->flash('error', 'Gagal memulai perintah: ' . $e->getMessage());
        }
    }

    public function stopCommand($commandId)
    {
        if (!$this->apiAvailable) {
            session()->flash('error', 'Pengelola Daemon Caldera tidak tersedia.');
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
            session()->flash('error', 'Gagal menghentikan perintah: ' . $e->getMessage());
        }
    }

    public function viewLogs($commandId)
    {
        if (!$this->apiAvailable) {
            session()->flash('error', 'Pengelola Daemon Caldera tidak tersedia.');
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
            session()->flash('error', 'Gagal memuat log: ' . $e->getMessage());
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
            session()->flash('error', 'Pengelola Daemon Caldera tidak tersedia.');
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
                $this->js('toast("' . __('Tidak ada perintah aktif yang terhenti untuk dimulai.') . '", { type: "info" })');
                return;
            }

            $results = $service->startMultipleCommands($enabledCommands);
            $successful = collect($results)->where('success', true)->count();
            $total = count($results);

            $this->js('toast("' . __("Berhasil memulai {$successful} dari {$total} perintah.") . '", { type: "success" })');
            $this->loadData();
        } catch (Exception $e) {
            $this->js('toast("' . __('Gagal memulai perintah: ' . $e->getMessage()) . '", { type: "danger" })');
        }
    }

    public function stopAllRunning()
    {
        if (!$this->apiAvailable) {
            session()->flash('error', 'Pengelola Daemon Caldera tidak tersedia.');
            return;
        }

        try {
            $service = app(PythonCommandService::class);
            $runningCommands = collect($this->commands)
                ->where('status', 'running')
                ->pluck('id')
                ->toArray();

            if (empty($runningCommands)) {
                $this->js('toast("' . __('Tidak ada perintah yang berjalan untuk dihentikan.') . '", { type: "info" })');
                return;
            }

            $results = $service->stopMultipleCommands($runningCommands);
            $successful = collect($results)->where('success', true)->count();
            $total = count($results);

            $this->js('toast("' . __("Berhasil menghentikan {$successful} dari {$total} perintah.") . '", { type: "success" })');
            $this->loadData();
        } catch (Exception $e) {
            $this->js('toast("' . __('Gagal menghentikan perintah: ' . $e->getMessage()) . '", { type: "danger" })');
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

<x-slot name="title">{{ __('Kelola daemon') . ' — ' . __('Admin') }}</x-slot>

<x-slot name="header">
    <x-nav-admin>{{ __('Kelola daemon') }}</x-nav-admin>
</x-slot>

<div class="py-12 text-neutral-800 dark:text-neutral-200" wire:poll="loadData">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Action Buttons -->
        <div class="mb-6 flex items-center justify-end space-x-4">
            @if($loading)
                <x-spinner class="text-caldy-500" />
            @endif
            
            <!-- Bulk Actions -->
            @if($apiAvailable && !empty($commands))
                <div class="btn-group">
                    <x-secondary-button type="button" wire:click="startAllEnabled">
                        <i class="icon-play mr-2 text-green-600"></i>
                        <span class="text-green-600">{{ __('Mulai Semua') }}</span>
                    </x-secondary-button>
                    <x-secondary-button type="button" wire:click="stopAllRunning" class="rounded-none">
                        <i class="icon-square mr-2 text-red-600"></i>
                        <span class="text-red-600">{{ __('Hentikan Semua') }}</span>
                    </x-secondary-button>
                </div>
            @endif
            
            <!-- Refresh Button -->
            <x-secondary-button type="button" wire:click="loadData">
                <i class="icon-rotate-cw"></i>
            </x-secondary-button>
        </div>


        <!-- API Status Alert -->
        @if(!$apiAvailable)
            <div class="relative text-neutral h-32 sm:rounded-lg overflow-hidden mb-8 border border-dashed border-neutral-300 dark:border-neutral-500">
                <div class="absolute top-0 left-0 flex h-full items-center px-4 lg:px-8 text-neutral-500">
                    <div>
                        <div class="uppercase font-bold mb-2"><i class="icon-triangle-alert me-2"></i>{{ __('Daemon tidak tersedia') }}</div>
                        <div>{{ __('Pastikan aplikasi Pengelola Daemon Caldera berjalan di port 8765.') }}</div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="icon-server text-2xl text-neutral-400"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Total Perintah') }}</dt>
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
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Berjalan') }}</dt>
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
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Terhenti') }}</dt>
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
                                <dt class="text-sm font-medium text-neutral-500 truncate">{{ __('Aktif') }}</dt>
                                <dd class="text-lg font-medium text-blue-600 dark:text-blue-400">{{ $summary['enabled'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Commands Table -->
        <div class="overflow-auto w-full">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    @if(!empty($commands))
                        <table class="table">
                            <tr>
                                <th>{{ __('Perintah') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Proses') }}</th>
                                <th>{{ __('Dimulai Pada') }}</th>
                                <th>{{ __('Aksi') }}</th>
                            </tr>
                            @foreach($commands as $command)
                                <tr class="{{ !$command['enabled'] ? 'opacity-50' : '' }}">
                                    <td>
                                        <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                            {{ $command['name'] }}
                                            @if(!$command['enabled'])
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                    {{ __('Nonaktif') }}
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
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getStatusBadgeClass($command['status']) }}">
                                            {{ ucfirst($command['status']) }}
                                        </span>
                                    </td>
                                    <td class="text-sm text-neutral-500 dark:text-neutral-400">
                                        @if($command['pid'])
                                            PID: {{ $command['pid'] }}
                                        @else
                                            {{ __('T/A') }}
                                        @endif
                                    </td>
                                    <td class="text-sm text-neutral-500 dark:text-neutral-400">
                                        @if($command['started_at'])
                                            {{ \Carbon\Carbon::parse($command['started_at'])->format('M j, Y H:i:s') }}
                                        @else
                                            {{ __('T/A') }}
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            @if($command['status'] === 'stopped' && $command['enabled'])
                                                <x-secondary-button type="button" wire:click="startCommand('{{ $command['id'] }}')">
                                                    <i class="icon-play text-green-600"></i>
                                                </x-secondary-button>
                                            @elseif($command['status'] === 'running')
                                                <x-secondary-button type="button" wire:click="stopCommand('{{ $command['id'] }}')" class="rounded-none">
                                                    <i class="icon-square text-red-600"></i>
                                                </x-secondary-button>
                                            @endif
                                            
                                            <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'daemon-logs'); $wire.viewLogs('{{ $command['id'] }}')">
                                                <i class="icon-square-terminal text-blue-600"></i>
                                            </x-secondary-button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                        
                        @if (!count($commands))
                            <div class="text-center py-12">
                                {{ __('Tidak ada perintah yang dikonfigurasi') }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-12">
                            <i class="icon-server mx-auto h-12 w-12 text-neutral-400"></i>
                            <h3 class="mt-2 text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Tidak ada perintah yang dikonfigurasi') }}</h3>
                            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                                @if($apiAvailable)
                                    {{ __('Konfigurasi perintah di aplikasi Pengelola Daemon Caldera.') }}
                                @else
                                    {{ __('Pengelola Daemon Caldera tidak tersedia.') }}
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- Logs Modal -->
    <x-modal name="daemon-logs" max-width="4xl">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ $selectedCommandName }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')">
                    <i class="icon-x"></i>
                </x-text-button>
            </div>
            
            <div class="mt-6">
                <div class="bg-neutral-900 text-green-400 font-mono text-sm p-4 rounded-lg max-h-96 overflow-y-auto">
                    @if(!empty($selectedLogs))
                        @foreach($selectedLogs as $log)
                            <div>{{ $log }}</div>
                        @endforeach
                    @else
                        <div class="text-neutral-400">{{ __('Tidak ada log tersedia') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </x-modal>
</div>
