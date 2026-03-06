<?php

use App\Models\InvCeAuth;
use App\Models\InvCeChemical;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    public string $cookieKey = 'invce_mixing_auth';

    public string $model = '';

    public string $recipe = '';

    public string $area = '';

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

    public string $lot_number_left_a = '';

    public string $exp_date_left_a = '';

    public string $weight_target_left_a = '';

    public string $weight_actual_left_a = '';

    // Left Head - Chemical B
    public string $item_code_left_b = '';

    public string $chemical_name_left_b = '';

    public string $lot_number_left_b = '';

    public string $exp_date_left_b = '';

    public string $weight_target_left_b = '';

    public string $weight_actual_left_b = '';

    public string $percentage_left = '';

    // Right Head - Chemical A
    public string $item_code_right_a = '';

    public string $chemical_name_right_a = '';

    public string $lot_number_right_a = '';

    public string $exp_date_right_a = '';

    public string $weight_target_right_a = '';

    public string $weight_actual_right_a = '';

    // Right Head - Chemical B
    public string $item_code_right_b = '';

    public string $chemical_name_right_b = '';

    public string $lot_number_right_b = '';

    public string $exp_date_right_b = '';

    public string $weight_target_right_b = '';

    public string $weight_actual_right_b = '';

    public string $percentage_right = '';

    // Autocomplete
    public array $chemicalOptionsLeftA = [];

    public array $chemicalOptionsLeftB = [];

    public array $chemicalOptionsRightA = [];

    public array $chemicalOptionsRightB = [];

    public bool $showOptionsLeftA = false;

    public bool $showOptionsLeftB = false;

    public bool $showOptionsRightA = false;

    public bool $showOptionsRightB = false;

    public function searchChemicalLeftA(string $value)
    {
        if (strlen($value) >= 2) {
            $this->chemicalOptionsLeftA = InvCeChemical::where('item_code', 'like', '%'.$value.'%')
                ->where('is_active', true)
                ->limit(10)
                ->get(['id', 'item_code', 'name'])
                ->toArray();
            $this->showOptionsLeftA = count($this->chemicalOptionsLeftA) > 0;
        } else {
            $this->chemicalOptionsLeftA = [];
            $this->showOptionsLeftA = false;
        }
    }

    public function searchChemicalLeftB(string $value)
    {
        if (strlen($value) >= 2) {
            $this->chemicalOptionsLeftB = InvCeChemical::where('item_code', 'like', '%'.$value.'%')
                ->where('is_active', true)
                ->limit(10)
                ->get(['id', 'item_code', 'name'])
                ->toArray();
            $this->showOptionsLeftB = count($this->chemicalOptionsLeftB) > 0;
        } else {
            $this->chemicalOptionsLeftB = [];
            $this->showOptionsLeftB = false;
        }
    }

    public function searchChemicalRightA(string $value)
    {
        if (strlen($value) >= 2) {
            $this->chemicalOptionsRightA = InvCeChemical::where('item_code', 'like', '%'.$value.'%')
                ->where('is_active', true)
                ->limit(10)
                ->get(['id', 'item_code', 'name'])
                ->toArray();
            $this->showOptionsRightA = count($this->chemicalOptionsRightA) > 0;
        } else {
            $this->chemicalOptionsRightA = [];
            $this->showOptionsRightA = false;
        }
    }

    public function searchChemicalRightB(string $value)
    {
        if (strlen($value) >= 2) {
            $this->chemicalOptionsRightB = InvCeChemical::where('item_code', 'like', '%'.$value.'%')
                ->where('is_active', true)
                ->limit(10)
                ->get(['id', 'item_code', 'name'])
                ->toArray();
            $this->showOptionsRightB = count($this->chemicalOptionsRightB) > 0;
        } else {
            $this->chemicalOptionsRightB = [];
            $this->showOptionsRightB = false;
        }
    }

    public function selectChemicalLeftA(string $itemCode, string $name)
    {
        $this->item_code_left_a = $itemCode;
        $this->chemical_name_left_a = $name;
        $this->showOptionsLeftA = false;
        $this->chemicalOptionsLeftA = [];
    }

    public function selectChemicalLeftB(string $itemCode, string $name)
    {
        $this->item_code_left_b = $itemCode;
        $this->chemical_name_left_b = $name;
        $this->showOptionsLeftB = false;
        $this->chemicalOptionsLeftB = [];
    }

    public function selectChemicalRightA(string $itemCode, string $name)
    {
        $this->item_code_right_a = $itemCode;
        $this->chemical_name_right_a = $name;
        $this->showOptionsRightA = false;
        $this->chemicalOptionsRightA = [];
    }

    public function selectChemicalRightB(string $itemCode, string $name)
    {
        $this->item_code_right_b = $itemCode;
        $this->chemical_name_right_b = $name;
        $this->showOptionsRightB = false;
        $this->chemicalOptionsRightB = [];
    }

    public function closeOptionsLeftA()
    {
        $this->showOptionsLeftA = false;
    }

    public function closeOptionsLeftB()
    {
        $this->showOptionsLeftB = false;
    }

    public function closeOptionsRightA()
    {
        $this->showOptionsRightA = false;
    }

    public function closeOptionsRightB()
    {
        $this->showOptionsRightB = false;
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

    // resret form
    public function resetForm()
    {
        $this->model = '';
        $this->recipe = '';
        $this->area = '';
        $this->device_name = '';

        // Left Head - Chemical A
        $this->item_code_left_a = '';
        $this->chemical_name_left_a = '';
        $this->lot_number_left_a = '';
        $this->exp_date_left_a = '';
        $this->weight_target_left_a = '';
        $this->weight_actual_left_a = '';

        // Left Head - Chemical B
        $this->item_code_left_b = '';
        $this->chemical_name_left_b = '';
        $this->lot_number_left_b = '';
        $this->exp_date_left_b = '';
        $this->weight_target_left_b = '';
        $this->weight_actual_left_b = '';

        $this->percentage_left = '';

        // Right Head - Chemical A
        $this->item_code_right_a = '';
        $this->chemical_name_right_a = '';
        $this->lot_number_right_a = '';
        $this->exp_date_right_a = '';
        $this->weight_target_right_a = '';
        $this->weight_actual_right_a = '';

        // Right Head - Chemical B
        $this->item_code_right_b = '';
        $this->chemical_name_right_b = '';
        $this->lot_number_right_b = '';
        $this->exp_date_right_b = '';
        $this->weight_target_right_b = '';
        $this->weight_actual_right_b = '';

        $this->percentage_right = '';

        // Autocomplete
        $this->chemicalOptionsLeftA = [];
        $this->chemicalOptionsLeftB = [];
        $this->chemicalOptionsRightA = [];
        $this->chemicalOptionsRightB = [];
        $this->showOptionsLeftA = false;
        $this->showOptionsLeftB = false;
        $this->showOptionsRightA = false;
        $this->showOptionsRightB = false;
    }

    public function submitForm(): void
    {
        if (! $this->isAuthenticated) {
            return;
        }

        // Store form data in session for process-timer page
        session([
            'mixing_data' => [
                'model' => $this->model,
                'recipe' => $this->recipe,
                'area' => $this->area,
                'device_name' => $this->device_name,
                'operator' => $this->auth['name'],
                'left' => [
                    'chemical_a' => [
                        'item_code' => $this->item_code_left_a,
                        'chemical_name' => $this->chemical_name_left_a,
                        'lot_number' => $this->lot_number_left_a,
                        'exp_date' => $this->exp_date_left_a,
                        'weight_target' => $this->weight_target_left_a,
                        'weight_actual' => $this->weight_actual_left_a,
                    ],
                    'chemical_b' => [
                        'item_code' => $this->item_code_left_b,
                        'chemical_name' => $this->chemical_name_left_b,
                        'lot_number' => $this->lot_number_left_b,
                        'exp_date' => $this->exp_date_left_b,
                        'weight_target' => $this->weight_target_left_b,
                        'weight_actual' => $this->weight_actual_left_b,
                    ],
                    'percentage' => $this->percentage_left,
                ],
                'right' => [
                    'chemical_a' => [
                        'item_code' => $this->item_code_right_a,
                        'chemical_name' => $this->chemical_name_right_a,
                        'lot_number' => $this->lot_number_right_a,
                        'exp_date' => $this->exp_date_right_a,
                        'weight_target' => $this->weight_target_right_a,
                        'weight_actual' => $this->weight_actual_right_a,
                    ],
                    'chemical_b' => [
                        'item_code' => $this->item_code_right_b,
                        'chemical_name' => $this->chemical_name_right_b,
                        'lot_number' => $this->lot_number_right_b,
                        'exp_date' => $this->exp_date_right_b,
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
        <!-- RFID Status Alert -->
        @if($rfidError)
        <div class="mb-4 p-4 border rounded bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-200">
            {{ $rfidError }}
        </div>
        @endif

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
        >
            <template x-if="auth && auth.status === 'found'">
                <div class="mb-4 p-4 border rounded bg-green-50 text-green-700 dark:bg-green-900 dark:text-green-200">
                    RFID <span class="font-mono" x-text="auth.rf_code"></span> - <span x-text="auth.name"></span> (Authorized)
                </div>
            </template>
            <template x-if="auth && auth.status === 'not_found'">
                <div class="mb-4 p-4 border rounded bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-200">
                    RFID <span class="font-mono" x-text="auth.rf_code"></span> - Tidak terdaftar
                </div>
            </template>
        </div>

        <div
            wire:ignore
            x-data="{
                storageKey: 'invce_mixing_last_rfid',
                url: @js(config('rfid.ws_url')),
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
            class="mb-4"
        >
            <div class="flex items-center gap-2 text-sm">
                <span>RFID:</span>
                <span :class="connected ? 'text-green-500' : 'text-red-500'" x-text="connected ? 'Connected' : 'Not connected'"></span>
                <span class="text-xs opacity-70" x-show="url">(<span x-text="url"></span>)</span>
                <span x-show="error" class="text-red-500 text-xs" x-text="error"></span>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Operator") }}</label>
                    @if($isAuthenticated)
                    <span class="px-3 py-1 bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-300 rounded-md">{{ $auth['name'] }}</span>
                    @else
                    <span class="px-3 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-md text-red-500">{{ __("Tap ID Card")}}</span>
                    @endif
                </div>
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Plant") }}</label>
                    @if($isAuthenticated)
                        <span class="px-3 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-md">Plant {{ $auth['area'] }}</span>
                    @else
                        <span class="px-3 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-md text-red-500">{{ __("Tap ID Card")}}</span>
                    @endif
                </div>
                <div class="border-l border-neutral-300 dark:border-neutral-700"></div>
            </div>
            <div class="flex gap-3">
                <!-- MODEL OPTION -->
                <div class="flex-1">
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Model") }}</label>
                    <select class="w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500" wire:model="model">
                        <option value="" disabled>{{ __("Select model") }}</option>
                        <option value="model_a">Model A</option>
                        <option value="model_b">Model B</option>
                        <option value="model_c">Model C</option>
                    </select>
                </div>
                <!-- START TIME -->
                <div class="flex-1">
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Recipe") }}</label>
                    <select class="w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500" wire:model="recipe">
                        <option value="" disabled>{{ __("Select recipe") }}</option>
                        <option value="recipe_a">Recipe A</option>
                        <option value="recipe_b">Recipe B</option>
                        <option value="recipe_c">Recipe C</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Area") }}</label>
                    <select id="area" class="w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500" wire:model="area">
                        <option value="" disabled>{{ __("Select area") }}</option>
                        <option value="area_a">Area A</option>
                        <option value="area_b">Area B</option>
                        <option value="area_c">Area C</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Chemical Base") }}</label>
                    <select id="chemical_base" class="w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500" wire:model="chemical_base">
                        <option value="" disabled>{{ __("Select chemical base") }}</option>
                        <option value="base_a">Watter Base</option>
                        <option value="base_b">Solvent Base</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- form -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Left Head -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 border-l-4 border-l-blue-500">
            <div class="text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="px-2 py-1 bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-300 text-xs font-semibold rounded">{{ __("LEFT HEAD") }}</span>
            </div>
            
            <!-- Left Head - Chemical A -->
            <div class="mb-6 pb-6 border-b border-neutral-200 dark:border-neutral-700">
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical A") }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="relative">
                        <label for="item_code_left_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                        <input type="text" id="item_code_left_a"
                            placeholder="{{ __('Type to search...') }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="item_code_left_a"
                            wire:input="searchChemicalLeftA($event.target.value)"
                            autocomplete="off">
                        @if($showOptionsLeftA)
                        <div class="absolute z-10 w-full bg-white dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-lg mt-1 max-h-48 overflow-y-auto">
                            @foreach($chemicalOptionsLeftA as $chemical)
                            <button type="button"
                                class="w-full text-left px-4 py-2 hover:bg-caldy-500 hover:text-white text-neutral-800 dark:text-neutral-200 dark:hover:bg-caldy-500"
                                wire:click="selectChemicalLeftA('{{ $chemical['item_code'] }}', '{{ $chemical['name'] }}')">
                                <div class="font-medium">{{ $chemical['item_code'] }}</div>
                                <div class="text-sm text-neutral-500 dark:text-neutral-400">{{ $chemical['name'] }}</div>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div>
                        <label for="chemical_name_left_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Chemical Name") }}</label>
                        <input type="text" id="chemical_name_left_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                        wire:model="chemical_name_left_a" readonly>
                    </div>
                    <div>
                        <label for="lot_number_left_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                        <input type="text" id="lot_number_left_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="lot_number_left_a">
                    </div>
                    <div>
                        <label for="exp_date_left_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Exp Date") }}</label>
                        <input type="date" id="exp_date_left_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="exp_date_left_a">
                    </div>
                    <div>
                        <label for="weight_target_left_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_target_left_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_target_left_a">
                    </div>
                    <div>
                        <label for="weight_actual_left_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_actual_left_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_actual_left_a">
                    </div>
                </div>
            </div>

            <!-- Left Head - Chemical B -->
            <div>
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical B") }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="relative">
                        <label for="item_code_left_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                        <input type="text" id="item_code_left_b"
                            placeholder="{{ __('Type to search...') }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="item_code_left_b"
                            wire:input="searchChemicalLeftB($event.target.value)"
                            autocomplete="off">
                        @if($showOptionsLeftB)
                        <div class="absolute z-10 w-full bg-white dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-lg mt-1 max-h-48 overflow-y-auto">
                            @foreach($chemicalOptionsLeftB as $chemical)
                            <button type="button"
                                class="w-full text-left px-4 py-2 hover:bg-caldy-500 hover:text-white text-neutral-800 dark:text-neutral-200 dark:hover:bg-caldy-500"
                                wire:click="selectChemicalLeftB('{{ $chemical['item_code'] }}', '{{ $chemical['name'] }}')">
                                <div class="font-medium">{{ $chemical['item_code'] }}</div>
                                <div class="text-sm text-neutral-500 dark:text-neutral-400">{{ $chemical['name'] }}</div>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div>
                        <label for="chemical_name_left_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Chemical Name") }}</label>
                        <input type="text" id="chemical_name_left_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                        wire:model="chemical_name_left_b" readonly>
                    </div>
                    <div>
                        <label for="lot_number_left_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                        <input type="text" id="lot_number_left_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="lot_number_left_b">
                    </div>
                    <div>
                        <label for="exp_date_left_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Exp Date") }}</label>
                        <input type="date" id="exp_date_left_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="exp_date_left_b">
                    </div>
                    <div>
                        <label for="weight_target_left_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_target_left_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_target_left_b">
                    </div>
                    <div>
                        <label for="weight_actual_left_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_actual_left_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_actual_left_b">
                    </div>
                    <div>
                        <label for="percentage_left" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Percentage (%)") }}</label>
                        <input type="number" step="0.01" id="percentage_left" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
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
            
            <!-- Right Head - Chemical A -->
            <div class="mb-6 pb-6 border-b border-neutral-200 dark:border-neutral-700">
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical A") }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="relative">
                        <label for="item_code_right_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                        <input type="text" id="item_code_right_a"
                            placeholder="{{ __('Type to search...') }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="item_code_right_a"
                            wire:input="searchChemicalRightA($event.target.value)"
                            autocomplete="off">
                        @if($showOptionsRightA)
                        <div class="absolute z-10 w-full bg-white dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-lg mt-1 max-h-48 overflow-y-auto">
                            @foreach($chemicalOptionsRightA as $chemical)
                            <button type="button"
                                class="w-full text-left px-4 py-2 hover:bg-caldy-500 hover:text-white text-neutral-800 dark:text-neutral-200 dark:hover:bg-caldy-500"
                                wire:click="selectChemicalRightA('{{ $chemical['item_code'] }}', '{{ $chemical['name'] }}')">
                                <div class="font-medium">{{ $chemical['item_code'] }}</div>
                                <div class="text-sm text-neutral-500 dark:text-neutral-400">{{ $chemical['name'] }}</div>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div>
                        <label for="chemical_name_right_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Chemical Name") }}</label>
                        <input type="text" id="chemical_name_right_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                        wire:model="chemical_name_right_a" readonly>
                    </div>
                    <div>
                        <label for="lot_number_right_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                        <input type="text" id="lot_number_right_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="lot_number_right_a">
                    </div>
                    <div>
                        <label for="exp_date_right_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Exp Date") }}</label>
                        <input type="date" id="exp_date_right_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="exp_date_right_a">
                    </div>
                    <div>
                        <label for="weight_target_right_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_target_right_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_target_right_a">
                    </div>
                    <div>
                        <label for="weight_actual_right_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_actual_right_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_actual_right_a">
                    </div>
                </div>
            </div>

            <!-- Right Head - Chemical B -->
            <div>
                <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical B") }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="relative">
                        <label for="item_code_right_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                        <input type="text" id="item_code_right_b"
                            placeholder="{{ __('Type to search...') }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="item_code_right_b"
                            wire:input="searchChemicalRightB($event.target.value)"
                            autocomplete="off">
                        @if($showOptionsRightB)
                        <div class="absolute z-10 w-full bg-white dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-lg mt-1 max-h-48 overflow-y-auto">
                            @foreach($chemicalOptionsRightB as $chemical)
                            <button type="button"
                                class="w-full text-left px-4 py-2 hover:bg-caldy-500 hover:text-white text-neutral-800 dark:text-neutral-200 dark:hover:bg-caldy-500"
                                wire:click="selectChemicalRightB('{{ $chemical['item_code'] }}', '{{ $chemical['name'] }}')">
                                <div class="font-medium">{{ $chemical['item_code'] }}</div>
                                <div class="text-sm text-neutral-500 dark:text-neutral-400">{{ $chemical['name'] }}</div>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div>
                        <label for="chemical_name_right_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Chemical Name") }}</label>
                        <input type="text" id="chemical_name_right_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                        wire:model="chemical_name_right_b" readonly>
                    </div>
                    <div>
                        <label for="lot_number_right_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                        <input type="text" id="lot_number_right_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="lot_number_right_b">
                    </div>
                    <div>
                        <label for="exp_date_right_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Exp Date") }}</label>
                        <input type="date" id="exp_date_right_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="exp_date_right_b">
                    </div>
                    <div>
                        <label for="weight_target_right_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_target_right_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_target_right_b">
                    </div>
                    <div>
                        <label for="weight_actual_right_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_actual_right_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_actual_right_b">
                    </div>
                    <div>
                        <label for="percentage_right" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Percentage (%)") }}</label>
                        <input type="number" step="0.01" id="percentage_right" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="percentage_right">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-4 flex justify-end gap-3">
        <button type="button" wire:click="resetForm" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
            {{ __('Reset') }}
        </button>
        <button type="button" 
            wire:click="submitForm" 
            {{ !$isAuthenticated ? 'disabled' : '' }}
            class="px-4 py-2 rounded-md {{ $isAuthenticated ? 'bg-caldy-500 hover:bg-caldy-600' : 'bg-gray-400 cursor-not-allowed' }} text-white">
            {{ __('Submit') }}
        </button>
    </div>
</div>

