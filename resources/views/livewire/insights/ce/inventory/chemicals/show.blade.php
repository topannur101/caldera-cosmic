<?php

use App\Models\InvCeChemical;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout("layouts.app")] class extends Component {
    #[Url]
    public int $id = 0;

    #[Url]
    public int $stock_id = 0;

    public bool $not_found = false;

    public array $chemical = [];

    public array $stock = [];

    public array $planning_areas = [];

    public function mount(): void
    {
        if ($this->id < 1) {
            $this->not_found = true;
            return;
        }

        $chemical = InvCeChemical::query()
            ->with([
                "inv_ce_vendor:id,name",
                "inv_ce_location:id,parent,bin",
                "inv_ce_area:id,name",
                "inv_ce_stocks" => fn($query) => $query->orderByDesc("updated_at"),
            ])
            ->find($this->id);

        if (! $chemical) {
            $this->not_found = true;
            return;
        }

        $selectedStock = $this->stock_id > 0
            ? $chemical->inv_ce_stocks->firstWhere("id", $this->stock_id)
            : $chemical->inv_ce_stocks->first();

        $this->chemical = [
            "id" => $chemical->id,
            "item_code" => $chemical->item_code,
            "name" => $chemical->name,
            "uom" => $chemical->uom,
            "category_chemical" => $chemical->category_chemical,
            "photo" => $chemical->photo,
            "vendor_name" => $chemical->inv_ce_vendor?->name,
            "location_name" => $chemical->inv_ce_location
                ? ($chemical->inv_ce_location->parent . " - " . $chemical->inv_ce_location->bin)
                : null,
            "area_name" => $chemical->inv_ce_area?->name,
            "is_active" => (bool) $chemical->is_active,
            "created_at" => $chemical->created_at ? $chemical->created_at->format("d M Y H:i") : null,
            "updated_at" => $chemical->updated_at ? $chemical->updated_at->diffForHumans() : null,
        ];

        if ($selectedStock) {
            $planningArea = json_decode((string) $selectedStock->planning_area, true);

            $this->stock = [
                "id" => $selectedStock->id,
                "quantity" => $selectedStock->quantity,
                "unit_size" => $selectedStock->unit_size,
                "unit_uom" => $selectedStock->unit_uom,
                "unit_price" => $selectedStock->unit_price,
                "lot_number" => $selectedStock->lot_number,
                "expiry_date" => $selectedStock->expiry_date
                    ? Carbon::parse($selectedStock->expiry_date)->format("d M Y")
                    : null,
                "status" => $selectedStock->status,
                "remarks" => $selectedStock->remarks,
                "updated_at" => $selectedStock->updated_at?->diffForHumans(),
            ];

            $this->planning_areas = is_array($planningArea)
                ? array_values(array_filter($planningArea, fn($value) => filled($value)))
                : [];
        }
    }
}; ?>

