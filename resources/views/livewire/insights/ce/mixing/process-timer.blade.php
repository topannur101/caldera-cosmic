<?php

use App\Models\InvCeChemical;
use App\Models\InvCeMixingLog;
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

    public bool $failedLeft = false;
    public bool $failedRight = false;

    public function mount(): void
    {
        $this->mixingData = session('mixing_data', []);
    }

    private function createMixingLog(array $recipe, string $status, string $notes = ''): void
    {
        try {
            InvCeMixingLog::create([
                'recipe_id'    => $recipe['id'] ?? 0,
                'user_id'      => auth()->id() ?? 0,
                'batch_number' => 'MIX-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                'duration'     => gmdate('H:i:s', $this->durationSeconds),
                'notes'        => $notes,
                'status'       => $status,
            ]);
        } catch (\Exception $e) {
            // fail silently — log creation should not block the main flow
        }
    }

    private function decreaseInputStock(string $itemCode, string $lotNumber, float $weightUsed): array
    {
        if ($itemCode === '' || $weightUsed <= 0) {
            return [];
        }

        $stock = InvCeStock::query()
            ->join('inv_ce_chemicals', 'inv_ce_stock.inv_ce_chemical_id', '=', 'inv_ce_chemicals.id')
            ->where('inv_ce_chemicals.item_code', $itemCode)
            ->when($lotNumber !== '', fn($q) => $q->where('inv_ce_stock.lot_number', $lotNumber))
            ->orderBy('inv_ce_stock.expiry_date')
            ->select('inv_ce_stock.*')
            ->first();

        if (!$stock) {
            return ['warning' => "Stock not found for item_code={$itemCode} lot={$lotNumber}"];
        }

        $before = (float) $stock->quantity;
        $after  = max(0, $before - $weightUsed);
        $stock->quantity = $after;
        if ($after <= 0) {
            $stock->status = 'empty';
        }
        $stock->save();

        return [
            'item_code'  => $itemCode,
            'lot_number' => $lotNumber,
            'before'     => $before,
            'used'       => $weightUsed,
            'after'      => $after,
        ];
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

        // Decrease input chemical stocks
        $decreaseA = $this->decreaseInputStock(
            $head['chemical_a']['item_code'] ?? '',
            $head['chemical_a']['lot_number'] ?? '',
            $weightA
        );
        $decreaseB = $this->decreaseInputStock(
            $head['chemical_b']['item_code'] ?? '',
            $head['chemical_b']['lot_number'] ?? '',
            $weightB
        );

        $lotNumber = trim($head['chemical_a']['lot_number'] ?? '');
        $potlife   = (float) ($recipe['potlife'] ?? 0);
        $expDate   = $potlife > 0
            ? now()->addHours($potlife)->toDateTimeString()
            : now()->addYear()->toDateTimeString();

        // Create output stock (increase)
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
            'decrease_a'  => $decreaseA,
            'decrease_b'  => $decreaseB,
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
        $this->createMixingLog($this->mixingData['recipe_left'], 'completed',
            'Left head completed. Output stock #' . ($result['stock_id'] ?? ''));
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
        $this->createMixingLog($this->mixingData['recipe_right'], 'completed',
            'Right head completed. Output stock #' . ($result['stock_id'] ?? ''));
        if ($this->completedLeft && $this->completedRight) session()->forget('mixing_data');
    }

    public function failMixingLeft(): void
    {
        if ($this->failedLeft || $this->completedLeft) return;
        $this->failedLeft = true;
        if (!empty($this->mixingData['recipe_left'])) {
            $this->createMixingLog($this->mixingData['recipe_left'], 'failed', 'Left head — Emergency Stop triggered.');
        }
    }

    public function failMixingRight(): void
    {
        if ($this->failedRight || $this->completedRight) return;
        $this->failedRight = true;
        if (!empty($this->mixingData['recipe_right'])) {
            $this->createMixingLog($this->mixingData['recipe_right'], 'failed', 'Right head — Emergency Stop triggered.');
        }
    }

    public function resetFailLeft(): void
    {
        $this->failedLeft = false;
    }

    public function resetFailRight(): void
    {
        $this->failedRight = false;
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
        emAFailed: false,

        // RIGHT HEAD timer
        rightElapsed: 0,
        rightRunning: false,
        rightFinished: false,
        rightInterval: null,
        emBFailed: false,

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
                    // emergency: stop, reset progress to 0, log as failed (once)
                    this.leftRunning = false;
                    this.leftFinished = false;
                    this.leftElapsed = 0;
                    if (this.leftInterval) { clearInterval(this.leftInterval); this.leftInterval = null; }
                    if (!this.emAFailed) {
                        this.emAFailed = true;
                        $wire.failMixingLeft();
                    }
                } else if (this.miconA.st === 1 && !this.leftRunning && !this.leftFinished) {
                    // new start signal — reset emergency flag and server failed state
                    if (this.emAFailed) {
                        this.emAFailed = false;
                        $wire.call('resetFailLeft');
                    }
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
                    // emergency: stop, reset progress to 0, log as failed (once)
                    this.rightRunning = false;
                    this.rightFinished = false;
                    this.rightElapsed = 0;
                    if (this.rightInterval) { clearInterval(this.rightInterval); this.rightInterval = null; }
                    if (!this.emBFailed) {
                        this.emBFailed = true;
                        $wire.failMixingRight();
                    }
                } else if (this.miconB.st === 1 && !this.rightRunning && !this.rightFinished) {
                    if (this.emBFailed) {
                        this.emBFailed = false;
                        $wire.call('resetFailRight');
                    }
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
    @php
        $left  = $mixingData['left']  ?? [];
        $right = $mixingData['right'] ?? [];
    @endphp
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- ═══════════════════════ LEFT HEAD ═══════════════════════ --}}
        <div
            x-data="{ open: false }"
            class="bg-white dark:bg-neutral-800 shadow sm:rounded-xl overflow-hidden"
            :class="miconA.em === 1 ? 'ring-2 ring-red-500' : ''"
        >
            {{-- Header banner --}}
            <div class="flex items-center gap-3 px-5 py-3"
                 :class="leftFinished ? 'bg-green-500' : (miconA.em === 1 ? 'bg-red-500' : 'bg-blue-500')">
                <span class="text-white font-bold text-sm tracking-widest uppercase">{{ __('Left') }}</span>
                @if(!empty($mixingData['recipe_left']))
                    <span class="ml-1 text-white/80 font-mono text-xs">→ {{ $mixingData['recipe_left']['output_code'] ?? '' }}</span>
                @endif
                <span class="ml-auto text-white/70 text-xs" x-show="miconA.em === 1">🚨 {{ __('Emergency Stop') }}</span>
                <span class="ml-auto text-white/70 text-xs" x-show="miconA.st === null || (miconA.st === 0 && miconA.ti === 0 && miconA.em === 0)">
                    <svg class="inline animate-spin h-3 w-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    {{ __('Waiting...') }}
                </span>
                <span class="ml-auto text-white font-bold text-xs" x-show="leftFinished && !{{ $completedLeft ? 'false' : 'false' }}">✓ {{ __('Done') }}</span>
            </div>

            {{-- Progress + timer --}}
            <div class="px-5 pt-4 pb-2">
                <div class="flex items-end justify-between mb-1">
                    <span class="text-3xl font-bold tabular-nums" x-text="leftTimeDisplay"
                          :class="leftFinished ? 'text-green-500' : (miconA.em === 1 ? 'text-red-500' : 'text-blue-500')"></span>
                    <span class="text-sm text-neutral-400 mb-1" x-text="Math.round(leftProgress) + '%'"></span>
                </div>
                <div class="w-full bg-neutral-100 dark:bg-neutral-700 rounded-full h-2 overflow-hidden">
                    <div class="h-2 rounded-full transition-all duration-1000"
                         :class="leftFinished ? 'bg-green-500' : (miconA.em === 1 ? 'bg-red-500' : 'bg-blue-500')"
                         :style="'width:' + leftProgress + '%'"></div>
                </div>
            </div>

            {{-- Chemical summary row (always visible) + click to expand --}}
            <button type="button" @click="open = !open"
                    class="w-full flex items-center justify-between px-5 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition-colors text-left">
                <div class="flex gap-4 text-sm">
                    <div>
                        <div class="text-xs text-neutral-400 uppercase tracking-wide">A</div>
                        <div class="font-semibold text-neutral-700 dark:text-neutral-200 truncate max-w-[120px]">{{ $left['chemical_a']['chemical_name'] ?? '-' }}</div>
                        <div class="text-xs text-neutral-500">{{ $left['chemical_a']['weight_actual'] ?? '-' }} kg</div>
                    </div>
                    <div class="self-center text-neutral-300 dark:text-neutral-600 font-bold">+</div>
                    <div>
                        <div class="text-xs text-neutral-400 uppercase tracking-wide">B</div>
                        <div class="font-semibold text-neutral-700 dark:text-neutral-200 truncate max-w-[120px]">{{ $left['chemical_b']['chemical_name'] ?? '-' }}</div>
                        <div class="text-xs text-neutral-500">{{ $left['chemical_b']['weight_actual'] ?? '-' }} kg · {{ $left['percentage'] ?? '-' }}%</div>
                    </div>
                </div>
                <i class="icon-chevron-down text-neutral-400 transition-transform" :class="open && 'rotate-180'"></i>
            </button>

            {{-- Expandable detail --}}
            <div x-show="open" x-collapse class="border-t border-neutral-100 dark:border-neutral-700 px-5 py-4 space-y-4 text-sm">
                {{-- Chemical A --}}
                <div>
                    <div class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-2">{{ __('Chemical A') }}</div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                        <span class="text-neutral-500">{{ __('Lot No.') }}</span><span class="font-mono">{{ $left['chemical_a']['lot_number'] ?? '-' }}</span>
                        <span class="text-neutral-500">{{ __('Exp Date') }}</span><span>{{ $left['chemical_a']['exp_date'] ?? '-' }}</span>
                        <span class="text-neutral-500">{{ __('Target') }}</span><span>{{ $left['chemical_a']['weight_target'] ?? '-' }} kg</span>
                        <span class="text-neutral-500">{{ __('Actual') }}</span><span class="font-semibold">{{ $left['chemical_a']['weight_actual'] ?? '-' }} kg</span>
                    </div>
                </div>
                {{-- Chemical B --}}
                <div>
                    <div class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-2">{{ __('Chemical B') }}</div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                        <span class="text-neutral-500">{{ __('Lot No.') }}</span><span class="font-mono">{{ $left['chemical_b']['lot_number'] ?? '-' }}</span>
                        <span class="text-neutral-500">{{ __('Exp Date') }}</span><span>{{ $left['chemical_b']['exp_date'] ?? '-' }}</span>
                        <span class="text-neutral-500">{{ __('Target') }}</span><span>{{ $left['chemical_b']['weight_target'] ?? '-' }} kg</span>
                        <span class="text-neutral-500">{{ __('Actual') }}</span><span class="font-semibold">{{ $left['chemical_b']['weight_actual'] ?? '-' }} kg</span>
                        <span class="text-neutral-500">{{ __('Ratio B') }}</span><span>{{ $left['percentage'] ?? '-' }} %</span>
                    </div>
                </div>
            </div>

            {{-- Failed (Emergency) banner --}}
            @if($failedLeft)
            <div class="border-t border-red-200 dark:border-red-700 px-5 py-4 bg-red-50 dark:bg-red-900/30 flex items-center gap-3">
                <span class="text-2xl">🚨</span>
                <div>
                    <div class="font-semibold text-red-700 dark:text-red-300">{{ __('Emergency Stop — Mixing Failed') }}</div>
                    <div class="text-xs text-red-500 dark:text-red-400 mt-0.5">{{ __('This mixing has been logged as failed.') }}</div>
                </div>
            </div>
            @endif

            {{-- Footer: action / result --}}
            <div x-show="leftFinished" x-transition class="border-t border-neutral-100 dark:border-neutral-700 px-5 py-4">
                @if(!$completedLeft && !$failedLeft)
                    @if($completeErrorLeft)
                        <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $completeErrorLeft }}</p>
                    @endif
                    <button
                        wire:click="completeMixingLeft"
                        wire:loading.attr="disabled"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold rounded-lg text-sm transition-colors"
                    >
                        <i wire:loading.remove wire:target="completeMixingLeft" class="icon-check"></i>
                        <span wire:loading.remove wire:target="completeMixingLeft">{{ __('Save & Complete') }}</span>
                        <span wire:loading wire:target="completeMixingLeft" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            {{ __('Saving...') }}
                        </span>
                    </button>
                @else
                    <div class="space-y-2">
                        {{-- Output --}}
                        <div class="flex items-center justify-between rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 px-3 py-2">
                            <div>
                                <div class="text-xs text-green-600 dark:text-green-400 font-semibold uppercase tracking-wide">{{ __('Output created') }}</div>
                                <div class="font-mono font-bold text-green-700 dark:text-green-300">{{ $completedStockLeft['output_code'] ?? '' }}</div>
                                <div class="text-xs text-neutral-500">{{ __('Stock') }} #{{ $completedStockLeft['stock_id'] ?? '' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-bold text-green-600 dark:text-green-400">+{{ $completedStockLeft['quantity'] ?? '' }}</div>
                                <div class="text-xs text-neutral-500">{{ $completedStockLeft['uom'] ?? '' }}</div>
                            </div>
                        </div>
                        {{-- Consumed inputs --}}
                        @foreach([($completedStockLeft['decrease_a'] ?? null), ($completedStockLeft['decrease_b'] ?? null)] as $d)
                            @if(!empty($d) && !isset($d['warning']))
                                <div class="flex items-center justify-between rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 px-3 py-2">
                                    <div>
                                        <div class="text-xs text-red-500 font-semibold uppercase tracking-wide">{{ __('Consumed') }}</div>
                                        <div class="font-mono font-medium text-neutral-700 dark:text-neutral-300">{{ $d['item_code'] ?? '' }}</div>
                                        @if(!empty($d['lot_number'])) <div class="text-xs text-neutral-400">{{ __('Lot') }}: {{ $d['lot_number'] }}</div> @endif
                                    </div>
                                    <div class="text-right text-sm">
                                        <div class="text-red-500 font-bold">−{{ $d['used'] ?? '' }}</div>
                                        <div class="text-xs text-neutral-400">{{ $d['before'] ?? '' }} → {{ $d['after'] ?? '' }}</div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        @if($completedLeft && $completedRight)
                            <a href="{{ route('insights.ce.mixing.create') }}" wire:navigate
                               class="mt-1 flex items-center justify-center gap-2 w-full px-4 py-2 rounded-lg bg-caldy-500 hover:bg-caldy-600 text-white font-medium text-sm">
                                <i class="icon-plus"></i> {{ __('New Mixing') }}
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════ RIGHT HEAD ═══════════════════════ --}}
        <div
            x-data="{ open: false }"
            class="bg-white dark:bg-neutral-800 shadow sm:rounded-xl overflow-hidden"
            :class="miconB.em === 1 ? 'ring-2 ring-red-500' : ''"
        >
            {{-- Header banner --}}
            <div class="flex items-center gap-3 px-5 py-3"
                 :class="rightFinished ? 'bg-green-500' : (miconB.em === 1 ? 'bg-red-500' : 'bg-emerald-500')">
                <span class="text-white font-bold text-sm tracking-widest uppercase">{{ __('Right') }}</span>
                @if(!empty($mixingData['recipe_right']))
                    <span class="ml-1 text-white/80 font-mono text-xs">→ {{ $mixingData['recipe_right']['output_code'] ?? '' }}</span>
                @endif
                <span class="ml-auto text-white/70 text-xs" x-show="miconB.em === 1">🚨 {{ __('Emergency Stop') }}</span>
                <span class="ml-auto text-white/70 text-xs" x-show="miconB.st === null || (miconB.st === 0 && miconB.ti === 0 && miconB.em === 0)">
                    <svg class="inline animate-spin h-3 w-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    {{ __('Waiting...') }}
                </span>
            </div>

            {{-- Progress + timer --}}
            <div class="px-5 pt-4 pb-2">
                <div class="flex items-end justify-between mb-1">
                    <span class="text-3xl font-bold tabular-nums" x-text="rightTimeDisplay"
                          :class="rightFinished ? 'text-green-500' : (miconB.em === 1 ? 'text-red-500' : 'text-emerald-500')"></span>
                    <span class="text-sm text-neutral-400 mb-1" x-text="Math.round(rightProgress) + '%'"></span>
                </div>
                <div class="w-full bg-neutral-100 dark:bg-neutral-700 rounded-full h-2 overflow-hidden">
                    <div class="h-2 rounded-full transition-all duration-1000"
                         :class="rightFinished ? 'bg-green-500' : (miconB.em === 1 ? 'bg-red-500' : 'bg-emerald-500')"
                         :style="'width:' + rightProgress + '%'"></div>
                </div>
            </div>

            {{-- Chemical summary row (always visible) + click to expand --}}
            <button type="button" @click="open = !open"
                    class="w-full flex items-center justify-between px-5 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition-colors text-left">
                <div class="flex gap-4 text-sm">
                    <div>
                        <div class="text-xs text-neutral-400 uppercase tracking-wide">A</div>
                        <div class="font-semibold text-neutral-700 dark:text-neutral-200 truncate max-w-[120px]">{{ $right['chemical_a']['chemical_name'] ?? '-' }}</div>
                        <div class="text-xs text-neutral-500">{{ $right['chemical_a']['weight_actual'] ?? '-' }} kg</div>
                    </div>
                    <div class="self-center text-neutral-300 dark:text-neutral-600 font-bold">+</div>
                    <div>
                        <div class="text-xs text-neutral-400 uppercase tracking-wide">B</div>
                        <div class="font-semibold text-neutral-700 dark:text-neutral-200 truncate max-w-[120px]">{{ $right['chemical_b']['chemical_name'] ?? '-' }}</div>
                        <div class="text-xs text-neutral-500">{{ $right['chemical_b']['weight_actual'] ?? '-' }} kg · {{ $right['percentage'] ?? '-' }}%</div>
                    </div>
                </div>
                <i class="icon-chevron-down text-neutral-400 transition-transform" :class="open && 'rotate-180'"></i>
            </button>

            {{-- Expandable detail --}}
            <div x-show="open" x-collapse class="border-t border-neutral-100 dark:border-neutral-700 px-5 py-4 space-y-4 text-sm">
                {{-- Chemical A --}}
                <div>
                    <div class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-2">{{ __('Chemical A') }}</div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                        <span class="text-neutral-500">{{ __('Lot No.') }}</span><span class="font-mono">{{ $right['chemical_a']['lot_number'] ?? '-' }}</span>
                        <span class="text-neutral-500">{{ __('Exp Date') }}</span><span>{{ $right['chemical_a']['exp_date'] ?? '-' }}</span>
                        <span class="text-neutral-500">{{ __('Target') }}</span><span>{{ $right['chemical_a']['weight_target'] ?? '-' }} kg</span>
                        <span class="text-neutral-500">{{ __('Actual') }}</span><span class="font-semibold">{{ $right['chemical_a']['weight_actual'] ?? '-' }} kg</span>
                    </div>
                </div>
                {{-- Chemical B --}}
                <div>
                    <div class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-2">{{ __('Chemical B') }}</div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                        <span class="text-neutral-500">{{ __('Lot No.') }}</span><span class="font-mono">{{ $right['chemical_b']['lot_number'] ?? '-' }}</span>
                        <span class="text-neutral-500">{{ __('Exp Date') }}</span><span>{{ $right['chemical_b']['exp_date'] ?? '-' }}</span>
                        <span class="text-neutral-500">{{ __('Target') }}</span><span>{{ $right['chemical_b']['weight_target'] ?? '-' }} kg</span>
                        <span class="text-neutral-500">{{ __('Actual') }}</span><span class="font-semibold">{{ $right['chemical_b']['weight_actual'] ?? '-' }} kg</span>
                        <span class="text-neutral-500">{{ __('Ratio B') }}</span><span>{{ $right['percentage'] ?? '-' }} %</span>
                    </div>
                </div>
            </div>

            {{-- Failed (Emergency) banner --}}
            @if($failedRight)
            <div class="border-t border-red-200 dark:border-red-700 px-5 py-4 bg-red-50 dark:bg-red-900/30 flex items-center gap-3">
                <span class="text-2xl">🚨</span>
                <div>
                    <div class="font-semibold text-red-700 dark:text-red-300">{{ __('Emergency Stop — Mixing Failed') }}</div>
                    <div class="text-xs text-red-500 dark:text-red-400 mt-0.5">{{ __('This mixing has been logged as failed.') }}</div>
                </div>
            </div>
            @endif

            {{-- Footer: action / result --}}
            <div x-show="rightFinished" x-transition class="border-t border-neutral-100 dark:border-neutral-700 px-5 py-4">
                @if(!$completedRight && !$failedRight)
                    @if($completeErrorRight)
                        <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $completeErrorRight }}</p>
                    @endif
                    <button
                        wire:click="completeMixingRight"
                        wire:loading.attr="disabled"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-semibold rounded-lg text-sm transition-colors"
                    >
                        <i wire:loading.remove wire:target="completeMixingRight" class="icon-check"></i>
                        <span wire:loading.remove wire:target="completeMixingRight">{{ __('Save & Complete') }}</span>
                        <span wire:loading wire:target="completeMixingRight" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            {{ __('Saving...') }}
                        </span>
                    </button>
                @else
                    <div class="space-y-2">
                        {{-- Output --}}
                        <div class="flex items-center justify-between rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 px-3 py-2">
                            <div>
                                <div class="text-xs text-green-600 dark:text-green-400 font-semibold uppercase tracking-wide">{{ __('Output created') }}</div>
                                <div class="font-mono font-bold text-green-700 dark:text-green-300">{{ $completedStockRight['output_code'] ?? '' }}</div>
                                <div class="text-xs text-neutral-500">{{ __('Stock') }} #{{ $completedStockRight['stock_id'] ?? '' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-bold text-green-600 dark:text-green-400">+{{ $completedStockRight['quantity'] ?? '' }}</div>
                                <div class="text-xs text-neutral-500">{{ $completedStockRight['uom'] ?? '' }}</div>
                            </div>
                        </div>
                        {{-- Consumed inputs --}}
                        @foreach([($completedStockRight['decrease_a'] ?? null), ($completedStockRight['decrease_b'] ?? null)] as $d)
                            @if(!empty($d) && !isset($d['warning']))
                                <div class="flex items-center justify-between rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 px-3 py-2">
                                    <div>
                                        <div class="text-xs text-red-500 font-semibold uppercase tracking-wide">{{ __('Consumed') }}</div>
                                        <div class="font-mono font-medium text-neutral-700 dark:text-neutral-300">{{ $d['item_code'] ?? '' }}</div>
                                        @if(!empty($d['lot_number'])) <div class="text-xs text-neutral-400">{{ __('Lot') }}: {{ $d['lot_number'] }}</div> @endif
                                    </div>
                                    <div class="text-right text-sm">
                                        <div class="text-red-500 font-bold">−{{ $d['used'] ?? '' }}</div>
                                        <div class="text-xs text-neutral-400">{{ $d['before'] ?? '' }} → {{ $d['after'] ?? '' }}</div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        @if($completedLeft && $completedRight)
                            <a href="{{ route('insights.ce.mixing.create') }}" wire:navigate
                               class="mt-1 flex items-center justify-center gap-2 w-full px-4 py-2 rounded-lg bg-caldy-500 hover:bg-caldy-600 text-white font-medium text-sm">
                                <i class="icon-plus"></i> {{ __('New Mixing') }}
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
