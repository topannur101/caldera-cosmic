<?php

use App\Models\InvCeChemical;
use App\Models\InvCeAuth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')]
class extends Component {
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

    // Chemical A
    public string $item_code_a = '';
    public string $chemical_name_a = '';
    public string $lot_number_a = '';
    public string $exp_date_a = '';
    public string $weight_target_a = '';
    public string $weight_actual_a = '';

    // Chemical B
    public string $item_code_b = '';
    public string $chemical_name_b = '';
    public string $lot_number_b = '';
    public string $exp_date_b = '';
    public string $weight_target_b = '';
    public string $weight_actual_b = '';
    public string $percentage = '';

    // Autocomplete
    public array $chemicalOptionsA = [];
    public array $chemicalOptionsB = [];
    public bool $showOptionsA = false;
    public bool $showOptionsB = false;

    public function searchChemicalA(string $value)
    {
        if (strlen($value) >= 2) {
            $this->chemicalOptionsA = InvCeChemical::where('item_code', 'like', '%' . $value . '%')
                ->where('is_active', true)
                ->limit(10)
                ->get(['id', 'item_code', 'name'])
                ->toArray();
            $this->showOptionsA = count($this->chemicalOptionsA) > 0;
        } else {
            $this->chemicalOptionsA = [];
            $this->showOptionsA = false;
        }
    }

    public function searchChemicalB(string $value)
    {
        if (strlen($value) >= 2) {
            $this->chemicalOptionsB = InvCeChemical::where('item_code', 'like', '%' . $value . '%')
                ->where('is_active', true)
                ->limit(10)
                ->get(['id', 'item_code', 'name'])
                ->toArray();
            $this->showOptionsB = count($this->chemicalOptionsB) > 0;
        } else {
            $this->chemicalOptionsB = [];
            $this->showOptionsB = false;
        }
    }

    public function selectChemicalA(string $itemCode, string $name)
    {
        $this->item_code_a = $itemCode;
        $this->chemical_name_a = $name;
        $this->showOptionsA = false;
        $this->chemicalOptionsA = [];
    }

    public function selectChemicalB(string $itemCode, string $name)
    {
        $this->item_code_b = $itemCode;
        $this->chemical_name_b = $name;
        $this->showOptionsB = false;
        $this->chemicalOptionsB = [];
    }

    public function closeOptionsA()
    {
        $this->showOptionsA = false;
    }

