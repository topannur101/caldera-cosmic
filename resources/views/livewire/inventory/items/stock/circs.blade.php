<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\On;
use App\Models\InvCirc;

new class extends Component {
    use WithPagination;

    public $perPage = 10;

    #[Reactive]
    public $stock_id = 0;

    public $stock_id_old = 0;

    public function with(): array
    {
        $this->stock_id_old == $this->stock_id ?: $this->resetPage();
        $this->stock_id_old = $this->stock_id;

        $circs = InvCirc::latest("updated_at")
            ->where("inv_stock_id", $this->stock_id)
            ->paginate($this->perPage);

        return [
            "circs" => $circs,
        ];
    }

    #[On("circ-created")]
    #[On("circ-updated")]
    #[On("circ-evaluated")]
    public function circsResetPage()
    {
        $this->resetPage();
    }

    public function circsDownload()
    {
        // Create a unique token for this download request
        $token = md5(uniqid());

        // Store the token in the session
        session()->put("inv_circs_token", $token);

        $this->js('toast("' . __("Unduhan dimulai...") . '", { type: "success" })');

        // Redirect to a temporary route that will handle the streaming
        return redirect()->route("download.inv-circs", ["token" => $token, "stock_id" => $this->stock_id]);
    }
};

?>

<div>
    <div wire:key="stock-modals">
        <x-modal name="circ-show">
            <livewire:inventory.circs.circ-show />
        </x-modal>
        <x-modal name="circs-chart">
            <livewire:inventory.items.stock.circs-chart />
        </x-modal>
    </div>

    <div wire:key="stock-spotlights">
        <x-spotlight name="downloading" maxWidth="sm">
            <div class="w-full flex flex-col gap-y-6 pb-10 text-center">
                <div class="h-16 relative">
                    <x-spinner class="mono" />
                </div>
                <header>
                    <h2 class="text-xl font-medium">
                        {{ __("Memproses unduhan...") }}
                    </h2>
                </header>
            </div>
        </x-spotlight>
    </div>

    <div wire:loading.class="cal-shimmer">
        @if ($circs->count())
            <table wire:key="circs" class="w-full [&_td]:py-2 [&_tr_td:first-child]:w-[1%] [&_tr_td:last-child]:w-[1%]">
                @foreach ($circs as $circ)
                    <x-inv-circ-stock-tr
                        wire:key="circ-{{ $circ->id }}"
                        id="{{ $circ->id }}"
                        color="{{ $circ->type_color() }}"
                        icon="{{ $circ->type_icon() }}"
                        qty_relative="{{ $circ->qty_relative }}"
                        uom="{{ $circ->inv_stock->uom }}"
                        user_name="{{ $circ->user->name }}"
                        user_emp_id="{{ $circ->user->emp_id }}"
                        user_photo="{{ $circ->user->photo }}"
                        is_delegated="{{ $circ->is_delegated }}"
                        eval_status="{{ $circ->eval_status }}"
                        eval_user_name="{{ $circ->eval_user?->name }}"
                        eval_user_emp_id="{{ $circ->eval_user?->emp_id }}"
                        updated_at="{{ $circ->updated_at }}"
                        updated_at_friendly="{{ $circ->updated_at->diffForHumans() }}"
                        remarks="{{ $circ->remarks }}"
                        eval_icon="{{ $circ->eval_icon() }}"
                    ></x-inv-circ-stock-tr>
                @endforeach
            </table>
            <div class="px-3 py-1">
                {{ $circs->onEachSide(1)->links(data: ["scrollTo" => false]) }}
            </div>
            <div class="btn-group flex justify-center p-1">
                <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'circs-chart'); $dispatch('circs-chart', { stock_id: {{ $stock_id }} })">
                    <i class="icon-chart-line"></i>
                </x-secondary-button>
                <x-secondary-button
                    type="button"
                    wire:click="circsDownload"
                    x-on:click="$dispatch('open-spotlight', 'downloading'); setTimeout(() => { window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' })) }, 2000)"
                >
                    <i class="icon-download"></i>
                </x-secondary-button>
            </div>
        @else
            <div class="py-4 text-neutral-500 text-center">
                {{ __("Tak ada sirkulasi") }}
            </div>
        @endif
    </div>
</div>
