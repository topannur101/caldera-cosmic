<?php

use App\Models\InvCeArea;
use App\Models\InvCeAuth;
use App\Models\InvCeChemical;
use App\Models\InvCeLocation;
use App\Models\InvCeStock;
use App\Models\InvCeVendor;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout("layouts.app")] class extends Component {
    public string $cookieKey = 'invce_chemical_create_auth';

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

    public string $item_code = "";

    public string $name = "";

    public int $inv_ce_vendor_id = 0;

    public string $uom = "";

    public string $category_chemical = "single";

    public ?int $location_id = null;

    public ?int $area_id = null;

    public int $quantity = 0;

    public string $unit_size = "0";

    public string $unit_uom = "0";

    public string $lot_number = "0";

    public string $unit_price = "0";

    public string $expiry_date = "";

    public string $planning_area = "";

    public string $status = "pending";

    public string $remarks = "";

    public array $vendors = [];

    public array $locations = [];

    public array $areas = [];

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

    public function mount()
    {
        $this->vendors = InvCeVendor::where("is_active", "1")
            ->orderBy("name")
            ->get(["id", "name"])
            ->toArray();

        $this->locations = InvCeLocation::query()
            ->where("is_active", true)
            ->orderBy("parent")
            ->orderBy("bin")
            ->get(["id", "parent", "bin"])
            ->toArray();

        $this->areas = InvCeArea::query()
            ->where("is_active", true)
            ->orderBy("name")
            ->get(["id", "name"])
            ->toArray();

        if (! empty($this->vendors)) {
            $this->inv_ce_vendor_id = (int) $this->vendors[0]["id"];
        }

        if (! empty($this->areas)) {
            $this->area_id = (int) $this->areas[0]["id"];
        }

        $this->expiry_date = now()->addMonths(6)->format("Y-m-d");
    }

    public function rules(): array
    {
        return [
            "item_code" => ["required", "string", "max:255", "unique:inv_ce_chemicals,item_code"],
            "name" => ["required", "string", "max:255"],
            "inv_ce_vendor_id" => ["required", "integer", "exists:inv_ce_vendors,id"],
            "uom" => ["required", "string", "max:100"],
            "category_chemical" => ["required", "in:single,double"],
            "location_id" => ["nullable", "integer", "exists:inv_ce_locations,id"],
            "area_id" => ["nullable", "integer", "exists:inv_ce_areas,id"],
            "quantity" => ["required", "integer", "min:0"],
            "unit_size" => ["required", "numeric", "min:0"],
            "unit_uom" => ["required", "string", "max:100"],
            "lot_number" => ["required", "numeric", "min:0"],
            "unit_price" => ["required", "numeric", "min:0"],
            "expiry_date" => ["required", "date"],
            "planning_area" => ["required", "string", "max:1000"],
            "status" => ["required", "in:pending,approved,rejected,returned,expired"],
            "remarks" => ["nullable", "string", "max:255"],
        ];
    }

    private function parsePlanningArea(string $value): array
    {
        return array_values(array_filter(array_unique(array_map(
            fn($part) => trim($part),
            explode(",", $value)
        ))));
    }

    public function save()
    {
        if (! $this->isAuthenticated) {
            return;
        }

        $validated = $this->validate();
        $planningArea = $this->parsePlanningArea($validated["planning_area"]);

        if (! count($planningArea)) {
            $this->addError("planning_area", __("Minimal satu planning area wajib diisi."));
            return;
        }

        DB::transaction(function () use ($validated, $planningArea) {
            $chemical = InvCeChemical::create([
                "item_code" => trim($validated["item_code"]),
                "name" => trim($validated["name"]),
                "inv_ce_vendor_id" => (int) $validated["inv_ce_vendor_id"],
                "uom" => trim($validated["uom"]),
                "category_chemical" => $validated["category_chemical"],
                "photo" => null,
                "location_id" => $validated["location_id"] ?: null,
                "area_id" => $validated["area_id"] ?: null,
                "is_active" => true,
            ]);

            InvCeStock::create([
                "inv_ce_chemical_id" => $chemical->id,
                "quantity" => (int) $validated["quantity"],
                "unit_size" => (float) $validated["unit_size"],
                "unit_uom" => trim($validated["unit_uom"]),
                "lot_number" => (float) $validated["lot_number"],
                "unit_price" => (float) $validated["unit_price"],
                "expiry_date" => $validated["expiry_date"],
                "planning_area" => json_encode($planningArea),
                "status" => $validated["status"],
                "remarks" => trim($validated["remarks"]) ?: null,
            ]);
        });

        return $this->redirect(route("insights.ce.inventory.chemicals.index"), navigate: true);
    }
}; ?>

