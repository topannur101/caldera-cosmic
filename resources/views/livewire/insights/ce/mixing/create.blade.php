<?php

use App\Models\InvCeAuth;
use App\Models\InvCeRecipe;
use App\Models\InvCeStock;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    public string $cookieKey = 'invce_mixing_auth';

    // Recipe selection
    public array $recipes = [];

    public ?int $recipe_id_left = null;
    public ?int $recipe_id_right = null;

    public array $selectedRecipeLeft = [];
    public array $selectedRecipeRight = [];

    // RFID Auth
    public array $auth = [
        'status' => '',
        'rf_code' => '',
        'name' => '',
        'emp_id' => '',
        'is_active' => 0,
        'area' => '',
        'resource_type' => '',
        'resource_id' => 0,
    ];

    public bool $isAuthenticated = false;

    public string $rfidError = '';

    // Device / Micon
    public string $device_name = '';

    // Left Head - Chemical A
    public string $item_code_left_a = '';
    public string $chemical_name_left_a = '';
    public array  $lot_numbers_left_a = [];
    public string $stock_id_left_a = '';
    public string $lot_number_left_a = '';
    public string $exp_date_left_a = '';
    public string $weight_target_left_a = '';
    public string $weight_actual_left_a = '';

    // Left Head - Chemical B
    public string $item_code_left_b = '';
    public string $chemical_name_left_b = '';
    public array  $lot_numbers_left_b = [];
    public string $stock_id_left_b = '';
    public string $lot_number_left_b = '';
    public string $exp_date_left_b = '';
    public string $weight_target_left_b = '';
    public string $weight_actual_left_b = '';
    public string $percentage_left = '';

    // Right Head - Chemical A
    public string $item_code_right_a = '';
    public string $chemical_name_right_a = '';
    public array  $lot_numbers_right_a = [];
    public string $stock_id_right_a = '';
    public string $lot_number_right_a = '';
    public string $exp_date_right_a = '';
    public string $weight_target_right_a = '';
    public string $weight_actual_right_a = '';

    // Right Head - Chemical B
    public string $item_code_right_b = '';
    public string $chemical_name_right_b = '';
    public array  $lot_numbers_right_b = [];
    public string $stock_id_right_b = '';
    public string $lot_number_right_b = '';
    public string $exp_date_right_b = '';
    public string $weight_target_right_b = '';
    public string $weight_actual_right_b = '';
    public string $percentage_right = '';

    public function mount(): void
    {
        $this->recipes = InvCeRecipe::query()
            ->with(['chemical:id,item_code,name', 'hardener:id,item_code,name'])
            ->where('is_active', true)
            ->orderBy('line')
            ->orderBy('model')
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'line'            => $r->line,
                'model'           => $r->model,
                'area'            => $r->area,
                'chemical_code'   => $r->chemical?->item_code ?? '',
                'chemical_name'   => $r->chemical?->name ?? '',
                'hardener_code'   => $r->hardener?->item_code ?? '',
                'hardener_name'   => $r->hardener?->name ?? '',
                'hardener_ratio'  => $r->hardener_ratio,
                'output_code'     => $r->output_code,
                'potlife'         => $r->potlife,
                'target_weight'   => $r->additional_settings['target_weight'] ?? null,
                'up_dev'          => $r->additional_settings['up_dev'] ?? null,
                'low_dev'         => $r->additional_settings['low_dev'] ?? null,
            ])
            ->toArray();
    }

    public function updatedRecipeIdLeft(?int $value): void
    {
        if (! $value) {
            $this->selectedRecipeLeft = [];
            $this->clearChemicalFieldsLeft();
            return;
        }

        $recipe = collect($this->recipes)->firstWhere('id', $value);
        if (! $recipe) {
            $this->selectedRecipeLeft = [];
            return;
        }

        $this->selectedRecipeLeft = $recipe;

        $this->item_code_left_a   = $recipe['chemical_code'];
        $this->chemical_name_left_a = $recipe['chemical_name'];
        $this->item_code_left_b   = $recipe['hardener_code'];
        $this->chemical_name_left_b = $recipe['hardener_name'];
        $this->percentage_left    = (string) $recipe['hardener_ratio'];

        if (isset($recipe['target_weight']) && $recipe['target_weight'] !== null) {
            $ratio = (float) $recipe['hardener_ratio'] / 100;
            $wB = round((float) $recipe['target_weight'] * $ratio, 2);
            $wA = round((float) $recipe['target_weight'] - $wB, 2);
            $this->weight_target_left_a = (string) $wA;
            $this->weight_target_left_b = (string) $wB;
        }

        $this->lot_numbers_left_a = $this->getLotNumbersByItemCode($recipe['chemical_code']);
        $this->lot_numbers_left_b = $this->getLotNumbersByItemCode($recipe['hardener_code']);
        $this->stock_id_left_a    = '';
        $this->stock_id_left_b    = '';
        $this->lot_number_left_a  = '';
        $this->lot_number_left_b  = '';
        $this->exp_date_left_a    = '';
        $this->exp_date_left_b    = '';
    }

    public function updatedRecipeIdRight(?int $value): void
    {
        if (! $value) {
            $this->selectedRecipeRight = [];
            $this->clearChemicalFieldsRight();
            return;
        }

        $recipe = collect($this->recipes)->firstWhere('id', $value);
        if (! $recipe) {
            $this->selectedRecipeRight = [];
            return;
        }

        $this->selectedRecipeRight = $recipe;

        $this->item_code_right_a   = $recipe['chemical_code'];
        $this->chemical_name_right_a = $recipe['chemical_name'];
        $this->item_code_right_b   = $recipe['hardener_code'];
        $this->chemical_name_right_b = $recipe['hardener_name'];
        $this->percentage_right    = (string) $recipe['hardener_ratio'];

        if (isset($recipe['target_weight']) && $recipe['target_weight'] !== null) {
            $ratio = (float) $recipe['hardener_ratio'] / 100;
            $wB = round((float) $recipe['target_weight'] * $ratio, 2);
            $wA = round((float) $recipe['target_weight'] - $wB, 2);
            $this->weight_target_right_a = (string) $wA;
            $this->weight_target_right_b = (string) $wB;
        }

        $this->lot_numbers_right_a = $this->getLotNumbersByItemCode($recipe['chemical_code']);
        $this->lot_numbers_right_b = $this->getLotNumbersByItemCode($recipe['hardener_code']);
        $this->stock_id_right_a    = '';
        $this->stock_id_right_b    = '';
        $this->lot_number_right_a  = '';
        $this->lot_number_right_b  = '';
        $this->exp_date_right_a    = '';
        $this->exp_date_right_b    = '';
    }

    private function clearChemicalFieldsLeft(): void
    {
        $this->item_code_left_a = $this->chemical_name_left_a = '';
        $this->item_code_left_b = $this->chemical_name_left_b = '';
        $this->percentage_left  = '';
        $this->lot_numbers_left_a = $this->lot_numbers_left_b = [];
        $this->stock_id_left_a = $this->stock_id_left_b = '';
        $this->lot_number_left_a = $this->lot_number_left_b = '';
        $this->exp_date_left_a = $this->exp_date_left_b = '';
    }

    private function clearChemicalFieldsRight(): void
    {
        $this->item_code_right_a = $this->chemical_name_right_a = '';
        $this->item_code_right_b = $this->chemical_name_right_b = '';
        $this->percentage_right  = '';
        $this->lot_numbers_right_a = $this->lot_numbers_right_b = [];
        $this->stock_id_right_a = $this->stock_id_right_b = '';
        $this->lot_number_right_a = $this->lot_number_right_b = '';
        $this->exp_date_right_a = $this->exp_date_right_b = '';
    }

    /**
     * Get lot numbers with expiry dates for a given item_code via a single join query.
     * Returns array of ['lot_number' => ..., 'expiry_date' => ...].
     */
    private function getLotNumbersByItemCode(string $item_code): array
    {
        if ($item_code === '') {
            return [];
        }

        return InvCeStock::query()
            ->join('inv_ce_chemicals', 'inv_ce_stock.inv_ce_chemical_id', '=', 'inv_ce_chemicals.id')
            ->where('inv_ce_chemicals.item_code', $item_code)
            ->where('inv_ce_stock.quantity', '>', 0)
            ->orderBy('inv_ce_stock.expiry_date')
            ->select('inv_ce_stock.id', 'inv_ce_stock.lot_number', 'inv_ce_stock.expiry_date')
            ->get()
            ->map(fn($s) => [
                'id'          => (string) $s->id,
                'lot_number'  => $s->lot_number,
                'expiry_date' => $s->expiry_date ? \Carbon\Carbon::parse($s->expiry_date)->format('Y-m-d') : '',
            ])
            ->toArray();
    }

    public function updatedStockIdLeftA(string $value): void
    {
        $found = collect($this->lot_numbers_left_a)->firstWhere('id', $value);
        $this->lot_number_left_a = $found['lot_number'] ?? '';
        $this->exp_date_left_a   = $found['expiry_date'] ?? '';
    }

    public function updatedStockIdLeftB(string $value): void
    {
        $found = collect($this->lot_numbers_left_b)->firstWhere('id', $value);
        $this->lot_number_left_b = $found['lot_number'] ?? '';
        $this->exp_date_left_b   = $found['expiry_date'] ?? '';
    }

    public function updatedStockIdRightA(string $value): void
    {
        $found = collect($this->lot_numbers_right_a)->firstWhere('id', $value);
        $this->lot_number_right_a = $found['lot_number'] ?? '';
        $this->exp_date_right_a   = $found['expiry_date'] ?? '';
    }

    public function updatedStockIdRightB(string $value): void
    {
        $found = collect($this->lot_numbers_right_b)->firstWhere('id', $value);
        $this->lot_number_right_b = $found['lot_number'] ?? '';
        $this->exp_date_right_b   = $found['expiry_date'] ?? '';
    }

    public function searchTTCode(string $code): void
    {
        $code = trim($code);
        $this->rfidError = '';

        if ($code === '') {
            Cookie::queue(Cookie::forget($this->cookieKey));
            $this->auth = ['status' => '', 'rf_code' => '', 'name' => '', 'emp_id' => '', 'is_active' => 0, 'area' => '', 'resource_type' => '', 'resource_id' => 0];
            $this->isAuthenticated = false;
            return;
        }

        $authRfid = InvCeAuth::query()
            ->with('user')
            ->where('rf_code', $code)
            ->first();

        $authUser = $authRfid?->user;

        if ($authUser) {
            $this->auth = [
                'status' => 'found',
                'rf_code' => $authRfid->rf_code,
                'name' => $authUser->name,
                'emp_id' => $authUser->emp_id,
                'is_active' => (int) ($authUser->is_active ?? 0),
                'area' => $authRfid->area,
                'resource_type' => $authRfid->resource_type,
                'resource_id' => $authRfid->resource_id,
            ];
            $this->isAuthenticated = true;
            Cookie::queue($this->cookieKey, json_encode($this->auth), 60 * 24);
        } else {
            $this->auth = [
                'status' => 'not_found',
                'rf_code' => $code,
                'name' => '',
                'emp_id' => '',
                'is_active' => 0,
                'area' => '',
                'resource_type' => '',
                'resource_id' => 0,
            ];
            $this->isAuthenticated = false;
            $this->rfidError = 'RFID tidak terdaftar';
            Cookie::queue($this->cookieKey, json_encode($this->auth), 60 * 24);
        }
    }

    public function resetForm(): void
    {
        $this->recipe_id_left = null;
        $this->recipe_id_right = null;
        $this->selectedRecipeLeft = [];
        $this->selectedRecipeRight = [];
        $this->device_name = '';

        $this->lot_number_left_a = $this->exp_date_left_a = '';
        $this->weight_target_left_a = $this->weight_actual_left_a = '';
        $this->lot_number_left_b = $this->exp_date_left_b = '';
        $this->weight_target_left_b = $this->weight_actual_left_b = '';

        $this->lot_number_right_a = $this->exp_date_right_a = '';
        $this->weight_target_right_a = $this->weight_actual_right_a = '';
        $this->lot_number_right_b = $this->exp_date_right_b = '';
        $this->weight_target_right_b = $this->weight_actual_right_b = '';

        $this->clearChemicalFieldsLeft();
        $this->clearChemicalFieldsRight();
    }

    public function setAllActualWeights(float|string $weight): void
    {
        $value = number_format((float) $weight, 2, '.', '');

        $this->weight_actual_left_a = $value;
        $this->weight_actual_left_b = $value;
        $this->weight_actual_right_a = $value;
        $this->weight_actual_right_b = $value;
    }

    public function submitForm(): void
    {
        if (! $this->isAuthenticated) {
            return;
        }

        session([
            'mixing_data' => [
                'recipe_id_left'   => $this->recipe_id_left,
                'recipe_id_right'  => $this->recipe_id_right,
                'recipe_left'      => $this->selectedRecipeLeft,
                'recipe_right'     => $this->selectedRecipeRight,
                'device_name' => $this->device_name,
                'operator'    => $this->auth['name'],
                'left' => [
                    'chemical_a' => [
                        'item_code'     => $this->item_code_left_a,
                        'chemical_name' => $this->chemical_name_left_a,
                        'lot_number'    => $this->lot_number_left_a,
                        'exp_date'      => $this->exp_date_left_a,
                        'weight_target' => $this->weight_target_left_a,
                        'weight_actual' => $this->weight_actual_left_a,
                    ],
                    'chemical_b' => [
                        'item_code'     => $this->item_code_left_b,
                        'chemical_name' => $this->chemical_name_left_b,
                        'lot_number'    => $this->lot_number_left_b,
                        'exp_date'      => $this->exp_date_left_b,
                        'weight_target' => $this->weight_target_left_b,
                        'weight_actual' => $this->weight_actual_left_b,
                    ],
                    'percentage' => $this->percentage_left,
                ],
                'right' => [
                    'chemical_a' => [
                        'item_code'     => $this->item_code_right_a,
                        'chemical_name' => $this->chemical_name_right_a,
                        'lot_number'    => $this->lot_number_right_a,
                        'exp_date'      => $this->exp_date_right_a,
                        'weight_target' => $this->weight_target_right_a,
                        'weight_actual' => $this->weight_actual_right_a,
                    ],
                    'chemical_b' => [
                        'item_code'     => $this->item_code_right_b,
                        'chemical_name' => $this->chemical_name_right_b,
                        'lot_number'    => $this->lot_number_right_b,
                        'exp_date'      => $this->exp_date_right_b,
                        'weight_target' => $this->weight_target_right_b,
                        'weight_actual' => $this->weight_actual_right_b,
                    ],
                    'percentage' => $this->percentage_right,
                ],
                'started_at' => now()->toISOString(),
            ],
        ]);

        $this->redirect(route('insights.ce.mixing.process-timer'), navigate: true);
    }

};

