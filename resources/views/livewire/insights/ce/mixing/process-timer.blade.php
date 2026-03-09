<?php

use App\Models\InvCeChemical;
use App\Models\InvCeStock;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component {
    public array $mixingData = [];
    public int $durationSeconds = 30;

    public bool $completed = false;
    public string $completeError = '';
    public array $completedStocks = []; // info about created stocks

    public function mount(): void
    {
        $this->mixingData = session('mixing_data', []);
    }

    public function completeMixing(): void
    {
        $this->completeError = '';
        $this->completedStocks = [];

        if (empty($this->mixingData)) {
            $this->completeError = 'No mixing data found.';
            return;
        }

        $heads = [];

        if (!empty($this->mixingData['recipe_left']) && !empty($this->mixingData['left'])) {
            $heads[] = [
                'recipe'  => $this->mixingData['recipe_left'],
                'head'    => $this->mixingData['left'],
                'side'    => 'Left',
            ];
        }

        if (!empty($this->mixingData['recipe_right']) && !empty($this->mixingData['right'])) {
            $heads[] = [
                'recipe'  => $this->mixingData['recipe_right'],
                'head'    => $this->mixingData['right'],
                'side'    => 'Right',
            ];
        }

        if (empty($heads)) {
            $this->completeError = 'No valid head data to process.';
            return;
        }

        foreach ($heads as $entry) {
            $recipe = $entry['recipe'];
            $head   = $entry['head'];
            $side   = $entry['side'];

            $outputCode = $recipe['output_code'] ?? null;
            if (!$outputCode) continue;

            // Find or create the output chemical by item_code
            $chemical = InvCeChemical::firstOrCreate(
                ['item_code' => $outputCode],
                [
                    'name'     => $outputCode,
                    'uom'      => 'kg',
                    'is_active' => true,
                    'status_bom' => '0',
                    'category_chemical' => 'double',
                ]
            );

            $weightA = (float) ($head['chemical_a']['weight_actual'] ?? 0);
            $weightB = (float) ($head['chemical_b']['weight_actual'] ?? 0);
            $totalWeight = $weightA + $weightB;

            if ($totalWeight <= 0) continue;

            // Look for existing open stock for same chemical + lot (if lot given)
            $lotNumber = trim($head['chemical_a']['lot_number'] ?? '');
            // Expiry of the mixed output = now + potlife (in hours)
            $potlife = (float) ($recipe['potlife'] ?? 0);
            $expDate = $potlife > 0
                ? now()->addHours($potlife)->toDateTimeString()
                : now()->addYear()->toDateTimeString();

            // Create a new stock record for this mixing batch
            $stock = InvCeStock::create([
                'inv_ce_chemical_id' => $chemical->id,
                'quantity'           => $totalWeight,
                'unit_size'          => $totalWeight,
                'unit_uom'           => $chemical->uom ?? 'kg',
                'lot_number'         => $lotNumber ?: null,
                'expiry_date'        => $expDate,
                'planning_area'      => json_encode([$recipe['area']] ?? []),
                'status'             => 'approved',
                'remarks'            => "Mixed: {$recipe['chemical_code']} + {$recipe['hardener_code']} | Operator: " . ($this->mixingData['operator'] ?? ''),
            ]);

            $this->completedStocks[] = [
                'side'        => $side,
                'output_code' => $outputCode,
                'name'        => $chemical->name,
                'quantity'    => $totalWeight,
                'uom'         => $chemical->uom ?? 'kg',
                'stock_id'    => $stock->id,
            ];
        }

        if (!empty($this->completedStocks)) {
            $this->completed = true;
            session()->forget('mixing_data');
        } else {
            $this->completeError = 'No output chemical found for the given output code(s), or weight is zero.';
        }
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
                <div><span class="text-neutral-500 uppercase text-xs">{{ __('Started At') }}:</span> <span class="font-semibold">{{ isset($mixingData['started_at']) ? \Carbon\Carbon::parse($mixingData['started_at'])->format('H:i:s') : '-' }}</span></div>
                @if(!empty($mixingData['recipe_left']))
                    <div><span class="text-neutral-500 uppercase text-xs">{{ __('Left Output') }}:</span> <span class="font-semibold font-mono">{{ $mixingData['recipe_left']['output_code'] ?? '-' }}</span></div>
                @endif
                @if(!empty($mixingData['recipe_right']))
                    <div><span class="text-neutral-500 uppercase text-xs">{{ __('Right Output') }}:</span> <span class="font-semibold font-mono">{{ $mixingData['recipe_right']['output_code'] ?? '-' }}</span></div>
                @endif
            @else
                <div class="text-neutral-500 italic">{{ __('No mixing data found.') }}</div>
            @endif
        </div>
    </div>

    <!-- Completed Result -->
    @if($completed)
    <div class="mb-6 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 shadow sm:rounded-lg p-4">
        <div class="font-semibold text-green-700 dark:text-green-300 mb-3">✅ {{ __('Mixing completed! Stock records created:') }}</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($completedStocks as $s)
            <div class="flex items-center justify-between p-3 bg-white dark:bg-neutral-800 rounded-lg border border-green-200 dark:border-green-700 text-sm">
                <div>
                    <span class="font-mono font-medium text-green-700 dark:text-green-300">{{ $s['output_code'] }}</span>
                    <span class="block text-xs text-neutral-500">{{ $s['name'] }}</span>
                    <span class="block text-xs text-neutral-400">{{ $s['side'] }} Head · Stock #{{ $s['stock_id'] }}</span>
                </div>
                <div class="text-right">
                    <span class="font-semibold">{{ $s['quantity'] }}</span>
                    <span class="text-xs text-neutral-500 ml-1">{{ $s['uom'] }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-4 flex gap-3">
            <a href="{{ route('insights.ce.mixing.create') }}" wire:navigate
                class="px-4 py-2 rounded-md bg-caldy-500 hover:bg-caldy-600 text-white font-medium">
                {{ __('New Mixing') }}
            </a>
        </div>
    </div>
    @endif

    @if($completeError)
    <div class="mb-6 p-4 border rounded bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-200">
        {{ $completeError }}
    </div>
    @endif

    <!-- Timer Display -->
    <div class="mb-6 flex flex-col items-center gap-4">
        <!-- Finish notification + Complete button -->
        <div
            x-show="finished"
            x-transition
            class="mt-2 flex flex-col items-center gap-3 w-full"
        >
            <div class="px-4 py-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded-md text-center font-semibold w-full">
                ✅ {{ __('Mixing process complete!') }}
            </div>
            @if(!$completed)
            <button
                wire:click="completeMixing"
                wire:loading.attr="disabled"
                class="px-6 py-2 bg-caldy-600 hover:bg-caldy-700 text-white font-semibold rounded-md flex items-center gap-2"
            >
                <span wire:loading.remove wire:target="completeMixing">{{ __('Save Stock & Complete') }}</span>
                <span wire:loading wire:target="completeMixing">{{ __('Saving...') }}</span>
            </button>
            @endif
        </div>
    </div>

    <!-- Per Head Progress -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- LEFT HEAD -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 border-l-4 border-l-blue-500">
            <div class="text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="px-2 py-1 bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-300 text-xs font-semibold rounded">{{ __('LEFT HEAD') }}</span>
                @if(!empty($mixingData['recipe_left']))
                    <span class="text-xs text-neutral-500 font-mono">→ {{ $mixingData['recipe_left']['output_code'] ?? '' }}</span>
                @endif
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
                @if(!empty($mixingData['recipe_right']))
                    <span class="text-xs text-neutral-500 font-mono">→ {{ $mixingData['recipe_right']['output_code'] ?? '' }}</span>
                @endif
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
