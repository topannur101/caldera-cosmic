<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Renderless;

use App\Models\InvCirc;
use App\Models\User;

new class extends Component {
    #[Reactive]
    public int $stock_id = 0;

    #[Reactive]
    public string $stock_uom = "";

    #[Reactive]
    public int $curr_id = 1;

    #[Reactive]
    public float $curr_rate = 1;

    #[Reactive]
    public float $unit_price = 0;

    #[Reactive]
    public bool $can_eval = false;

    public array $types = [];

    public string $type = "";

    public int $qty_relative = 0;

    public float $amount = 0;

    public string $remarks = "";

    public int $user_id = 0;

    public string $userq = "";

    #[Renderless]
    public function updatedUserq()
    {
        $this->dispatch("userq-updated", $this->userq);
    }

    public function mount()
    {
        $this->user_id = Auth::user()->id;

        $this->types = [
            "deposit" => [
                "icon" => "icon-plus",
                "color" => "text-green-500",
                "text" => __("Tambah"),
            ],
            "capture" => [
                "icon" => "icon-git-commit-horizontal",
                "color" => "text-yellow-600",
                "text" => __("Catat"),
            ],
            "withdrawal" => [
                "icon" => "icon-minus",
                "color" => "text-red-500",
                "text" => __("Ambil"),
            ],
        ];
    }

    public function save()
    {
        $this->remarks = trim($this->remarks);

        $this->validate([
            "type" => ["required", "in:deposit,capture,withdrawal"],
            "stock_id" => ["required", "exists:inv_stocks,id"],
            "qty_relative" => ["required", "gte:0", "lte:100000"],
            "remarks" => ["required", "string", "max:256"],
        ]);

        // withdrawal and capture qty_relative cannot be 0
        if ($this->type == "deposit" || $this->type == "withdrawal") {
            if (! $this->qty_relative > 0) {
                $this->js('toast("' . __("Qty tidak boleh 0") . '", { type: "danger" } )');
                return;
            }
        }

        $amount = 0;
        $amount = $this->unit_price * $this->qty_relative;
        $unit_price = $this->unit_price;

        // amount should always be main currency (USD)
        if ($amount > 0 && $this->curr_id !== 1) {
            $amount /= $this->curr_rate;
            $unit_price /= $this->curr_rate;
        }

        $user = $this->userq ? User::where("emp_id", $this->userq)->first() : null;
        $user_id = (int) ($user ? $user->id : Auth::user()->id);
        $auth_id = (int) Auth::user()->id;

        $is_delegated = false;
        if ($user_id !== $auth_id && ! $this->can_eval) {
            $this->js('toast("' . __("Kamu tidak dapat mendelegasikan sirkulasi di area ini") . '", { type: "danger" } )');
            return;
        } elseif ($user_id !== $auth_id) {
            $is_delegated = true;
        }

        $circ = new InvCirc();
        $circ->amount = $amount;
        $circ->unit_price = $unit_price;

        $circ->type = $this->type;
        $circ->inv_stock_id = $this->stock_id;
        $circ->qty_relative = $this->qty_relative;
        $circ->remarks = $this->remarks;

        $circ->user_id = $user_id;
        $circ->is_delegated = $is_delegated;

        $response = Gate::inspect("create", $circ);

        if ($response->denied()) {
            $this->js('toast("' . $response->message() . '", { type: "danger" })');
            return;
        }

        $circ->save();

        $this->dispatch("close-popover");
        $this->dispatch("circ-created");
        $this->js('toast("' . __("Sirkulasi dibuat") . '", { type: "success" } )');
        $this->reset(["qty_relative", "amount", "remarks", "userq", "user_id"]);
    }
};

?>

<x-popover-button
    focus="{{ 'circ-' . $type . (($type == 'deposit' || $type == 'withdrawal') ? '-qty' : '-remarks') }}"
    icon="{{ $types[$type]['icon'] . ' ' . $types[$type]['color'] }}"
