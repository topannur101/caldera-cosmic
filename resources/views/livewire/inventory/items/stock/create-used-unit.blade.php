<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\On;

use App\Models\InvStock;

new class extends Component {
    public string $condition = "";

    #[Reactive]
    public int $stock_id = 0;

    public int $unit_price_reference = 0;

    public int $unit_price = 0;

    public string $curr_name = "";

    public string $uom = "";

    public int $rate = 10;

    #[On("create-used-unit")]
    public function init()
    {
        $stock = InvStock::find($this->stock_id);
        $stocks = InvStock::where("inv_item_id", $stock->inv_item_id)
            ->where("is_active", true)
            ->get();

        $condition = "";

        if (str_ends_with(strtoupper($stock->uom), "-B")) {
            $condition = "uom_suffix_b_in_this_unit";
        } elseif (
            $stocks->contains(function ($s) {
                return str_ends_with($s->uom, "-B");
            })
        ) {
            $condition = "uom_suffix_b_in_other_unit";
        } elseif ($stocks->count() > 2) {
            $condition = "too_many_stock_units";
        } else {
            $condition = "no_uom_suffix_b";
            $this->unit_price_reference = $stock->unit_price;
            $this->curr_name = $stock->inv_curr->name;
            $this->uom = $stock->uom;
            $this->unit_price = $stock->unit_price * ($this->rate / 100);
        }

        $this->condition = $condition;
    }

    public function create()
    {
        try {
            $this->validate([
                "unit_price" => "required|numeric|gte:0|lte:100000000",
            ]);

            $stock = InvStock::find($this->stock_id);
            if (! $stock) {
                throw new \Exception(__("Unit stok tidak ditemukan"));
            }

            $item = $stock->inv_item;

            $itemEval = Gate::inspect("circCreate", $item);
            if ($itemEval->denied()) {
                throw new \Exception($itemEval->message());
            }

            $stock_used = InvStock::updateOrCreate(
                [
                    "inv_item_id" => $item->id,
                    "uom" => $this->uom . "-B",
                    "inv_curr_id" => $stock->inv_curr_id,
                ],
                [
                    "unit_price" => $this->unit_price,
                    "is_active" => true,
                ],
            );

            $this->js('toast("' . __("Unit stok bekas berhasil dibuat") . '", { type: "success" })');
            $this->js('$dispatch("close")');
            $this->dispatch("used-unit-created", $stock_used->id);
        } catch (\Throwable $th) {
            $this->js('toast("' . $th->getMessage() . '", { type: "error" })');
        }
    }
};

?>

<div class="relative p-6">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __("Buat unit stok bekas") }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
    </div>
    <div class="my-4 text-sm">
        @switch($condition)
            @case("uom_suffix_b_in_this_unit")
                {{ __("Barang ini sudah mempunyai unit stok dengan akhiran -B.") }}

                @break
            @case("uom_suffix_b_in_other_unit")
                {{ __("Barang ini sudah mempunyai unit stok dengan akhiran -B.") }}

                @break
            @case("too_many_stock_units")
                {{ __("Jumlah stok unit di barang ini sudah mencapai maksimum (3 unit stok).") }}

                @break
            @case("no_uom_suffix_b")
                {{ __("Unit stok untuk barang bekas akan dibuat dengan rincian sebagai berikut:") }}
                <div
                    x-data="{
                        unit_price_reference: @entangle("unit_price_reference"),
                        unit_price: @entangle("unit_price"),
                        curr_name: @entangle("curr_name"),
                        uom: @entangle("uom"),
                        rate: @entangle("rate"),
                    }"
                    class="mt-4"
                >
                    <div class="grid grid-cols-1 gap-y-4">
                        <hr class="border-neutral-300 dark:border-neutral-600" />
                        <div>
                            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="unit_price"><span>{{ __("Nama stok unit") }}</span></label>
                            <div class="px-3 font-bold" x-text="uom + '-B'"></div>
                        </div>
                        <div>
                            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="unit_price"><span>{{ __("Harga satuan") }}</span></label>
                            <div class="px-3" x-text="curr_name + ' ' + unit_price"></div>
                        </div>
                        <hr class="border-neutral-300 dark:border-neutral-600" />
                        <div>
                            {{ __("Harga satuan diatas ditentukan dari 10% harga satuan dari stok unit saat ini.") }}
                        </div>
                    </div>
                </div>

                @break
        @endswitch
    </div>
    <div class="flex justify-end">
        @switch($condition)
            @case("uom_suffix_b_in_this_unit")
            @case("uom_suffix_b_in_other_unit")
            @case("too_many_stock_units")
                <x-primary-button type="button" x-on:click="$dispatch('close')">{{ __("Paham") }}</x-primary-button>

                @break
            @case("no_uom_suffix_b")
                <x-primary-button type="button" wire:click="create">{{ __("Buat") }}</x-primary-button>

                @break
        @endswitch
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
