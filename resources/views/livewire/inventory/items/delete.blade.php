<?php

use Livewire\Volt\Component;
use App\Models\InvItem;
use App\Models\InvCirc;
use App\Models\InvStock;

new class extends Component {
    public int $id = 0;

    public bool $is_deletable = false;

    public function placeholder()
    {
        return view("livewire.layout.modal-placeholder");
    }

    public function mount()
    {
        try {
            $item = InvItem::find($this->id);
            $circs = InvCirc::whereHas("inv_stock", function ($query) use ($item) {
                $query->where("inv_item_id", $item->id);
            })
                ->where("eval_status", "approved")
                ->count();

            $this->is_deletable = (bool) ! $circs;
        } catch (\Throwable $th) {
            $this->js('toast("' . $th->getMessage() . '", { type: "danger" } )');
        }
    }

    public function confirmDelete()
    {
        try {
            if (! $this->is_deletable) {
                throw new Exception(__("Barang tak dapat dihapus"), 1);
            }

            InvCirc::whereHas("inv_stock", function ($query) {
                $query->where("inv_item_id", $this->id);
            })->delete();

            InvStock::where("inv_item_id", $this->id)->delete();
            InvItem::find($this->id)->delete();
            $this->redirect(route("inventory.items.index", ["is_deleted" => true]), false);
        } catch (\Throwable $th) {
            $this->js('toast("' . $th->getMessage() . '", { type: "danger" } )');
        }
    }
};

?>

<div class="p-6">
    @if ($is_deletable)
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Yakin?") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')">
                <i class="icon-x"></i>
            </x-text-button>
        </div>
        <div class="my-6">
            {{ __("Semua sirkulasi yang tertunda dan ditolak akan ikut terhapus. Tindakan ini tidak dapat diurungkan.") }}
        </div>
        <div class="flex justify-end">
            <x-secondary-button type="button" wire:click="confirmDelete" class="text-red-500">
                {{ __("Hapus selamanya") }}
            </x-secondary-button>
        </div>
    @else
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Tidak dapat dihapus") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')">
                <i class="icon-x"></i>
            </x-text-button>
        </div>
        <div class="my-6">
            {{ __("Maaf, barang ini tidak dapat dihapus karena memiliki sirkulasi yang disetujui. Jika kamu yakin bahwa barang ini layak dihapus, hubungi superuser.") }}
        </div>
        <div class="flex justify-end">
            <x-primary-button type="button" x-on:click="$dispatch('close')">
                {{ __("Paham") }}
            </x-primary-button>
        </div>
    @endif
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