>
    @if ($type == "deposit" || $type == "withdrawal")
        <div
            x-data="{
                qty_relative: @entangle("qty_relative"),
                unit_price: @entangle("unit_price"),
                curr_rate: @entangle("curr_rate"),

                // Calculated properties
                amount_primary: 0,
                primary_currency: 'USD',

                // Calculate amount in primary currency
                calculate() {
                    this.amount_primary =
                        (this.qty_relative * this.unit_price) / this.curr_rate
                },

                // Format number for display
                formatNumber(num) {
                    return new Intl.NumberFormat('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }).format(num)
                },
            }"
            x-init="
                calculate()
                $watch('qty_relative', () => calculate())
                $watch('unit_price', () => calculate())
                $watch('curr_rate', () => calculate())
            "
        >
            <form wire:submit="save" class="grid grid-cols-1 gap-y-4">
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-{{ $type }}-qty"><span>{{ __("Jumlah") }}</span></label>
                    <x-text-input-suffix
                        x-model="qty_relative"
                        suffix="{{ $stock_uom }}"
                        id="circ-{{ $type }}-qty"
                        class="text-center"
                        name="circ-{{ $type }}-qty"
                        type="number"
                        value=""
                        min="1"
                        placeholder="Qty"
                    />
                </div>

                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-{{ $type }}-remarks">{{ __("Keterangan") }}</label>
                    <x-text-input wire:model="remarks" id="circ-{{ $type }}-remarks" autocomplete="circ-remarks" />
                </div>

                @if ($can_eval)
                    <div
                        x-data="{ 'open': false, 'userq': @entangle("userq").live }"
                        x-on:click.away="open = false"
                        x-on:user-selected="userq = $event.detail.user_emp_id; open = false"
                    >
                        <label for="circ-{{ $type }}-user" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Pengguna") }}</label>
                        <x-text-input-icon
                            x-ref="userq"
                            x-model="userq"
                            x-on:focus="open = true"
                            x-on:change="open = true"
                            icon="icon-user"
                            id="circ-{{ $type }}-user"
                            type="text"
                            autocomplete="off"
                            placeholder="{{ __('Pengguna') }}"
                        />
                        <div class="relative" x-show="open" x-cloak>
                            <div class="absolute top-1 left-0 w-full z-10">
                                <livewire:layout.user-select />
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Amount Preview --}}
                <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-3 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span x-text="primary_currency"></span>
                        <span class="font-mono" x-text="formatNumber(amount_primary)"></span>
                    </div>
                </div>

                @if ($errors->any())
                    <div>
                        <x-input-error :messages="$errors->first()" />
                    </div>
                @endif

                <div class="text-right">
                    <x-secondary-button type="submit">
                        <span class="{{ $types[$type]["color"] }}">
                            <i class="{{ $types[$type]["icon"] }} mr-2"></i>
                            {{ $types[$type]["text"] }}
                        </span>
                    </x-secondary-button>
                </div>
            </form>
        </div>
    @else
        <form wire:submit="save" class="grid grid-cols-1 gap-y-4">
            <div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-{{ $type }}-remarks">{{ __("Keterangan") }}</label>
                <x-text-input wire:model="remarks" id="circ-{{ $type }}-remarks" autocomplete="circ-remarks" />
            </div>
            @if ($can_eval)
                <div
                    x-data="{ 'open': false, 'userq': @entangle("userq").live }"
                    x-on:click.away="open = false"
                    x-on:user-selected="userq = $event.detail.user_emp_id; open = false"
                >
                    <label for="circ-{{ $type }}-user" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Pengguna") }}</label>
                    <x-text-input-icon
                        x-ref="userq"
                        x-model="userq"
                        x-on:focus="open = true"
                        x-on:change="open = true"
                        icon="icon-user"
                        id="circ-{{ $type }}-user"
                        type="text"
                        autocomplete="off"
                        placeholder="{{ __('Pengguna') }}"
                    />
                    <div class="relative" x-show="open" x-cloak>
                        <div class="absolute top-1 left-0 w-full z-10">
                            <livewire:layout.user-select />
                        </div>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div>
                    <x-input-error :messages="$errors->first()" />
                </div>
            @endif

            <div class="text-right">
                <x-secondary-button type="submit">
                    <span class="{{ $types[$type]["color"] }}">
                        <i class="{{ $types[$type]["icon"] }} mr-2"></i>
                        {{ $types[$type]["text"] }}
                    </span>
                </x-secondary-button>
            </div>
        </form>
    @endif
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</x-popover-button>