    public function closeOptionsB()
    {
        $this->showOptionsB = false;
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
                'status'        => 'found',
                'rf_code'       => $authRfid->rf_code,
                'name'          => $authUser->name,
                'emp_id'        => $authUser->emp_id,
                'is_active'     => (int) ($authUser->is_active ?? 0),
                'area'          => $authRfid->area,
                'resource_type' => $authRfid->resource_type,
                'resource_id'   => $authRfid->resource_id,
            ];
            $this->isAuthenticated = true;
            Cookie::queue($this->cookieKey, json_encode($this->auth), 60 * 24);
        } else {
            $this->auth = [
                'status'   => 'not_found',
                'rf_code'  => $code,
                'name'     => '',
                'emp_id'   => '',
                'is_active' => 0,
                'area'     => '',
                'resource_type' => '',
                'resource_id' => 0,
            ];
            $this->isAuthenticated = false;
            $this->rfidError = 'RFID tidak terdaftar';
            Cookie::queue($this->cookieKey, json_encode($this->auth), 60 * 24);
        }
    }

    // resret form
    public function resetForm(){
        $this->model = '';
        $this->recipe = '';
        $this->area = '';

        // Chemical A
        $this->item_code_a = '';
        $this->chemical_name_a = '';
        $this->lot_number_a = '';
        $this->exp_date_a = '';
        $this->weight_target_a = '';
        $this->weight_actual_a = '';

        // Chemical B
        $this->item_code_b = '';
        $this->chemical_name_b = '';
        $this->lot_number_b = '';
        $this->exp_date_b = '';
        $this->weight_target_b = '';
        $this->weight_actual_b = '';

        // Autocomplete
        $this->chemicalOptionsA = [];
        $this->chemicalOptionsB = [];
        $this->showOptionsA = false;
        $this->showOptionsB = false;
    }

    public function submitForm(): void
    {
        if (!$this->isAuthenticated) {
            dd('Unauthorized');
        }
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
        <!-- Chemical A -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-lg font-semibold mb-4">{{ __("Chemical A") }}</div>
            <div class="grid gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="relative">
                        <label for="item_code_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                        <input type="text" id="item_code_a"
                            placeholder="{{ __('Type to search...') }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="item_code_a"
                            wire:input="searchChemicalA($event.target.value)"
                            autocomplete="off">
                        @if($showOptionsA)
                        <div class="absolute z-10 w-full bg-white dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-lg mt-1 max-h-48 overflow-y-auto">
                            @foreach($chemicalOptionsA as $chemical)
                            <button type="button"
                                class="w-full text-left px-4 py-2 hover:bg-caldy-500 hover:text-white text-neutral-800 dark:text-neutral-200 dark:hover:bg-caldy-500"
                                wire:click="selectChemicalA('{{ $chemical['item_code'] }}', '{{ $chemical['name'] }}')">
                                <div class="font-medium">{{ $chemical['item_code'] }}</div>
                                <div class="text-sm text-neutral-500 dark:text-neutral-400">{{ $chemical['name'] }}</div>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div>
                        <label for="chemical_name_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Chemical Name") }}</label>
                        <input type="text" id="chemical_name_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                        wire:model="chemical_name_a" readonly>
                    </div>
                    <div>
                        <label for="lot_number_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                        <input type="text" id="lot_number_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="lot_number_a">
                    </div>
                    <div>
                        <label for="exp_date_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Exp Date") }}</label>
                        <input type="date" id="exp_date_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="exp_date_a">
                    </div>
                    <div>
                        <label for="weight_target_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_target_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_target_a">
                    </div>
                    <div>
                        <label for="weight_actual_a" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_actual_a" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_actual_a">
                    </div>
                </div>
            </div>
        </div>

        <!-- Chemical B -->
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div class="text-lg font-semibold mb-4">{{ __("Chemical B") }}</div>
            <div class="grid gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="relative">
                        <label for="item_code_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                        <input type="text" id="item_code_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="item_code_b"
                            wire:input="searchChemicalB($event.target.value)"
                            autocomplete="off">
                        @if($showOptionsB)
                        <div class="absolute z-10 w-full bg-white dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-lg mt-1 max-h-48 overflow-y-auto">
                            @foreach($chemicalOptionsB as $chemical)
                            <button type="button"
                                class="w-full text-left px-4 py-2 hover:bg-caldy-500 hover:text-white text-neutral-800 dark:text-neutral-200 dark:hover:bg-caldy-500"
                                wire:click="selectChemicalB('{{ $chemical['item_code'] }}', '{{ $chemical['name'] }}')">
                                <div class="font-medium">{{ $chemical['item_code'] }}</div>
                                <div class="text-sm text-neutral-500 dark:text-neutral-400">{{ $chemical['name'] }}</div>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div>
                        <label for="chemical_name_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Chemical Name") }}</label>
                        <input type="text" id="chemical_name_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="chemical_name_b" readonly>
                    </div>
                    <div>
                        <label for="lot_number_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                        <input type="text" id="lot_number_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="lot_number_b">
                    </div>
                    <div>
                        <label for="exp_date_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Exp Date") }}</label>
                        <input type="date" id="exp_date_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="exp_date_b">
                    </div>
                    <div>
                        <label for="weight_target_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_target_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_target_b">
                    </div>
                    <div>
                        <label for="weight_actual_b" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (kg)") }}</label>
                        <input type="number" step="0.01" id="weight_actual_b" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="weight_actual_b">
                    </div>
                    <!-- percentage -->
                    <div>
                        <label for="percentage" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Percentage (%)") }}</label>
                        <input type="number" step="0.01" id="percentage" 
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500"
                            wire:model="percentage">
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

