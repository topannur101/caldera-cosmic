<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component {
    public array $mixingData = [];
    public int $durationSeconds = 600; // 10 minutes default

    public function mount(): void
    {
        $this->mixingData = session('mixing_data', []);
    }
}; ?>

<x-slot name="header">
    <x-nav-insights-ce-mix></x-nav-insights-ce-mix>
</x-slot>

<div
    class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200"
    x-data="{
        duration: {{ $durationSeconds }},
        elapsed: 0,
        running: false,
        finished: false,
        interval: null,

        get remaining() { return Math.max(0, this.duration - this.elapsed); },
        get progress() { return Math.min(100, (this.elapsed / this.duration) * 100); },
        get minutes() { return Math.floor(this.remaining / 60); },
        get seconds() { return this.remaining % 60; },
        get timeDisplay() {
            return String(this.minutes).padStart(2,'0') + ':' + String(this.seconds).padStart(2,'0');
        },

        start() {
            if (this.finished) return;
            this.running = true;
            this.interval = setInterval(() => {
                if (this.elapsed < this.duration) {
                    this.elapsed++;
                } else {
                    this.finished = true;
                    this.running = false;
                    clearInterval(this.interval);
                }
            }, 1000);
        },

        pause() {
            this.running = false;
            clearInterval(this.interval);
        },

        reset() {
            this.pause();
            this.elapsed = 0;
            this.finished = false;
        },
    }"
    x-init="start()"