<x-slot name="title">{{ ($chemical["name"] ?? __("Detail bahan kimia")) . " — " . __("Inventaris CE") }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-ce></x-nav-inventory-ce>
</x-slot>

<div class="py-12 max-w-5xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    @if ($not_found)
        <div class="text-center w-80 py-20 mx-auto">
            <i class="icon-ghost block text-5xl mb-6 text-neutral-400 dark:text-neutral-600"></i>
            <div class="text-neutral-500 mb-6">{{ __("Data bahan kimia tidak ditemukan.") }}</div>
            <x-link href="{{ route('inventory-ce.chemicals.index') }}" wire:navigate>
                <i class="icon-arrow-left mr-2"></i>{{ __("Kembali ke daftar") }}
            </x-link>
        </div>
    @else
        <div class="mb-6 flex items-center justify-between gap-4">
            <x-link href="{{ route('inventory-ce.chemicals.index') }}" wire:navigate>
                <i class="icon-arrow-left mr-2"></i>{{ __("Kembali") }}
            </x-link>
            <div class="flex items-center gap-3">
                <x-link
                    href="{{ route('inventory-ce.chemicals.edit', ['id' => $chemical['id'] ?? 0, 'stock_id' => $stock['id'] ?? 0]) }}"
                    wire:navigate
                >
                    <i class="icon-pencil mr-2"></i>{{ __("Edit") }}
                </x-link>
                <div class="text-xs uppercase text-neutral-500">
                    {{ __("Terakhir diperbarui") }}: {{ $chemical["updated_at"] ?? "-" }}
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4">
                    <div class="bg-neutral-200 dark:bg-neutral-700 rounded-lg aspect-square overflow-hidden flex items-center justify-center relative">
                        <i class="icon-flask-conical text-6xl text-neutral-500"></i>
                        @if (! empty($chemical["photo"]))
                            <img
                                class="absolute w-full h-full object-cover top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"
                                src="{{ "/storage/inv-ce-chemicals/" . $chemical["photo"] }}"
                                alt="{{ $chemical["name"] }}"
                            />
                        @endif
                    </div>
                    <div class="mt-4 text-xs uppercase text-neutral-500">{{ __("Status data") }}</div>
                    <div class="mt-2">
                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ ($chemical["is_active"] ?? false) ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300" : "bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300" }}"
                        >
                            {{ ($chemical["is_active"] ?? false) ? __("Aktif") : __("Tidak aktif") }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-6">
                    <div class="text-xs uppercase text-neutral-500 mb-1">{{ __("Bahan kimia") }}</div>
                    <h1 class="text-2xl font-semibold">{{ $chemical["name"] ?? "-" }}</h1>
                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-xs uppercase text-neutral-500">{{ __("Kode") }}</div>
                            <div class="font-medium">{{ $chemical["item_code"] ?: "-" }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-neutral-500">{{ __("Kategori") }}</div>
                            <div class="font-medium">{{ ucfirst($chemical["category_chemical"] ?? "-") }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-neutral-500">{{ __("Vendor") }}</div>
                            <div class="font-medium">{{ $chemical["vendor_name"] ?: "-" }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-neutral-500">{{ __("UOM") }}</div>
                            <div class="font-medium">{{ $chemical["uom"] ?: "-" }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-neutral-500">{{ __("Lokasi") }}</div>
                            <div class="font-medium">{{ $chemical["location_name"] ?: "-" }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-neutral-500">{{ __("Area") }}</div>
                            <div class="font-medium">{{ $chemical["area_name"] ?: "-" }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div class="text-xs uppercase text-neutral-500">{{ __("Unit stok") }}</div>
                        @if (! empty($stock))
                            <div class="text-xs text-neutral-500">#{{ $stock["id"] }}</div>
                        @endif
                    </div>

                    @if (empty($stock))
                        <div class="py-8 text-neutral-500">{{ __("Belum ada data unit stok.") }}</div>
                    @else
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <div class="text-xs uppercase text-neutral-500">{{ __("Qty") }}</div>
                                <div class="font-medium">{{ $stock["quantity"] . " " . ($chemical["uom"] ?: "") }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-neutral-500">{{ __("Ukuran unit") }}</div>
                                <div class="font-medium">{{ $stock["unit_size"] ?: "-" }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-neutral-500">{{ __("Unit UOM") }}</div>
                                <div class="font-medium">{{ $stock["unit_uom"] ?: "-" }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-neutral-500">{{ __("Total unit") }}</div>
                                <div class="font-medium">
                                    {{ is_numeric($stock["quantity"] ?? null) ? ($stock["quantity"] * $stock["unit_size"]) . " " . ($stock["unit_uom"] ?: "") : "-" }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-neutral-500">{{ __("Harga per unit") }}</div>
                                <div class="font-medium">
                                    {{ is_numeric($stock["unit_price"] ?? null) ? ("$" . number_format((float) $stock["unit_price"], 2, ".", ",")) : "-" }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-neutral-500">{{ __("Total harga") }}</div>
                                <!-- harga unit * total unit -->
                                <div class="font-medium">
                                    {{ is_numeric($stock["unit_price"] ?? null) ? ("$" . number_format((float) $stock["unit_price"] * $stock["quantity"], 2, ".", ",")) : "-" }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-neutral-500">{{ __("Lot number") }}</div>
                                <div class="font-medium">{{ $stock["lot_number"] ?: "-" }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-neutral-500">{{ __("Kedaluwarsa") }}</div>
                                <div class="font-medium">{{ $stock["expiry_date"] ?: "-" }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-neutral-500">{{ __("Status") }}</div>
                                <div class="font-medium">{{ ucfirst($stock["status"] ?? "-") }}</div>
                            </div>
                        </div>

                        <div class="mt-5">
                            <div class="text-xs uppercase text-neutral-500 mb-2">{{ __("Planning area") }}</div>
                            @if (count($planning_areas))
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($planning_areas as $planning_area)
                                        <span class="inline-flex items-center rounded-full bg-neutral-100 dark:bg-neutral-700 px-3 py-1 text-xs">
                                            {{ $planning_area }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-sm text-neutral-500">-</div>
                            @endif
                        </div>

                        <div class="mt-5">
                            <div class="text-xs uppercase text-neutral-500">{{ __("Catatan") }}</div>
                            <div class="text-sm mt-1">{{ $stock["remarks"] ?: "-" }}</div>
                        </div>

                        <div class="mt-4 text-xs text-neutral-500">
                            {{ __("Diperbarui") }}: {{ $stock["updated_at"] ?? "-" }}
                        </div>
                    @endif
                </div>

                <div class="text-xs text-neutral-500">
                    {{ __("Dibuat") }}: {{ $chemical["created_at"] ?? "-" }}
                </div>
            </div>
        </div>
    @endif
</div>