<x-slot name="title">{{ __("Bahan kimia baru") . " — " . __("Inventaris CE") }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-ce></x-nav-inventory-ce>
</x-slot>

<div class="py-12 max-w-4xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <!-- RFID Status Alert -->
    @if($rfidError)
    <div class="mb-4 px-4 sm:px-0 p-4 border rounded bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-200">
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
        x-on:rfid-result.window="auth = $event.detail"
        class="px-4 sm:px-0 mb-4"
    >
        <template x-if="auth && auth.status === 'found'">
            <div class="p-4 border rounded bg-green-50 text-green-700 dark:bg-green-900 dark:text-green-200">
                RFID <span class="font-mono" x-text="auth.rf_code"></span> - <span x-text="auth.name"></span> (Authorized)
            </div>
        </template>
        <template x-if="auth && auth.status === 'not_found'">
            <div class="p-4 border rounded bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-200">
                RFID <span class="font-mono" x-text="auth.rf_code"></span> - Tidak terdaftar
            </div>
        </template>
        <template x-if="!auth || auth.status === ''">
            <div class="p-4 border rounded bg-yellow-50 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-200">
                {{ __("Tap ID Card untuk autentikasi") }}
            </div>
        </template>
    </div>

    <div
        wire:ignore
        x-data="{
            storageKey: 'invce_chemical_create_last_rfid',
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
        x-init="connect(); loadSavedCode()"
        class="px-4 sm:px-0 mb-6"
    >
        <div class="flex items-center gap-2 text-sm">
            <span>RFID:</span>
            <span :class="connected ? 'text-green-500' : 'text-red-500'" x-text="connected ? 'Connected' : 'Not connected'"></span>
            <span class="text-xs opacity-70" x-show="url">(<span x-text="url"></span>)</span>
            <span x-show="error" class="text-red-500 text-xs" x-text="error"></span>
        </div>
    </div>

    <div class="px-4 sm:px-0 mb-8 grid grid-cols-1 gap-y-4">
        <div
            class="flex items-center justify-between gap-x-4 p-4 text-sm text-neutral-800 border border-neutral-300 rounded-lg bg-neutral-50 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-600"
            role="alert"
        >
            <div>
                {{ __("Klik simpan jika sudah selesai melengkapi informasi bahan kimia") }}
                @if(!$isAuthenticated)
                    <span class="ml-2 text-red-500 text-xs">{{ __("(Tap ID Card untuk autentikasi)") }}</span>
                @endif
            </div>

            <!-- login as user -->
             @if($isAuthenticated)
                <div class="flex items-center gap-x-2 text-xs">
                    <span class="text-neutral-500">{{ __("Login sebagai") }}</span>
                    <span class="text-neutral-800 dark:text-neutral-300">{{ $auth['name'] ?? "-" }}</span>
                </div>
            @endif
            <div>
                <div wire:loading>
                    <x-primary-button type="button" disabled><i class="icon-save mr-2"></i>{{ __("Simpan") }}</x-primary-button>
                </div>
                <div wire:loading.remove>
                    <x-primary-button type="submit" form="ce-chemical-create-form" :disabled="!$isAuthenticated"><i class="icon-save mr-2"></i>{{ __("Simpan") }}</x-primary-button>
                </div>
            </div>
        </div>
        @if ($errors->any())
            <div class="text-center">
                <x-input-error :messages="$errors->first()" />
            </div>
        @endif
    </div>

    <form id="ce-chemical-create-form" wire:submit="save">
        <div class="block sm:flex gap-x-6">
            <div class="sm:w-72">
                <div class="sticky top-5 left-0 space-y-6">
                    <div class="bg-neutral-200 dark:bg-neutral-800 rounded-lg aspect-square flex items-center justify-center">
                        <i class="icon-box text-7xl text-neutral-400"></i>
                    </div>

                    <div class="px-2 text-sm text-neutral-600 dark:text-neutral-400">
                        <x-text-button type="button">
                            <i class="icon-upload mr-2"></i>{{ __("Unggah foto") }}
                        </x-text-button>
                    </div>

                    <div class="grid grid-cols-1 divide-y divide-neutral-200 dark:divide-neutral-800 px-2 text-sm">
                        <div class="flex items-center gap-x-2 py-3">
                            <i class="text-neutral-500 icon-building"></i>
                            <x-select id="inv_ce_vendor_id" wire:model="inv_ce_vendor_id" class="w-full">
                                <option value="0">{{ __("Pilih vendor") }}</option>
                                @foreach ($vendors as $vendor)
                                    <option value="{{ $vendor["id"] }}">{{ $vendor["name"] }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        @error("inv_ce_vendor_id")
                            <x-input-error messages="{{ $message }}" class="pb-2" />
                        @enderror

                        <div class="flex items-center gap-x-2 py-3">
                            <i class="text-neutral-500 icon-layers"></i>
                            <x-select id="category_chemical" wire:model="category_chemical" class="w-full">
                                <option value="single">{{ __("Single") }}</option>
                                <option value="double">{{ __("Double") }}</option>
                            </x-select>
                        </div>
                        @error("category_chemical")
                            <x-input-error messages="{{ $message }}" class="pb-2" />
                        @enderror

                        <div class="flex items-center gap-x-2 py-3">
                            <i class="text-neutral-500 icon-flask-conical"></i>
                            <x-text-input id="uom" wire:model.blur="uom" type="text" placeholder="Masukan Uom" />
                            @error("uom")
                                <x-input-error messages="{{ $message }}" class="pb-2" />
                            @enderror
                        </div>
                        @error("uom")
                            <x-input-error messages="{{ $message }}" class="pb-2" />
                        @enderror
                    </div>
                </div>
            </div>

            <div class="grow space-y-6 mt-6 sm:mt-0">
                <div class="relative bg-white dark:bg-neutral-800 shadow rounded-none sm:rounded-lg divide-y divide-neutral-200 dark:divide-neutral-700">
                    <div class="grid gap-y-4 py-6">
                        <div class="px-6">
                            <label for="name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Nama") }}</label>
                            <x-text-input id="name" wire:model.blur="name" type="text" />
                            @error("name")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                        <div class="px-6">
                            <label for="remarks" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Deskripsi") }}</label>
                            <x-text-input id="remarks" wire:model.blur="remarks" type="text" />
                            @error("remarks")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-1 gap-y-4">
                        <div>
                            <label for="item_code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Kode") }}</label>
                            <x-text-input id="item_code" wire:model.blur="item_code" type="text" />
                            @error("item_code")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <label for="location_id" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Lokasi") }}</label>
                                <x-select id="location_id" wire:model="location_id" class="w-full">
                                    <option value="">{{ __("Tanpa lokasi") }}</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location["id"] }}">{{ $location["parent"] . " - " . $location["bin"] }}</option>
                                    @endforeach
                                </x-select>
                                @error("location_id")
                                    <x-input-error messages="{{ $message }}" class="mt-2" />
                                @enderror
                            </div>

                            <div>
                                <label for="area_id" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Tag area") }}</label>
                                <x-select id="area_id" wire:model="area_id" class="w-full">
                                    <option value="">{{ __("Tanpa area") }}</option>
                                    @foreach ($areas as $area)
                                        <option value="{{ $area["id"] }}">{{ $area["name"] }}</option>
                                    @endforeach
                                </x-select>
                                @error("area_id")
                                    <x-input-error messages="{{ $message }}" class="mt-2" />
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-neutral-800 shadow rounded-none sm:rounded-lg p-6">
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Unit stok") }}</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="quantity" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Qty awal") }}</label>
                            <x-text-input id="quantity" wire:model.blur="quantity" type="number" min="0" />
                            @error("quantity")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                        <div>
                            <label for="unit_size" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Ukuran unit") }}</label>
                            <x-text-input id="unit_size" wire:model.blur="unit_size" type="number" min="0" step="0.01" />
                            @error("unit_size")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                        <div>
                            <label for="unit_uom" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Unit UOM") }}</label>
                            <x-text-input id="unit_uom" wire:model.blur="unit_uom" type="text" />
                            @error("unit_uom")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                        <div>
                            <label for="lot_number" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Lot number") }}</label>
                            <x-text-input id="lot_number" wire:model.blur="lot_number" type="number" min="0" step="0.01" />
                            @error("lot_number")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                        <div>
                            <label for="unit_price" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Harga per unit") }}</label>
                            <x-text-input id="unit_price" wire:model.blur="unit_price" type="number" min="0" step="0.01" />
                            @error("unit_price")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                        <div>
                            <label for="expiry_date" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Tanggal kedaluwarsa") }}</label>
                            <x-text-input id="expiry_date" wire:model.blur="expiry_date" type="date" />
                            @error("expiry_date")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                        <div>
                            <label for="status" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Status") }}</label>
                            <x-select id="status" wire:model="status" class="w-full">
                                <option value="pending">{{ __("Pending") }}</option>
                                <option value="approved">{{ __("Approved") }}</option>
                                <option value="rejected">{{ __("Rejected") }}</option>
                                <option value="returned">{{ __("Returned") }}</option>
                                <option value="expired">{{ __("Expired") }}</option>
                            </x-select>
                            @error("status")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 mt-4">
                        <div>
                            <label for="planning_area" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Planning area (pisahkan dengan koma)") }}</label>
                            <x-text-input id="planning_area" wire:model.blur="planning_area" type="text" placeholder="Mixing, Warehouse A" />
                            @error("planning_area")
                                <x-input-error messages="{{ $message }}" class="mt-2" />
                            @enderror
                        </div>
                    </div>

                    <div class="px-3 mt-4">
                        <x-text-button type="button" disabled class="rounded-full border border-neutral-300 dark:border-neutral-700 px-3 py-1">
                            <i class="icon-plus mr-2"></i>{{ __("Tambah unit") }}
                        </x-text-button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
