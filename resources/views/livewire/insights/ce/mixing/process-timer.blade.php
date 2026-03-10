<?php

use App\Models\InvCeChemical;
use App\Models\InvCeStock;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component {
    public array $mixingData = [];
    public int $durationSeconds = 30;

    public bool $completedLeft = false;
    public bool $completedRight = false;
    public string $completeErrorLeft = '';
    public string $completeErrorRight = '';
    public array $completedStockLeft = [];
    public array $completedStockRight = [];

    public function mount(): void
    {
        $this->mixingData = session('mixing_data', []);
    }

    private function saveHeadStock(array $recipe, array $head, string $side): array
    {
        $outputCode = $recipe['output_code'] ?? null;
        if (!$outputCode) return ['error' => 'No output code configured.'];

        $chemical = InvCeChemical::firstOrCreate(
            ['item_code' => $outputCode],
            [
                'name'              => $outputCode,
                'uom'               => 'kg',
                'is_active'         => true,
                'status_bom'        => '0',
                'category_chemical' => 'double',
            ]
        );

        $weightA     = (float) ($head['chemical_a']['weight_actual'] ?? 0);
        $weightB     = (float) ($head['chemical_b']['weight_actual'] ?? 0);
        $totalWeight = $weightA + $weightB;

        if ($totalWeight <= 0) return ['error' => 'Weight is zero, nothing to save.'];

        $lotNumber = trim($head['chemical_a']['lot_number'] ?? '');
        $potlife   = (float) ($recipe['potlife'] ?? 0);
        $expDate   = $potlife > 0
            ? now()->addHours($potlife)->toDateTimeString()
            : now()->addYear()->toDateTimeString();

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

        return [
            'side'        => $side,
            'output_code' => $outputCode,
            'name'        => $chemical->name,
            'quantity'    => $totalWeight,
            'uom'         => $chemical->uom ?? 'kg',
            'stock_id'    => $stock->id,
        ];
    }

    public function completeMixingLeft(): void
    {
        $this->completeErrorLeft = '';
        if (empty($this->mixingData)) { $this->completeErrorLeft = 'No mixing data found.'; return; }
        if (empty($this->mixingData['recipe_left']) || empty($this->mixingData['left'])) {
            $this->completeErrorLeft = 'No left head data.'; return;
        }
        $result = $this->saveHeadStock($this->mixingData['recipe_left'], $this->mixingData['left'], 'Left');
        if (isset($result['error'])) { $this->completeErrorLeft = $result['error']; return; }
        $this->completedStockLeft = $result;
        $this->completedLeft = true;
        if ($this->completedLeft && $this->completedRight) session()->forget('mixing_data');
    }

    public function completeMixingRight(): void
    {
        $this->completeErrorRight = '';
        if (empty($this->mixingData)) { $this->completeErrorRight = 'No mixing data found.'; return; }
        if (empty($this->mixingData['recipe_right']) || empty($this->mixingData['right'])) {
            $this->completeErrorRight = 'No right head data.'; return;
        }
        $result = $this->saveHeadStock($this->mixingData['recipe_right'], $this->mixingData['right'], 'Right');
        if (isset($result['error'])) { $this->completeErrorRight = $result['error']; return; }
        $this->completedStockRight = $result;
        $this->completedRight = true;
        if ($this->completedLeft && $this->completedRight) session()->forget('mixing_data');
    }
}; ?>

<x-slot name="header">
    <x-nav-insights-ce-mix></x-nav-insights-ce-mix>
</x-slot>

