<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\On;

use App\Models\InvStock;

new class extends Component {
    #[Reactive]
    public int $stock_id = 0;

    #[Reactive]
    public int $stock_qty_min = 0;

    #[Reactive]
    public int $stock_qty_max = 0;

    public int $qty_min = 0;

    public int $qty_max = 0;

    #[On("edit-qty-limit")]
    public function init()
    {
        $this->qty_min = $this->stock_qty_min;
        $this->qty_max = $this->stock_qty_max;
    }

    public function save()
    {
        try {
            $this->validate([
                "qty_min" => "required|integer|lte:qty_max|lte:10000",
                "qty_max" => "required|integer|gte:qty_min|lte:10000",
            ]);

            $stock = InvStock::find($this->stock_id);
            if (! $stock) {
                throw new \Exception(__("Unit stok tidak ditemukan"));
            }

            $item = $stock->inv_item;

            $itemEval = Gate::inspect("store", $item);
            if ($itemEval->denied()) {
                throw new \Exception(__("Tak ada wewenang untuk mengelola barang ini"));
            }

            $stock->update([
                "qty_min" => $this->qty_min,
                "qty_max" => $this->qty_max,
            ]);
            $this->js('toast("' . __("Batas qty diperbarui") . '", { type: "success" })');
            $this->js('$dispatch("close")');
            $this->dispatch("qty-limit-updated");
        } catch (\Throwable $th) {
            $this->js('toast("' . $th->getMessage() . '", { type: "danger" })');
        }
    }
};

?>

<div class="relative">
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                <i class="icon-chevrons-down-up mr-2"></i>
                {{ __("Edit batas qty") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="mb-6">
            <div class="grid grid-cols1 sm:grid-cols-2 mt-6 gap-y-6 gap-x-3">
                <div>
                    <label for="edit-qty_min" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Qty mininum") }}</label>
                    <x-text-input id="edit-qty_min" wire:model="qty_min" type="text" autocomplete="off" />
                </div>
                <div>
                    <label for="edit-qty_max" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Qty maksimum") }}</label>
                    <x-text-input id="edit-qty_max" wire:model="qty_max" type="text" autocomplete="off" />
                </div>
            </div>
        </div>
        <div class="flex justify-end">
            <x-primary-button type="submit">{{ __("Simpan") }}</x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
