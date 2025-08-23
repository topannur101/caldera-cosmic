<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\InvItem;

new #[Layout("layouts.app")] class extends Component {
    #[Url]
    public int $id = 0;

    public bool $is_editing = true;

    public array $items = [
        0 => [
            "id" => "",
            "name" => "",
            "desc" => "",
            "code" => "",
            "loc_id" => 0,
            "loc_name" => "",
            "tags_list" => "",
            "photo" => "",
            "area_id" => 0,
            "area_name" => "",
            "is_active" => false,
            "updated_at" => "",
            "last_withdrawal" => "",
        ],
    ];

    public string $loc_parent = "";

    public string $loc_bin = "";

    public array $tags = [];

    public array $stocks = [];

    public function mount()
    {
        $item = InvItem::find($this->id);
        if ($item) {
            $this->items[0]["id"] = $item->id;
            $this->items[0]["name"] = $item->name;
            $this->items[0]["desc"] = $item->desc;
            $this->items[0]["code"] = $item->code;

            // for is_editing false
            $this->items[0]["loc_name"] = $item->inv_loc_id ? $item->inv_loc->parent . "-" . $item->inv_loc->bin : "";

            // for is_editing true
            $this->loc_parent = $item->inv_loc_id ? $item->inv_loc->parent : "";
            $this->loc_bin = $item->inv_loc_id ? $item->inv_loc->bin : "";
            $this->tags = $item->inv_tags->pluck("name")->toArray();
            $this->stocks = $item->inv_stocks
                ->map(function ($stock) {
                    return [
                        "uom" => $stock->uom,
                        "unit_price" => $stock->unit_price,
                        "currency" => $stock->inv_curr->name,
                    ];
                })
                ->toArray();

            $this->items[0]["tags_list"] = $item->inv_tags->pluck("name")->implode(", ");
            $this->items[0]["photo"] = $item->photo;
            $this->items[0]["area_id"] = $item->inv_area_id;
            $this->items[0]["area_name"] = $item->inv_area->name;
            $this->items[0]["is_active"] = $item->is_active;
            $this->items[0]["updated_at"] = $item->updated_at->diffForHumans();
        }
    }
};

?>

<x-slot name="title">{{ $items[0]["name"] . " — " . __("Inventaris") }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __("Edit barang") }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-4xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <livewire:inventory.items.form :$items :$is_editing :$loc_parent :$loc_bin :$tags :$stocks />
</div>