?>

<x-slot name="header">
    <x-nav-insights-ce-mix></x-nav-insights-ce-mix>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200 gap-4">
    <div class="p-0 sm:p-1 mb-6">
        <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4 mb-4">
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <div
                    x-data="{
                        auth: null,
                        initFromCookie() {
                            try {
                                const raw = document.cookie
                                    .split('; ')
                                    .find(r => r.startsWith('{{ $cookieKey }}='))
                                    ?.split('=').slice(1).join('=');
                                if (raw) this.auth = JSON.parse(decodeURIComponent(raw));
                            } catch (_) {}
                        },
                    }"
                    x-init="initFromCookie()"
                    @rfid-result.window="auth = $event.detail"
                    class="flex items-center gap-2"
                >
                    <span class="text-neutral-500">RFID Auth:</span>
                    <span
                        class="font-medium"
                        :class="{
                            'text-green-600 dark:text-green-400': auth && auth.status === 'found',
                            'text-red-600 dark:text-red-400': auth && auth.status === 'not_found',
                            'text-neutral-500': !auth || !auth.status,
                        }"
                        x-text="auth && auth.status === 'found'
                            ? `${auth.name} (${auth.rf_code})`
                            : auth && auth.status === 'not_found'
                                ? `RFID ${auth.rf_code} not registered`
                                : 'Waiting card'"
                    ></span>
                </div>

                <span class="hidden sm:inline text-neutral-300 dark:text-neutral-600">|</span>

                <div
                    wire:ignore
                    x-data="{
                        storageKey: 'invce_mixing_last_rfid',
                        url: @js(config('rfid.ws_url_rfid')),
                        ws: null,
                        connected: false,
                        error: '',
                        lastRawMessage: '',
                        code: '',
                        lastProcessedCode: '',
                        lastProcessedAt: 0,
                        wireDebounceTimer: null,
                        reconnectAttempt: 0,
                        reconnectTimer: null,

                        loadSavedCode() {
                            try {
                                const saved = localStorage.getItem(this.storageKey);
                                if (saved) {
                                    this.code = String(saved).trim();
                                    if (typeof $wire !== 'undefined') $wire.searchTTCode(this.code);
                                }
                            } catch (_) {}
                        },

                        saveCode(code) {
                            try { localStorage.setItem(this.storageKey, code); } catch (_) {}
                        },

                        connect() {
                            if (!this.url) {
                                this.setDisconnected('RFID_WS_URL is empty');
                                return;
                            }
                            if (window.location?.protocol === 'https:' && this.url.startsWith('ws://')) {
                                this.setDisconnected('Page is HTTPS, WebSocket must be WSS');
                                return;
                            }
                            try {
                                this.ws = new WebSocket(this.url);
                            } catch (e) {
                                this.setDisconnected(e?.message ?? 'Failed to create WebSocket');
                                this.scheduleReconnect();
                                return;
                            }

                            this.ws.onopen = () => {
                                this.reconnectAttempt = 0;
                                this.connected = true;
                                this.error = '';
                            };

                            this.ws.onmessage = (event) => {
                                const raw = typeof event?.data === 'string' ? event.data : '';
                                this.lastRawMessage = raw;
                                let payload = raw;
                                if (raw && (raw.startsWith('{') || raw.startsWith('['))) {
                                    try { payload = JSON.parse(raw); } catch (_) {}
                                }
                                let code = '';
                                if (typeof payload === 'string') {
                                    code = payload;
                                } else if (payload && typeof payload === 'object') {
                                    code = payload.data ?? payload.code ?? payload.tag ?? payload.uid ?? '';
                                    if (code === '' && typeof payload.message === 'string') code = payload.message;
                                }
                                code = String(code ?? '').replace(/[\x00-\x1F\x7F]/g, '').trim();
                                if (code !== '') {
                                    const now = Date.now();
                                    if (code === this.lastProcessedCode && (now - this.lastProcessedAt) < 800) return;
                                    this.lastProcessedCode = code;
                                    this.lastProcessedAt = now;
                                    this.code = code;
                                    this.saveCode(code);
                                    try {
                                        if (typeof $wire !== 'undefined') {
                                            if (this.wireDebounceTimer) clearTimeout(this.wireDebounceTimer);
                                            this.wireDebounceTimer = setTimeout(() => {
                                                $wire.searchTTCode(code);
                                            }, 150);
                                        }
                                    } catch (_) {}
                                }
                            };

                            this.ws.onerror = () => {
                                this.setDisconnected('WebSocket error');
                            };

                            this.ws.onclose = (evt) => {
                                const reason = evt?.reason ? `Disconnected: ${evt.reason}` : 'Disconnected';
                                this.setDisconnected(reason);
                                this.scheduleReconnect();
                            };
                        },

                        setDisconnected(message) {
                            this.connected = false;
                            if (message) this.error = String(message);
                        },

                        scheduleReconnect() {
                            if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
                            const delay = Math.min(10000, 1000 * Math.pow(2, this.reconnectAttempt++));
                            this.reconnectTimer = setTimeout(() => this.connect(), delay);
                        },
                    }"
                    x-init="connect(); $watch('code', value => { if(value) loadSavedCode() })"
                    class="flex items-center gap-2"
                >
                    <span class="text-neutral-500">RFID WS:</span>
                    <span :class="connected ? 'text-green-500 font-medium' : 'text-red-500 font-medium'" x-text="connected ? 'Connected' : 'Disconnected'"></span>
                </div>

                <span class="hidden sm:inline text-neutral-300 dark:text-neutral-600">|</span>

                <div
                    wire:ignore
                    x-data="{
                        url: 'ws://127.0.0.1:8767/',
                        ws: null,
                        connected: false,
                        error: '',
                        reconnectAttempt: 0,
                        reconnectTimer: null,

                        setConnected() {
                            this.connected = true;
                            this.error = '';
                            this.reconnectAttempt = 0;
                        },

                        setDisconnected(message) {
                            this.connected = false;
                            if (message) this.error = String(message);
                        },

                        syncWeightField(field, value) {
                            if (typeof $wire !== 'undefined') {
                                $wire.set(field, value);
                            }

                            const input = document.getElementById(field);
                            if (input) {
                                input.value = value;
                                input.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        },

                        showWeightOnForm(berat) {
                            if (!Number.isNaN(berat)) {
                                const value = Number(berat).toFixed(2);

                                this.syncWeightField('weight_actual_left_a', value);
                                this.syncWeightField('weight_actual_left_b', value);
                                this.syncWeightField('weight_actual_right_a', value);
                                this.syncWeightField('weight_actual_right_b', value);
                            }
                        },

                        handleWeightMessage(raw) {
                            const idMatch = raw.match(/\[ID:(\d+)\]/);
                            const beratMatch = raw.match(/Berat:\s*([\d.]+)/);

                            if (idMatch && beratMatch && idMatch[1] === '2') {
                                this.showWeightOnForm(parseFloat(beratMatch[1]));
                            }
                        },

                        connect() {
                            if (!this.url) {
                                this.setDisconnected('Weight WS URL is empty');
                                return;
                            }
                            if (window.location?.protocol === 'https:' && this.url.startsWith('ws://')) {
                                this.setDisconnected('Page is HTTPS, WebSocket must be WSS');
                                return;
                            }
                            try {
                                this.ws = new WebSocket(this.url);
                            } catch (e) {
                                this.setDisconnected(e?.message ?? 'Failed to create WebSocket');
                                this.scheduleReconnect();
                                return;
                            }

                            this.ws.onopen = () => {
                                this.setConnected();
                            };

                            this.ws.onmessage = (event) => {
                                const raw = typeof event?.data === 'string' ? event.data : '';
                                this.handleWeightMessage(raw);
                            };

                            this.ws.onerror = () => {
                                this.setDisconnected('WebSocket error');
                            };

                            this.ws.onclose = (evt) => {
                                const reason = evt?.reason ? `Disconnected: ${evt.reason}` : 'Disconnected';
                                this.setDisconnected(reason);
                                this.scheduleReconnect();
                            };
                        },

                        scheduleReconnect() {
                            if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
                            const delay = Math.min(10000, 1000 * Math.pow(2, this.reconnectAttempt++));
                            this.reconnectTimer = setTimeout(() => this.connect(), delay);
                        },
                    }"
                    x-init="connect()"
                    class="flex items-center gap-2"
                >
                    <span class="text-neutral-500">Weight WS:</span>
                    <span :class="connected ? 'text-green-500 font-medium' : 'text-red-500 font-medium'" x-text="connected ? 'Connected' : 'Disconnected'"></span>
                </div>

                @if($rfidError)
                    <span class="text-red-500 text-xs">{{ $rfidError }}</span>
                @endif
            </div>
        </div>

        <!-- Operator / Plant Info -->
        <div class="flex gap-3 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 mb-4">
            <div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Operator") }}</label>
                @if($isAuthenticated)
                    <span class="px-3 py-1 bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-300 rounded-md">{{ $auth['name'] }}</span>
                @else
                    <span class="px-3 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-md text-red-500">{{ __("Tap ID Card") }}</span>
                @endif
            </div>
            <div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Plant") }}</label>
                @if($isAuthenticated)
                    <span class="px-3 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-md">Plant {{ $auth['area'] }}</span>
                @else
                    <span class="px-3 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-md text-red-500">{{ __("Tap ID Card") }}</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Left/Right Head forms -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Left Head -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 border-l-4 border-l-blue-500">
            <div class="text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="px-2 py-1 bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-300 text-xs font-semibold rounded">{{ __("LEFT HEAD") }}</span>
            </div>

            <!-- Left Head Recipe Selector -->
            <div class="mb-4">
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Recipe") }}</label>
                <select wire:model.live="recipe_id_left"
                    class="w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                    <option value="">{{ __("— Select recipe —") }}</option>
                    @foreach ($recipes as $r)
                        <option value="{{ $r['id'] }}">
                            [{{ $r['line'] }}] {{ $r['model'] }} · {{ $r['area'] }} → {{ $r['output_code'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if(!empty($selectedRecipeLeft))
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 mb-4 bg-caldy-50 dark:bg-caldy-900 border border-caldy-200 dark:border-caldy-700 rounded-lg text-sm">
                <div>
                    <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Chemical (A)") }}</span>
                    <span class="font-mono font-medium">{{ $selectedRecipeLeft['chemical_code'] }}</span>
                    <span class="block text-xs text-neutral-500">{{ $selectedRecipeLeft['chemical_name'] }}</span>
                </div>
                <div>
                    <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Hardener (B)") }}</span>
                    <span class="font-mono font-medium">{{ $selectedRecipeLeft['hardener_code'] }}</span>
                    <span class="block text-xs text-neutral-500">{{ $selectedRecipeLeft['hardener_name'] }}</span>
                </div>
                <div>
                    <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Ratio B") }}</span>
                    <span class="font-semibold text-caldy-600 dark:text-caldy-400">{{ $selectedRecipeLeft['hardener_ratio'] }}%</span>
                </div>
                <div>
                    <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Output Code") }}</span>
                    <span class="font-mono font-medium">{{ $selectedRecipeLeft['output_code'] }}</span>
                    <span class="block text-xs text-neutral-500">Potlife: {{ $selectedRecipeLeft['potlife'] }} hr</span>
                </div>
            </div>
            @endif

            <!-- Left Head - Chemical A -->
            <div class="mb-6 pb-6 border-b border-neutral-200 dark:border-neutral-700">
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical A (Base)") }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                        <input type="text" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="item_code_left_a">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Chemical Name") }}</label>
                        <input type="text" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="chemical_name_left_a">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                        <select wire:model.live="stock_id_left_a"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                            @if (count($lot_numbers_left_a) > 0)
                                <option value="">{{ __("— Select lot —") }}</option>
                                @foreach ($lot_numbers_left_a as $lot)
                                    <option value="{{ $lot['id'] }}">{{ $lot['lot_number'] }} (exp: {{ $lot['expiry_date'] }})</option>
                                @endforeach
                            @else
                                <option value="">{{ __("— Select lot —") }}</option>
                                <option value="" disabled>{{ __("No available stock") }}</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Exp Date") }}</label>
                        <input type="date" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="exp_date_left_a">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (g)") }}</label>
                        <input type="number" readonly step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_target_left_a">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (g)") }}</label>
                        <input id="weight_actual_left_a" type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_actual_left_a">
                    </div>
                </div>
            </div>

            <!-- Left Head - Chemical B -->
            <div>
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical B (Hardener)") }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                        <input type="text" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="item_code_left_b">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Chemical Name") }}</label>
                        <input type="text" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="chemical_name_left_b">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                        <select wire:model.live="stock_id_left_b"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                            <option value="">{{ __("— Select lot —") }}</option>
                            @foreach ($lot_numbers_left_b as $lot)
                                <option value="{{ $lot['id'] }}">{{ $lot['lot_number'] }} (exp: {{ $lot['expiry_date'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Exp Date") }}</label>
                        <input type="date" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="exp_date_left_b">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (g)") }}</label>
                        <input type="number" readonly step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_target_left_b">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (g)") }}</label>
                        <input id="weight_actual_left_b" type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_actual_left_b">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Percentage (%)") }}</label>
                        <input type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="percentage_left">
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Head -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 border-l-4 border-l-green-500">
            <div class="text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="px-2 py-1 bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-300 text-xs font-semibold rounded">{{ __("RIGHT HEAD") }}</span>
            </div>

            <!-- Right Head Recipe Selector -->
            <div class="mb-4">
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Recipe") }}</label>
                <select wire:model.live="recipe_id_right"
                    class="w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                    <option value="">{{ __("— Select recipe —") }}</option>
                    @foreach ($recipes as $r)
                        <option value="{{ $r['id'] }}">
                            [{{ $r['line'] }}] {{ $r['model'] }} · {{ $r['area'] }} → {{ $r['output_code'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if(!empty($selectedRecipeRight))
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 mb-4 bg-caldy-50 dark:bg-caldy-900 border border-caldy-200 dark:border-caldy-700 rounded-lg text-sm">
                <div>
                    <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Chemical (A)") }}</span>
                    <span class="font-mono font-medium">{{ $selectedRecipeRight['chemical_code'] }}</span>
                    <span class="block text-xs text-neutral-500">{{ $selectedRecipeRight['chemical_name'] }}</span>
                </div>
                <div>
                    <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Hardener (B)") }}</span>
                    <span class="font-mono font-medium">{{ $selectedRecipeRight['hardener_code'] }}</span>
                    <span class="block text-xs text-neutral-500">{{ $selectedRecipeRight['hardener_name'] }}</span>
                </div>
                <div>
                    <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Ratio B") }}</span>
                    <span class="font-semibold text-caldy-600 dark:text-caldy-400">{{ $selectedRecipeRight['hardener_ratio'] }}%</span>
                </div>
                <div>
                    <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Output Code") }}</span>
                    <span class="font-mono font-medium">{{ $selectedRecipeRight['output_code'] }}</span>
                    <span class="block text-xs text-neutral-500">Potlife: {{ $selectedRecipeRight['potlife'] }} hr</span>
                </div>
                @if(!empty($selectedRecipeRight['target_weight']))
                <div>
                    <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Target Weight") }}</span>
                    <span class="font-semibold">{{ $selectedRecipeRight['target_weight'] }} g</span>
                </div>
                @endif
                @if(!empty($selectedRecipeRight['up_dev']) || !empty($selectedRecipeRight['low_dev']))
                <div>
                    <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Tolerance") }}</span>
                    <span class="text-xs">
                        +{{ $selectedRecipeRight['up_dev'] ?? 0 }}g / -{{ $selectedRecipeRight['low_dev'] ?? 0 }}g
                    </span>
                </div>
                @endif
            </div>
            @endif

            <!-- Right Head - Chemical A -->
            <div class="mb-6 pb-6 border-b border-neutral-200 dark:border-neutral-700">
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical A (Base)") }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                        <input type="text" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="item_code_right_a">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Chemical Name") }}</label>
                        <input type="text" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="chemical_name_right_a">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                        <select wire:model.live="stock_id_right_a"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                            <option value="">{{ __("— Select lot —") }}</option>
                            @foreach ($lot_numbers_right_a as $lot)
                                <option value="{{ $lot['id'] }}">{{ $lot['lot_number'] }} (exp: {{ $lot['expiry_date'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Exp Date") }}</label>
                        <input type="date" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="exp_date_right_a">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (g)") }}</label>
                        <input type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_target_right_a">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (g)") }}</label>
                        <input id="weight_actual_right_a" type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_actual_right_a">
                    </div>
                </div>
            </div>

            <!-- Right Head - Chemical B -->
            <div>
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical B (Hardener)") }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                        <input type="text" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="item_code_right_b">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Chemical Name") }}</label>
                        <input type="text" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="chemical_name_right_b">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                        <select wire:model.live="stock_id_right_b"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                            <option value="">{{ __("— Select lot —") }}</option>
                            @foreach ($lot_numbers_right_b as $lot)
                                <option value="{{ $lot['id'] }}">{{ $lot['lot_number'] }} (exp: {{ $lot['expiry_date'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Exp Date") }}</label>
                        <input type="date" readonly
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed"
                            wire:model="exp_date_right_b">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (g)") }}</label>
                        <input type="number" readonly step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_target_right_b">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (g)") }}</label>
                        <input id="weight_actual_right_b" type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_actual_right_b">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Percentage (%)") }}</label>
                        <input type="number" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="percentage_right">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 flex justify-end gap-3">
        <button type="button" wire:click="resetForm"
            class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
            {{ __('Reset') }}
        </button>
        <button type="button"
            wire:click="submitForm"
            {{ (!$isAuthenticated || (!$recipe_id_left && !$recipe_id_right)) ? 'disabled' : '' }}
            class="px-4 py-2 rounded-md {{ ($isAuthenticated && ($recipe_id_left || $recipe_id_right)) ? 'bg-caldy-500 hover:bg-caldy-600' : 'bg-gray-400 cursor-not-allowed' }} text-white">
            {{ __('Submit') }}
        </button>
    </div>
</div>
