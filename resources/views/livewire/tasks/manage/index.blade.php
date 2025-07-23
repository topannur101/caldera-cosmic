<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\TskTeam;
use App\Models\TskProject;
use App\Models\TskItem;
use App\Models\TskType;
use App\Models\User;

new #[Layout('layouts.app')]
class extends Component
{
    public function with(): array
    {
        return [
            'stats' => $this->getManagementStats()
        ];
    }

    private function getManagementStats(): array
    {
        return [
            'teams' => [
                'total' => TskTeam::count(),
                'active' => TskTeam::where('is_active', true)->count(),
            ],
            'projects' => [
                'total' => TskProject::count(),
                'active' => TskProject::where('status', 'active')->count(),
            ],
            'tasks' => [
                'total' => TskItem::count(),
                'in_progress' => TskItem::whereIn('status', ['todo', 'in_progress', 'review'])->count(),
            ],
            'types' => [
                'total' => TskType::count(),
                'active' => TskType::where('is_active', true)->count(),
            ],
            'users' => [
                'total' => User::whereHas('tsk_auths')->distinct('id')->count(),
            ]
        ];
    }
}; ?>

<div>
    <x-nav-task />
    
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Kelola Sistem Tugas</h1>
                <p class="mt-2 text-gray-600">
                    Kelola tim, proyek, otorisasi, dan konfigurasi sistem tugas.
                </p>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Teams Stats -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Tim</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $stats['teams']['active'] }}/{{ $stats['teams']['total'] }}
                                        <span class="text-sm text-gray-500">aktif</span>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Projects Stats -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Proyek</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $stats['projects']['active'] }}/{{ $stats['projects']['total'] }}
                                        <span class="text-sm text-gray-500">aktif</span>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasks Stats -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Tugas</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $stats['tasks']['in_progress'] }}/{{ $stats['tasks']['total'] }}
                                        <span class="text-sm text-gray-500">aktif</span>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Types Stats -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a1.414 1.414 0 01-1.414.586H7a4 4 0 01-4-4V7a4 4 0 014-4z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Tipe Tugas</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $stats['types']['active'] }}/{{ $stats['types']['total'] }}
                                        <span class="text-sm text-gray-500">aktif</span>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Management Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Teams Management -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Kelola Tim</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Buat dan kelola tim kerja, atur anggota dan pengaturan tim.
                                </p>
                                <div class="mt-3">
                                    <div class="text-sm text-gray-600">
                                        <span class="font-medium">{{ $stats['teams']['total'] }}</span> tim tersedia,
                                        <span class="font-medium">{{ $stats['users']['total'] }}</span> pengguna terdaftar
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-5">
                            <a href="{{ route('tasks.manage.teams') }}"
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Kelola Tim
                                <svg class="ml-2 -mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Authorization Management -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Kelola Otorisasi</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Atur hak akses dan izin pengguna untuk berbagai fungsi sistem.
                                </p>
                                <div class="mt-3">
                                    <div class="text-sm text-gray-600">
                                        Izin tersedia: <span class="font-medium">task-create</span>, 
                                        <span class="font-medium">task-assign</span>, 
                                        <span class="font-medium">task-manage</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-5">
                            <a href="{{ route('tasks.manage.auths') }}"
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Kelola Otorisasi
                                <svg class="ml-2 -mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Types Management -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Kelola Tipe</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Kelola tipe-tipe tugas yang dapat digunakan dalam sistem.
                                </p>
                                <div class="mt-3">
                                    <div class="text-sm text-gray-600">
                                        <span class="font-medium">{{ $stats['types']['total'] }}</span> tipe tersedia,
                                        <span class="font-medium">{{ $stats['types']['active'] }}</span> aktif
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a1.414 1.414 0 01-1.414.586H7a4 4 0 01-4-4V7a4 4 0 014-4z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-5">
                            <a href="{{ route('tasks.manage.types') }}"
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-purple-700 bg-purple-100 hover:bg-purple-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                Kelola Tipe
                                <svg class="ml-2 -mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Informasi Sistem</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Lihat statistik dan informasi umum sistem tugas.
                                </p>
                                <div class="mt-3 space-y-1">
                                    <div class="text-sm text-gray-600">
                                        Total proyek: <span class="font-medium">{{ $stats['projects']['total'] }}</span>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        Total tugas: <span class="font-medium">{{ $stats['tasks']['total'] }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-5">
                            <div class="text-sm text-gray-500">
                                Sistem tugas beroperasi normal
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-8">
                <div class="bg-gray-50 rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Aksi Cepat</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="{{ route('tasks.manage.teams') }}"
                           class="flex items-center p-3 bg-white rounded-md shadow-sm hover:shadow-md transition-shadow">
                            <svg class="h-5 w-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">Buat Tim Baru</span>
                        </a>

                        <a href="{{ route('tasks.manage.types') }}"
                           class="flex items-center p-3 bg-white rounded-md shadow-sm hover:shadow-md transition-shadow">
                            <svg class="h-5 w-5 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">Tambah Tipe Tugas</span>
                        </a>

                        <a href="{{ route('tasks.manage.auths') }}"
                           class="flex items-center p-3 bg-white rounded-md shadow-sm hover:shadow-md transition-shadow">
                            <svg class="h-5 w-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">Atur Izin Pengguna</span>
                        </a>

                        <a href="{{ route('tasks.projects.index') }}"
                           class="flex items-center p-3 bg-white rounded-md shadow-sm hover:shadow-md transition-shadow">
                            <svg class="h-5 w-5 text-gray-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900">Lihat Proyek</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>