<div
    class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200"
    x-data="{
        duration: {{ $durationSeconds }},

        // LEFT HEAD timer
        leftElapsed: 0,
        leftRunning: false,
        leftFinished: false,
        leftInterval: null,

        // RIGHT HEAD timer
        rightElapsed: 0,
        rightRunning: false,
        rightFinished: false,
        rightInterval: null,

        // Computed LEFT
        get leftRemaining() { return Math.max(0, this.duration - this.leftElapsed); },
        get leftProgress()  { return Math.min(100, (this.leftElapsed / this.duration) * 100); },
        get leftTimeDisplay() {
            const m = Math.floor(this.leftRemaining / 60);
            const s = this.leftRemaining % 60;
            return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
        },

        // Computed RIGHT
        get rightRemaining() { return Math.max(0, this.duration - this.rightElapsed); },
        get rightProgress()  { return Math.min(100, (this.rightElapsed / this.duration) * 100); },
        get rightTimeDisplay() {
            const m = Math.floor(this.rightRemaining / 60);
            const s = this.rightRemaining % 60;
            return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
        },

        startLeft() {
            if (this.leftFinished || this.leftRunning) return;
            this.leftRunning = true;
            this.leftInterval = setInterval(() => {
                if (this.leftElapsed < this.duration) {
                    this.leftElapsed++;
                } else {
                    this.leftFinished = true;
                    this.leftRunning = false;
                    clearInterval(this.leftInterval);
                }
            }, 1000);
        },

        startRight() {
            if (this.rightFinished || this.rightRunning) return;
            this.rightRunning = true;
            this.rightInterval = setInterval(() => {
                if (this.rightElapsed < this.duration) {
                    this.rightElapsed++;
                } else {
                    this.rightFinished = true;
                    this.rightRunning = false;
                    clearInterval(this.rightInterval);
                }
            }, 1000);
        },

        // WebSocket (micon)
        wsUrl: @js(config('rfid.ws_url_node_1')),
        ws: null,
        wsConnected: false,
        wsError: '',
        reconnectAttempt: 0,
        reconnectTimer: null,
        lastRaw: '',

        // Parsed micon states
        miconA: { st: null, ti: null, em: null, mode: null },
        miconB: { st: null, ti: null, em: null, mode: null },
        miconWeight: null,
        miconId: null,

        parseMicon(raw) {
            if (!raw) return;
            this.lastRaw = raw;
            // [ID:2] W: 0.00 | A:[St:1 Ti:0 Em:0 Mode:1] | B:[St:0 Ti:0 Em:0 Mode:0]
            const idM = raw.match(/\[ID:(\d+)\]/);
            if (idM) this.miconId = parseInt(idM[1]);
            const wM = raw.match(/W:\s*([\d.]+)/);
            if (wM) this.miconWeight = parseFloat(wM[1]);

            const aM = raw.match(/A:\[St:(\d+)\s+Ti:(\d+)\s+Em:(\d+)\s+Mode:(\d+)\]/);
            if (aM) this.miconA = { st: parseInt(aM[1]), ti: parseInt(aM[2]), em: parseInt(aM[3]), mode: parseInt(aM[4]) };

            const bM = raw.match(/B:\[St:(\d+)\s+Ti:(\d+)\s+Em:(\d+)\s+Mode:(\d+)\]/);
            if (bM) this.miconB = { st: parseInt(bM[1]), ti: parseInt(bM[2]), em: parseInt(bM[3]), mode: parseInt(bM[4]) };

            this.reactToMicon();
        },

        reactToMicon() {
            // LEFT head driven by A
            if (this.miconA.st !== null) {
                if (this.miconA.em === 1) {
                    // emergency: stop timer
                    this.leftRunning = false;
                    if (this.leftInterval) clearInterval(this.leftInterval);
                } else if (this.miconA.st === 1 && !this.leftRunning && !this.leftFinished) {
                    this.startLeft();
                } else if (this.miconA.ti === 1 && !this.leftFinished) {
                    this.leftFinished = true;
                    this.leftRunning = false;
                    if (this.leftInterval) clearInterval(this.leftInterval);
                }
            }
            // RIGHT head driven by B
            if (this.miconB.st !== null) {
                if (this.miconB.em === 1) {
                    this.rightRunning = false;
                    if (this.rightInterval) clearInterval(this.rightInterval);
                } else if (this.miconB.st === 1 && !this.rightRunning && !this.rightFinished) {
                    this.startRight();
                } else if (this.miconB.ti === 1 && !this.rightFinished) {
                    this.rightFinished = true;
                    this.rightRunning = false;
                    if (this.rightInterval) clearInterval(this.rightInterval);
                }
            }
        },

        wsConnect() {
            if (!this.wsUrl) { this.wsError = 'NODE_1_WS_URL is not configured'; return; }
            try { this.ws = new WebSocket(this.wsUrl); } catch(e) {
                this.wsError = e?.message ?? 'Failed to connect';
                this.scheduleReconnect(); return;
            }
            this.ws.onopen = () => { this.wsConnected = true; this.wsError = ''; this.reconnectAttempt = 0; };
            this.ws.onmessage = (evt) => { this.parseMicon(typeof evt.data === 'string' ? evt.data.trim() : ''); };
            this.ws.onerror = () => { this.wsError = 'WebSocket error'; this.wsConnected = false; };
            this.ws.onclose = (evt) => {
                this.wsConnected = false;
                this.wsError = evt?.reason ? 'Disconnected: ' + evt.reason : 'Disconnected';
                this.scheduleReconnect();
            };
        },

        scheduleReconnect() {
            if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
            const delay = Math.min(10000, 1000 * Math.pow(2, this.reconnectAttempt++));
            this.reconnectTimer = setTimeout(() => this.wsConnect(), delay);
        },
    }"
    x-init="wsConnect()"
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

    <!-- Micon WebSocket Status -->
    <div class="mb-4 flex items-center gap-2 text-sm">
        <span>{{ __('Micon') }}:</span>
        <span :class="wsConnected ? 'text-green-500' : 'text-red-500'" x-text="wsConnected ? 'Connected' : 'Not connected'"></span>
        <span class="text-xs opacity-70">(<span x-text="wsUrl"></span>)</span>
        <span x-show="wsError" class="text-red-500 text-xs" x-text="wsError"></span>
    </div>

    <!-- Emergency Alert -->
    <div x-show="miconA.em === 1 || miconB.em === 1" x-transition class="mb-4 p-4 border rounded bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-200 font-semibold flex items-center gap-2">
        🚨 {{ __('Emergency Stop triggered! Mixing has been halted.') }}
        <span x-show="miconA.em === 1" class="ml-2 text-sm font-normal">({{ __('LEFT HEAD') }})</span>
        <span x-show="miconB.em === 1" class="ml-2 text-sm font-normal">({{ __('RIGHT HEAD') }})</span>
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

            <!-- Emergency alert left -->
            <div x-show="miconA.em === 1" class="mb-3 px-3 py-2 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded text-sm font-semibold">
                🚨 {{ __('Emergency Stop!') }}
            </div>

            <!-- Waiting indicator left -->
            <div x-show="miconA.st === null || (miconA.st === 0 && miconA.ti === 0 && miconA.em === 0)" class="mb-3 flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                {{ __('Waiting for micon signal...') }}
            </div>

            <!-- Progress Bar LEFT -->
            <div class="mb-4">
                <div class="flex justify-between text-xs text-neutral-500 mb-1">
                    <span>{{ __('Progress') }}</span>
                    <span x-text="Math.round(leftProgress) + '%'"></span>
                </div>
                <div class="w-full bg-neutral-200 dark:bg-neutral-700 rounded-full h-4 overflow-hidden">
                    <div
                        class="h-4 rounded-full transition-all duration-1000"
                        :class="leftFinished ? 'bg-green-500' : (miconA.em === 1 ? 'bg-red-500' : 'bg-blue-500')"
                        :style="'width:' + leftProgress + '%'"
                    ></div>
                </div>
                <div class="mt-1 text-xs text-neutral-500 text-right" x-text="leftTimeDisplay + ' remaining'"></div>
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

            <!-- LEFT HEAD: finished notification + save button -->
            <div x-show="leftFinished" x-transition class="mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
                <div class="mb-2 text-sm font-semibold text-green-600 dark:text-green-400">✅ {{ __('Timer complete!') }}</div>
                @if(!$completedLeft)
                @if($completeErrorLeft)
                <div class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $completeErrorLeft }}</div>
                @endif
                <button
                    wire:click="completeMixingLeft"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-caldy-600 hover:bg-caldy-700 text-white font-semibold rounded-md text-sm flex items-center gap-2"
                >
                    <span wire:loading.remove wire:target="completeMixingLeft">{{ __('Save Stock & Complete') }}</span>
                    <span wire:loading wire:target="completeMixingLeft">{{ __('Saving...') }}</span>
                </button>
                @else
                <div class="p-3 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded text-sm">
                    <span class="font-mono font-medium text-green-700 dark:text-green-300">{{ $completedStockLeft['output_code'] ?? '' }}</span>
                    <span class="block text-xs text-neutral-500">{{ $completedStockLeft['name'] ?? '' }} · Stock #{{ $completedStockLeft['stock_id'] ?? '' }}</span>
                    <span class="block text-xs font-semibold">{{ $completedStockLeft['quantity'] ?? '' }} {{ $completedStockLeft['uom'] ?? '' }}</span>
                </div>
                @if($completedLeft && $completedRight)
                <a href="{{ route('insights.ce.mixing.create') }}" wire:navigate
                    class="mt-2 inline-block px-4 py-2 rounded-md bg-caldy-500 hover:bg-caldy-600 text-white font-medium text-sm">
                    {{ __('New Mixing') }}
                </a>
                @endif
                @endif
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

            <!-- Emergency alert right -->
            <div x-show="miconB.em === 1" class="mb-3 px-3 py-2 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded text-sm font-semibold">
                🚨 {{ __('Emergency Stop!') }}
            </div>

            <!-- Waiting indicator right -->
            <div x-show="miconB.st === null || (miconB.st === 0 && miconB.ti === 0 && miconB.em === 0)" class="mb-3 flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                {{ __('Waiting for micon signal...') }}
            </div>

            <!-- Progress Bar RIGHT -->
            <div class="mb-4">
                <div class="flex justify-between text-xs text-neutral-500 mb-1">
                    <span>{{ __('Progress') }}</span>
                    <span x-text="Math.round(rightProgress) + '%'"></span>
                </div>
                <div class="w-full bg-neutral-200 dark:bg-neutral-700 rounded-full h-4 overflow-hidden">
                    <div
                        class="h-4 rounded-full transition-all duration-1000"
                        :class="rightFinished ? 'bg-green-500' : (miconB.em === 1 ? 'bg-red-500' : 'bg-emerald-500')"
                        :style="'width:' + rightProgress + '%'"
                    ></div>
                </div>
                <div class="mt-1 text-xs text-neutral-500 text-right" x-text="rightTimeDisplay + ' remaining'"></div>
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

            <!-- RIGHT HEAD: finished notification + save button -->
            <div x-show="rightFinished" x-transition class="mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
                <div class="mb-2 text-sm font-semibold text-green-600 dark:text-green-400">✅ {{ __('Timer complete!') }}</div>
                @if(!$completedRight)
                @if($completeErrorRight)
                <div class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $completeErrorRight }}</div>
                @endif
                <button
                    wire:click="completeMixingRight"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-caldy-600 hover:bg-caldy-700 text-white font-semibold rounded-md text-sm flex items-center gap-2"
                >
                    <span wire:loading.remove wire:target="completeMixingRight">{{ __('Save Stock & Complete') }}</span>
                    <span wire:loading wire:target="completeMixingRight">{{ __('Saving...') }}</span>
                </button>
                @else
                <div class="p-3 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded text-sm">
                    <span class="font-mono font-medium text-green-700 dark:text-green-300">{{ $completedStockRight['output_code'] ?? '' }}</span>
                    <span class="block text-xs text-neutral-500">{{ $completedStockRight['name'] ?? '' }} · Stock #{{ $completedStockRight['stock_id'] ?? '' }}</span>
                    <span class="block text-xs font-semibold">{{ $completedStockRight['quantity'] ?? '' }} {{ $completedStockRight['uom'] ?? '' }}</span>
                </div>
                @if($completedLeft && $completedRight)
                <a href="{{ route('insights.ce.mixing.create') }}" wire:navigate
                    class="mt-2 inline-block px-4 py-2 rounded-md bg-caldy-500 hover:bg-caldy-600 text-white font-medium text-sm">
                    {{ __('New Mixing') }}
                </a>
                @endif
                @endif
            </div>
        </div>

    </div>
</div>
