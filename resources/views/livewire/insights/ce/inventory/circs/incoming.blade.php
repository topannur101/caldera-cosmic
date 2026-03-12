<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Models\User;
use App\Models\InvCeAuth;


new #[Layout("layouts.app")] class extends Component {
    public string $cookieKey = 'invce_incoming_auth';
    public array $circs = [];
    public array $auth = [
        'status' => '',
        'rf_code' => '',
        'name' => '',
        'emp_id' => '',
        'is_active' => 0,
        'area' => '',
        'resource_type' => '',
        'resource_id' => 0,
        'saved_at' => '',
    ];

    public function mount(): void {}

    public function searchTTCode(string $code): void
    {
        $code = trim($code);

        if ($code === '') {
            Cookie::queue(Cookie::forget($this->cookieKey));
            $this->dispatch('rfid-result', status: 'cleared');
            return;
        }

        $authRfid = InvCeAuth::query()
            ->with('user')
            ->where('rf_code', $code)
            ->first();

        $authUser = $authRfid?->user;

        if ($authUser) {
            $payload = [
                'status'        => 'found',
                'rf_code'       => $authRfid->rf_code,
                'user_id'       => $authUser->id,
                'name'          => $authUser->name,
                'emp_id'        => $authUser->emp_id,
                'is_active'     => (int) ($authUser->is_active ?? 0),
                'area'          => $authRfid->area,
                'resource_type' => $authRfid->resource_type,
                'resource_id'   => $authRfid->resource_id,
                'saved_at'      => now()->toIso8601String(),
            ];

            $this->auth = $payload;
        } else {
            $payload = [
                'status'   => 'not_found',
                'rf_code'  => $code,
                'saved_at' => now()->toIso8601String(),
            ];

            $this->auth = $payload;
        }

        Cookie::queue($this->cookieKey, json_encode($payload), 60 * 24);
        $this->dispatch('rfid-result', ...$payload);
    }

    public function apply(): void
    {
        // Process the circs data
        // This is where you'd handle the circulation data
    }
}; ?>

<x-slot name="title">{{ __("Cari") . " — " . __("Inventaris") }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-ce></x-nav-inventory-ce>
</x-slot>

<div class="py-5 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200 gap-4">
    <div
        wire:ignore
        x-data="{
            storageKey: 'invce_last_rfid_code',
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

                // If your page is https:// then ws:// will be blocked by the browser.
                if (window.location?.protocol === 'https:' && this.url.startsWith('ws://')) {
                    this.setDisconnected('Page is HTTPS, WebSocket must be WSS (wss://...)');
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

                    // Accept either plain text or JSON payloads from Python.
                    let payload = raw;
                    if (raw && (raw.startsWith('{') || raw.startsWith('['))) {
                        try { payload = JSON.parse(raw); } catch (_) {}
                    }

                    let code = '';
                    if (typeof payload === 'string') {
                        code = payload;
                    } else if (payload && typeof payload === 'object') {
                        code =
                            payload.data ??
                            payload.code ??
                            payload.tag ??
                            payload.uid ??
                            '';
                        if (code === '' && typeof payload.message === 'string') code = payload.message;
                    }

                    // Strip control characters (e.g. ETX \u0003 sent by some RFID readers after a scan).
                    code = String(code ?? '').replace(/[\x00-\x1F\x7F]/g, '').trim();
                    if (code !== '') {
                        const now = Date.now();
                        // Ignore duplicate bursts from a single scan, but allow re-scan later.
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
                    // The browser doesn't give much detail here.
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

                // Exponential backoff: 1s, 2s, 4s, ... max 10s
                const delay = Math.min(10000, 1000 * Math.pow(2, this.reconnectAttempt++));
                this.reconnectTimer = setTimeout(() => this.connect(), delay);
            },
        }"
        x-init="connect()"
        class="mt-3"
    >
        <div>
            Status:
            <span x-text="connected ? 'Connected' : 'Not connected'"></span>
            <span class="text-xs opacity-70" x-show="url">(<span x-text="url"></span>)</span>
        </div>
        <div x-show="error" class="text-red-600">
            Error: <span x-text="error"></span>
        </div>
    </div>

    <div
        class="mt-4"
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
        @rfid-result.window="auth = ($event.detail.status === 'cleared' ? null : $event.detail)"
    >
        <template x-if="auth && auth.status === 'found'">
            <div class="p-4 border rounded bg-green-50 text-green-700">
                RFID <span class="font-mono" x-text="auth.rf_code"></span> terdaftar sebagai <span class="font-mono" x-text="auth.name"></span>.
            </div>
        </template>

        <template x-if="auth && auth.status === 'not_found'">
            <div class="p-4 border rounded bg-red-50 text-red-700">
                RFID <span class="font-mono" x-text="auth.rf_code"></span> tidak terdaftar.
            </div>
        </template>

        <div x-data="editorData()" x-init="editorInit()">
            <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6 mb-8">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100"><i class="icon-arrow-right-left mr-3"></i>{{ __('Sirkulasi saja') }}</h1>
            <div class="flex gap-x-2">
               <div class="px-2 my-auto">
                  <span x-text="rowCount"></span><span class="">{{ ' ' . __('baris') }}</span>
               </div>
               <div class="btn-group">
                  <x-secondary-button type="button" x-on:click="editorDownload"><i class="icon-download"></i></x-secondary-button>
                  <x-secondary-button type="button" x-on:click="editorReset"><i class="icon-rotate-cw"></i></x-secondary-button>
               </div>
               <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'guide')">{{ __('Panduan') }}</x-secondary-button>
               <x-secondary-button type="button" x-on:click="editorApply">
                  <div class="relative">
                     <span wire:loading.class="opacity-0" wire:target="apply"><i class="icon-circle-check mr-2"></i>{{ __('Terapkan') }}</span>
                     <x-spinner wire:loading.class.remove="hidden" wire:target="apply" class="hidden sm mono"></x-spinner>                
                  </div>                
               </x-secondary-button>
            </div>
            </div>
         <div class="bg-white dark:bg-neutral-800 shadow rounded-lg text-sm" id="editor-table" wire:ignore></div>
      </div>
    </div>

