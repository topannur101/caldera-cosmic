<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component {
    
    public function mount()
    {
        // TODO: Load user's tasks, team assignments, etc.
    }
    
    public function with(): array
    {
        return [
            // TODO: Return dashboard data
            'my_tasks' => [],
            'team_stats' => [],
            'recent_activity' => []
        ];
    }
};

?>

<x-slot name="title">{{ __('Dasbor Tugas') }}</x-slot>

@auth
    <x-slot name="header">
        <x-nav-task></x-nav-task>
    </x-slot>
@endauth

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-neutral-800 overflow-hidden shadow sm:rounded-lg">
            <div class="p-6 text-neutral-900 dark:text-neutral-100">
                
                <!-- Dasbor Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold mb-2">{{ __('Dasbor Tugas') }}</h1>
                    <p class="text-neutral-600 dark:text-neutral-400">{{ __('Ringkasan tugas dan aktivitas tim Anda') }}</p>
                </div>

                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-6 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">0</div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tugas Aktif') }}</div>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-6 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">0</div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tugas Tertunda') }}</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 p-6 rounded-lg">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">0</div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tugas Selesai') }}</div>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 p-6 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">0</div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Proyek Aktif') }}</div>
                    </div>
                </div>

                <!-- Placeholder Content -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- My Tasks -->
                    <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4">{{ __('Tugas Saya') }}</h3>
                        <div class="text-center py-12 text-neutral-500 dark:text-neutral-400">
                            <i class="icon-ticket text-4xl mb-4"></i>
                            <p>{{ __('Belum ada tugas yang ditugaskan') }}</p>
                            <p class="text-sm mt-2">{{ __('Tugas yang ditugaskan kepada Anda akan muncul di sini') }}</p>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4">{{ __('Aktivitas Terbaru') }}</h3>
                        <div class="text-center py-12 text-neutral-500 dark:text-neutral-400">
                            <i class="icon-clock text-4xl mb-4"></i>
                            <p>{{ __('Belum ada aktivitas') }}</p>
                            <p class="text-sm mt-2">{{ __('Aktivitas tim akan muncul di sini') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mt-8 flex flex-wrap gap-4">
                    <x-primary-button x-on:click.prevent="$dispatch('open-slide-over', 'task-create'); $dispatch('task-create')">
                        <i class="icon-plus mr-2"></i>{{ __('Buat Tugas Baru') }}
                    </x-primary-button>
                    <x-secondary-button onclick="window.location.href='{{ route('tasks.projects.create') }}'" wire:navigate>
                        <i class="icon-folder-plus mr-2"></i>{{ __('Buat Proyek Baru') }}
                    </x-secondary-button>
                    <x-secondary-button onclick="window.location.href='{{ route('tasks.board.index') }}'" wire:navigate>
                        <i class="icon-layout-kanban mr-2"></i>{{ __('Lihat Board') }}
                    </x-secondary-button>
                </div>

                <!-- Management Access -->
                <div class="mt-8 pt-6 border-t border-neutral-200 dark:border-neutral-700">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Administrasi') }}</h3>
                    <x-secondary-button onclick="window.location.href='{{ route('tasks.manage.index') }}'" wire:navigate>
                        <i class="icon-cog mr-2"></i>{{ __('Kelola') }}
                    </x-secondary-button>
                </div>

            </div>
        </div>
    </div>
    <div wire:key="slideovers">
        <!-- Task Creation Slideover -->
        <x-slide-over name="task-create">
            <livewire:tasks.items.create />
        </x-slide-over>
    </div>
</div>