>
    <!-- Summary Info -->
    <div class="mb-6 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
        <div class="flex flex-wrap gap-4 text-sm">
            @if(!empty($mixingData))
                <div><span class="text-neutral-500 uppercase text-xs">{{ __('Operator') }}:</span> <span class="font-semibold">{{ $mixingData['operator'] ?? '-' }}</span></div>
                <div><span class="text-neutral-500 uppercase text-xs">{{ __('Model') }}:</span> <span class="font-semibold">{{ $mixingData['model'] ?? '-' }}</span></div>
                <div><span class="text-neutral-500 uppercase text-xs">{{ __('Recipe') }}:</span> <span class="font-semibold">{{ $mixingData['recipe'] ?? '-' }}</span></div>
                <div><span class="text-neutral-500 uppercase text-xs">{{ __('Area') }}:</span> <span class="font-semibold">{{ $mixingData['area'] ?? '-' }}</span></div>
                <div><span class="text-neutral-500 uppercase text-xs">{{ __('Started At') }}:</span> <span class="font-semibold">{{ isset($mixingData['started_at']) ? \Carbon\Carbon::parse($mixingData['started_at'])->format('H:i:s') : '-' }}</span></div>
            @else
                <div class="text-neutral-500 italic">{{ __('No mixing data found.') }}</div>
            @endif
        </div>
    </div>

    <!-- Timer Display -->
    <div class="mb-6 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6 flex flex-col items-center gap-4">
        <!-- Circular / large timer -->
        <div class="relative flex items-center justify-center" style="width:180px;height:180px;">
            <svg class="absolute" width="180" height="180" viewBox="0 0 180 180">
                <!-- Background circle -->
                <circle cx="90" cy="90" r="80" fill="none" stroke="#e5e7eb" stroke-width="12" class="dark:stroke-neutral-700"/>
                <!-- Progress arc -->
                <circle
                    cx="90" cy="90" r="80"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="12"
                    stroke-linecap="round"
                    class="text-caldy-500"
                    :stroke-dasharray="2 * Math.PI * 80"
                    :stroke-dashoffset="2 * Math.PI * 80 * (1 - progress / 100)"
                    transform="rotate(-90 90 90)"
                    style="transition: stroke-dashoffset 1s linear;"
                    :class="finished ? 'text-green-500' : 'text-caldy-500'"
                />
            </svg>
            <div class="text-center z-10">
                <div class="text-3xl font-mono font-bold" x-text="timeDisplay"></div>
                <div class="text-xs text-neutral-500 mt-1" x-text="finished ? '{{ __('Done!') }}' : (running ? '{{ __('Running') }}' : '{{ __('Paused') }}')"></div>
            </div>
        </div>

        <!-- Controls -->
        <div class="flex gap-3">
            <button
                @click="running ? pause() : start()"
                :disabled="finished"
                class="px-4 py-2 rounded-md text-white font-medium transition"
                :class="finished ? 'bg-gray-400 cursor-not-allowed' : (running ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-caldy-500 hover:bg-caldy-600')"
                x-text="running ? '{{ __('Pause') }}' : '{{ __('Resume') }}'"
            ></button>
            <button
                @click="reset()"
                class="px-4 py-2 rounded-md bg-gray-500 hover:bg-gray-600 text-white font-medium"
            >{{ __('Reset') }}</button>
            <a
                href="{{ route('insights.ce.mixing.create') }}"
                wire:navigate
                class="px-4 py-2 rounded-md bg-neutral-200 dark:bg-neutral-700 hover:bg-neutral-300 dark:hover:bg-neutral-600 text-neutral-800 dark:text-neutral-200 font-medium"
            >{{ __('New Mixing') }}</a>
        </div>

        <!-- Finish notification -->
        <div
            x-show="finished"
            x-transition
            class="mt-2 px-4 py-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded-md text-center font-semibold"
        >
            ✅ {{ __('Mixing process complete!') }}
        </div>
    </div>

    <!-- Per Head Progress -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- LEFT HEAD -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 border-l-4 border-l-blue-500">
            <div class="text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="px-2 py-1 bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-300 text-xs font-semibold rounded">{{ __('LEFT HEAD') }}</span>
            </div>

            <!-- Progress Bar -->
            <div class="mb-4">
                <div class="flex justify-between text-xs text-neutral-500 mb-1">
                    <span>{{ __('Progress') }}</span>
                    <span x-text="Math.round(progress) + '%'"></span>
                </div>
                <div class="w-full bg-neutral-200 dark:bg-neutral-700 rounded-full h-4 overflow-hidden">
                    <div
                        class="h-4 rounded-full transition-all duration-1000"
                        :class="finished ? 'bg-green-500' : 'bg-blue-500'"
                        :style="'width:' + progress + '%'"
                    ></div>
                </div>
                <div class="mt-1 text-xs text-neutral-500 text-right" x-text="timeDisplay + ' remaining'"></div>
            </div>

            @php $left = $mixingData['left'] ?? []; @endphp

            <!-- Chemical A -->
            <div class="mb-4 pb-4 border-b border-neutral-200 dark:border-neutral-700">
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Chemical A') }}</div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-neutral-500">{{ __('Item Code') }}</div>
                    <div class="font-medium">{{ $left['chemical_a']['item_code'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Name') }}</div>
                    <div class="font-medium">{{ $left['chemical_a']['chemical_name'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Lot No.') }}</div>
                    <div class="font-medium">{{ $left['chemical_a']['lot_number'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Exp Date') }}</div>
                    <div class="font-medium">{{ $left['chemical_a']['exp_date'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Weight Target') }}</div>
                    <div class="font-medium">{{ $left['chemical_a']['weight_target'] ?? '-' }} kg</div>
                    <div class="text-neutral-500">{{ __('Weight Actual') }}</div>
                    <div class="font-medium">{{ $left['chemical_a']['weight_actual'] ?? '-' }} kg</div>
                </div>
            </div>

            <!-- Chemical B -->
            <div>
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Chemical B') }}</div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-neutral-500">{{ __('Item Code') }}</div>
                    <div class="font-medium">{{ $left['chemical_b']['item_code'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Name') }}</div>
                    <div class="font-medium">{{ $left['chemical_b']['chemical_name'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Lot No.') }}</div>
                    <div class="font-medium">{{ $left['chemical_b']['lot_number'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Exp Date') }}</div>
                    <div class="font-medium">{{ $left['chemical_b']['exp_date'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Weight Target') }}</div>
                    <div class="font-medium">{{ $left['chemical_b']['weight_target'] ?? '-' }} kg</div>
                    <div class="text-neutral-500">{{ __('Weight Actual') }}</div>
                    <div class="font-medium">{{ $left['chemical_b']['weight_actual'] ?? '-' }} kg</div>
                    <div class="text-neutral-500">{{ __('Percentage') }}</div>
                    <div class="font-medium">{{ $left['percentage'] ?? '-' }} %</div>
                </div>
            </div>
        </div>

        <!-- RIGHT HEAD -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 border-l-4 border-l-green-500">
            <div class="text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="px-2 py-1 bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-300 text-xs font-semibold rounded">{{ __('RIGHT HEAD') }}</span>
            </div>

            <!-- Progress Bar -->
            <div class="mb-4">
                <div class="flex justify-between text-xs text-neutral-500 mb-1">
                    <span>{{ __('Progress') }}</span>
                    <span x-text="Math.round(progress) + '%'"></span>
                </div>
                <div class="w-full bg-neutral-200 dark:bg-neutral-700 rounded-full h-4 overflow-hidden">
                    <div
                        class="h-4 rounded-full transition-all duration-1000"
                        :class="finished ? 'bg-green-500' : 'bg-green-500'"
                        :style="'width:' + progress + '%'"
                    ></div>
                </div>
                <div class="mt-1 text-xs text-neutral-500 text-right" x-text="timeDisplay + ' remaining'"></div>
            </div>

            @php $right = $mixingData['right'] ?? []; @endphp

            <!-- Chemical A -->
            <div class="mb-4 pb-4 border-b border-neutral-200 dark:border-neutral-700">
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Chemical A') }}</div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-neutral-500">{{ __('Item Code') }}</div>
                    <div class="font-medium">{{ $right['chemical_a']['item_code'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Name') }}</div>
                    <div class="font-medium">{{ $right['chemical_a']['chemical_name'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Lot No.') }}</div>
                    <div class="font-medium">{{ $right['chemical_a']['lot_number'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Exp Date') }}</div>
                    <div class="font-medium">{{ $right['chemical_a']['exp_date'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Weight Target') }}</div>
                    <div class="font-medium">{{ $right['chemical_a']['weight_target'] ?? '-' }} kg</div>
                    <div class="text-neutral-500">{{ __('Weight Actual') }}</div>
                    <div class="font-medium">{{ $right['chemical_a']['weight_actual'] ?? '-' }} kg</div>
                </div>
            </div>

            <!-- Chemical B -->
            <div>
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Chemical B') }}</div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-neutral-500">{{ __('Item Code') }}</div>
                    <div class="font-medium">{{ $right['chemical_b']['item_code'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Name') }}</div>
                    <div class="font-medium">{{ $right['chemical_b']['chemical_name'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Lot No.') }}</div>
                    <div class="font-medium">{{ $right['chemical_b']['lot_number'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Exp Date') }}</div>
                    <div class="font-medium">{{ $right['chemical_b']['exp_date'] ?? '-' }}</div>
                    <div class="text-neutral-500">{{ __('Weight Target') }}</div>
                    <div class="font-medium">{{ $right['chemical_b']['weight_target'] ?? '-' }} kg</div>
                    <div class="text-neutral-500">{{ __('Weight Actual') }}</div>
                    <div class="font-medium">{{ $right['chemical_b']['weight_actual'] ?? '-' }} kg</div>
                    <div class="text-neutral-500">{{ __('Percentage') }}</div>
                    <div class="font-medium">{{ $right['percentage'] ?? '-' }} %</div>
                </div>
            </div>
        </div>

    </div>
</div>