</div>

@script
<script type="module">   
   Alpine.data('editorData', () => ({
         table: null,
         circs: @entangle('circs'),
         circsDefault: null,
         rowCount: 0,            
         
         editorInit() {
            const columns = [
               { title: 'item_id', field: 'item_id', width: 80 }, 
               { title: 'item_code', field: 'item_code', width: 110 }, 
               { title: 'curr', field: 'curr', width: 80},
               { title: 'qty_relative', field: 'qty_relative', width: 100 },
               { title: 'uom', field: 'uom', width: 80 },
               { title: 'remarks', field: 'remarks', width: 200 }, 
            ];
            
            this.circsDefault = this.circsDefault ? this.circsDefault : this.circs,

            // Initialize Tabulator
            this.table = new Tabulator("#editor-table", {
               
               data: this.circsDefault,
               layout: "fitColumns",
               columns: columns,
               height: "calc(100vh - 19rem)",

               //configure clipboard to allow copy and paste of range format data
               clipboard: true,
               clipboardCopyStyled:false,
               clipboardCopyConfig:{
                  rowHeaders:false,
                  columnHeaders:false,
               },
               clipboardCopyRowRange:"range",
               clipboardPasteParser:"range",
               clipboardPasteAction:"replace",

               rowHeader:{resizable: false, frozen: true, width:40, hozAlign:"center", formatter: "rownum", cssClass:"range-header-col", editor:false},
               columnDefaults:{
                  headerSort:false,
                  headerHozAlign:"center",
                  resizable:"header",
                  editor: "input"
               }
            });      
            
            this.table.on("dataLoaded", (data) => {

               if (data.length > 100) {
                  $dispatch('open-modal', 'warning');
               }

               // Check if the last row exists and is empty (all properties are empty strings)
               if (data.length > 0) {
                  const lastRow = data[data.length - 1];
                  const isLastRowEmpty = Object.values(lastRow).every(value => value === "");

                  // If the last row is empty, remove it
                  if (isLastRowEmpty) {
                     data.pop(); // Remove the last row from the data array
                     this.table.setData(data);
                  }
               }

               this.rowCount = data.length; // Update the row count
            });

            this.table.on("dataChanged", (data) => {             
               this.rowCount = data.length; // Update the row count
            });
            
            document.addEventListener('editor-reset', event => {
               this.table.destroy();
               this.editorInit();
            });
         },
         
         editorApply() {
            this.circs = this.table.getData();
            $wire.apply();
         },

         editorReset() {
            Livewire.navigate("{{ route('inventory.circs.bulk-operation.index') }}");
         },

         editorDownload() {
            this.table.download("csv", "bulk_operation_circulations.csv"); 
         },
   }));
</script>
@endscript
