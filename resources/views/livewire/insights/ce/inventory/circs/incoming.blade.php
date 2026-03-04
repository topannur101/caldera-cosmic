<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Models\User;
use App\Models\InvCeAuth;


new #[Layout("layouts.app")] class extends Component {
    public string $cookieKey = 'invce_incoming_auth';
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
}; ?>

<div>
    <h1>Halaman Incoming Chemical</h1>
    <h1>Masuk Sebagai : <span x-text="auth && auth.status === 'found' ? auth.name : 'Guest'"></span></h1>

    <div
        wire:ignore
        x-data="{
            storageKey: 'invce_last_rfid_code',
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
    </div>

    <div class="p-4 border rounded bg-white">
        <div class="font-semibold text-lg">Data Auth</div>
        <div class="mt-2 grid gap-1 text-sm">
            <div><span class="opacity-70">RFID: {{ $auth['rf_code'] ?? '-' }}</span></div>
            <div><span class="opacity-70">Nama: {{ $auth['name'] ?? '-' }}</span></div>
            <div><span class="opacity-70">Emp ID: {{ $auth['emp_id'] ?? '-' }}</span></div>
            <div><span class="opacity-70">Active: {{ $auth['is_active'] ?? 0 === 1 ? 'Yes' : 'No' }}</span></div>
            <div><span class="opacity-70">Area: {{ $auth['area'] ?? '-' }}</span></div>
            <div><span class="opacity-70">Resource Type: {{ $auth['resource_type'] ?? '-' }}</span></div>
            <div><span class="opacity-70">Resource ID: {{ $auth['resource_id'] ?? '-' }}</span></div>
            <div><span class="opacity-70">Saved At: {{ $auth['saved_at'] ?? '-' }}</span></div>
        </div>
    </div>
</div>
