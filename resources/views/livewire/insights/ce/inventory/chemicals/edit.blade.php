<?php

use App\Models\InvCeArea;
use App\Models\InvCeChemical;
use App\Models\InvCeLocation;
use App\Models\InvCeStock;
use App\Models\InvCeVendor;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Carbon\Carbon;

new #[Layout("layouts.app")] class extends Component {
    #[Url]
    public int $id = 0;

    #[Url]
    public int $stock_id = 0;

    public bool $not_found = false;

    public string $item_code = "";

    public string $name = "";

    public int $inv_ce_vendor_id = 0;

    public string $uom = "";

    public string $category_chemical = "single";

    public ?int $location_id = null;

    public ?int $area_id = null;

    public int $quantity = 0;

    public string $unit_size = "0";

    public string $unit_uom = "";

    public string $lot_number = "0";

    public string $unit_price = "0";

    public string $expiry_date = "";

    public string $planning_area = "";

    public string $status = "pending";

    public string $remarks = "";

    public bool $is_active = true;

    public array $vendors = [];

    public array $locations = [];

    public array $areas = [];

    public int $selected_stock_id = 0;

    public function mount(): void
    {
        $this->vendors = InvCeVendor::query()
            ->where("is_active", true)
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

        if ($this->id < 1) {
            $this->not_found = true;
            return;
        }

        $chemical = InvCeChemical::query()
            ->with(["inv_ce_stocks" => fn($query) => $query->orderByDesc("updated_at")])
            ->find($this->id);

        if (! $chemical) {
            $this->not_found = true;
            return;
        }

        $stock = $this->stock_id > 0
            ? $chemical->inv_ce_stocks->firstWhere("id", $this->stock_id)
            : $chemical->inv_ce_stocks->first();

        if (! $stock) {
            $this->not_found = true;
            return;
        }

        $this->item_code = (string) $chemical->item_code;
        $this->name = (string) $chemical->name;
        $this->inv_ce_vendor_id = (int) $chemical->inv_ce_vendor_id;
        $this->uom = (string) $chemical->uom;
        $this->category_chemical = (string) $chemical->category_chemical;
        $this->location_id = $chemical->location_id ? (int) $chemical->location_id : null;
        $this->area_id = $chemical->area_id ? (int) $chemical->area_id : null;
        $this->is_active = (bool) $chemical->is_active;

        $planningArea = json_decode((string) $stock->planning_area, true);

        $this->selected_stock_id = (int) $stock->id;
        $this->quantity = (int) $stock->quantity;
        $this->unit_size = (string) $stock->unit_size;
        $this->unit_uom = (string) $stock->unit_uom;
        $this->lot_number = (string) $stock->lot_number;
        $this->unit_price = (string) $stock->unit_price;
        $this->expiry_date = $stock->expiry_date ? Carbon::parse($stock->expiry_date)->format("Y-m-d") : "";
        $this->planning_area = is_array($planningArea) ? implode(", ", $planningArea) : "";
        $this->status = (string) $stock->status;
        $this->remarks = (string) ($stock->remarks ?? "");
    }

    public function rules(): array
    {
        return [
            "item_code" => ["required", "string", "max:255", "unique:inv_ce_chemicals,item_code," . $this->id],
            "name" => ["required", "string", "max:255"],
            "inv_ce_vendor_id" => ["required", "integer", "exists:inv_ce_vendors,id"],
            "uom" => ["required", "string", "max:100"],
            "category_chemical" => ["required", "in:single,double"],
            "location_id" => ["nullable", "integer", "exists:inv_ce_locations,id"],
            "area_id" => ["nullable", "integer", "exists:inv_ce_areas,id"],
            "is_active" => ["required", "boolean"],
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
        $validated = $this->validate();
        $planningArea = $this->parsePlanningArea($validated["planning_area"]);

        if (! count($planningArea)) {
            $this->addError("planning_area", __("Minimal satu planning area wajib diisi."));
            return;
        }

        DB::transaction(function () use ($validated, $planningArea) {
            InvCeChemical::query()
                ->whereKey($this->id)
                ->update([
                    "item_code" => trim($validated["item_code"]),
                    "name" => trim($validated["name"]),
                    "inv_ce_vendor_id" => (int) $validated["inv_ce_vendor_id"],
                    "uom" => trim($validated["uom"]),
                    "category_chemical" => $validated["category_chemical"],
                    "location_id" => $validated["location_id"] ?: null,
                    "area_id" => $validated["area_id"] ?: null,
                    "is_active" => (bool) $validated["is_active"],
                ]);

            InvCeStock::query()
                ->whereKey($this->selected_stock_id)
                ->update([
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

        return $this->redirect(
            route("inventory-ce.chemicals.show", ["id" => $this->id, "stock_id" => $this->selected_stock_id]),
            navigate: true
        );
    }
}; ?>

<x-slot name="title">{{ __("Edit bahan kimia") . " — " . __("Inventaris CE") }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-ce></x-nav-inventory-ce>
</x-slot>

<div class="py-12 max-w-4xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    @if ($not_found)
        <div class="text-center w-80 py-20 mx-auto">
            <i class="icon-ghost block text-5xl mb-6 text-neutral-400 dark:text-neutral-600"></i>
            <div class="text-neutral-500 mb-6">{{ __("Data bahan kimia tidak ditemukan.") }}</div>
            <x-link href="{{ route('inventory-ce.chemicals.index') }}" wire:navigate>
                <i class="icon-arrow-left mr-2"></i>{{ __("Kembali ke daftar") }}
            </x-link>
        </div>
    @else
        <div class="px-4 sm:px-0 mb-8 grid grid-cols-1 gap-y-4">
            <div
                class="flex items-center justify-between gap-x-4 p-4 text-sm text-neutral-800 border border-neutral-300 rounded-lg bg-neutral-50 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-600"
                role="alert"
            >
                <div>{{ __("Perbarui data bahan kimia, lalu klik simpan.") }}</div>
                <div class="flex items-center gap-2">
                    <x-link href="{{ route('inventory-ce.chemicals.show', ['id' => $id, 'stock_id' => $selected_stock_id]) }}" wire:navigate>
                        <i class="icon-arrow-left mr-2"></i>{{ __("Kembali") }}
                    </x-link>
                    <div wire:loading>
                        <x-primary-button type="button" disabled><i class="icon-save mr-2"></i>{{ __("Simpan") }}</x-primary-button>
                    </div>
                    <div wire:loading.remove>
                        <x-primary-button type="submit" form="ce-chemical-edit-form"><i class="icon-save mr-2"></i>{{ __("Simpan") }}</x-primary-button>
                    </div>
                </div>
            </div>
            @if ($errors->any())
                <div class="text-center">
                    <x-input-error :messages="$errors->first()" />
                </div>
            @endif
        </div>

        <form id="ce-chemical-edit-form" wire:submit="save">
            <div class="block sm:flex gap-x-6">
                <div class="sm:w-72">
                    <div class="sticky top-5 left-0 space-y-6">
                        <div class="bg-neutral-200 dark:bg-neutral-800 rounded-lg aspect-square flex items-center justify-center">
                            <i class="icon-box text-7xl text-neutral-400"></i>
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
                                <x-text-input id="uom" wire:model.blur="uom" type="text" placeholder="Masukan UOM" />
                            </div>
                            @error("uom")
                                <x-input-error messages="{{ $message }}" class="pb-2" />
                            @enderror

                            <div class="flex items-center justify-between py-3">
                                <label for="is_active" class="text-neutral-500">{{ __("Status aktif") }}</label>
                                <x-select id="is_active" wire:model="is_active" class="w-32">
                                    <option value="1">{{ __("Aktif") }}</option>
                                    <option value="0">{{ __("Tidak aktif") }}</option>
                                </x-select>
                            </div>
                            @error("is_active")
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
                                <label for="quantity" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Qty") }}</label>
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
                    </div>
                </div>
            </div>
        </form>
    @endif
</div>
