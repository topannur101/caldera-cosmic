<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\InvCeAuth;
use App\Traits\HasDateRangeFilter;
use App\Models\InvCeCircs;
use App\Models\InvCeChemical;
use App\Models\InvCeVendor;
use App\Services\HtmlToArrayService;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;


new #[Layout("layouts.app")] class extends Component {
    use HasDateRangeFilter;
    use WithFileUploads;

    public string $start_at = "";
    public string $end_at = "";
    public int $perPage = 10;
    public $importFile = null;
    public bool $importing = false;
    public ?array $importResult = null;
    public array $importErrors = [];

    public function mount() {
        // set default date today
        if (!$this->start_at) {
            $this->start_at = now()->toDateString();
        }
        if (!$this->end_at) {
            $this->end_at = now()->toDateString();
        }
    }

    // get data
    private function getCountsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InvCeCircs::whereBetween("created_at", [$start, $end]);
        return $query->orderBy("created_at", "DESC");
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }

    public function rules()
    {
        return [
            "importFile" => ["required", "file", "mimes:html,htm", "max:10240"],
        ];
    }

    public function importHtml()
    {
        $this->validate();

        $this->importing = true;
        $this->importErrors = [];
        $this->importResult = null;

        try {
            $path = $this->importFile->store("temp", "local");
            $fullPath = storage_path("app/" . $path);

            $service = new HtmlToArrayService();
            $result = $service->extract($fullPath);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            if (!($result["success"] ?? false)) {
                $this->importErrors = $result["errors"] ?? [__("Gagal parsing file HTML")];
                $this->js('toast("' . ($result["message"] ?? __("Import gagal")) . '", { type: "error" })');
                return;
            }

            $saved = $this->saveToDatabase($result["rows"] ?? []);

            $this->importResult = [
                "title" => $result["title"] ?? "",
                "rows" => $result["total_rows"] ?? 0,
                "columns" => count($result["headers"] ?? []),
                "headers" => $result["headers"] ?? [],
                "created_chemicals" => $saved["created"],
                "existing_chemicals" => $saved["existing"],
                "skipped_rows" => $saved["skipped"],
            ];

            $this->js('toast("' . __("Import HTML berhasil") . '", { type: "success" })');
            $this->dispatch("updated");
        } catch (\Throwable $e) {
            Log::error("CE HTML import error", ["error" => $e->getMessage()]);
            $this->importErrors = [$e->getMessage()];
            $this->js('toast("Error: ' . $e->getMessage() . '", { type: "error" })');
        } finally {
            $this->importing = false;
        }
    }

    public function resetImportForm()
    {
        $this->reset(["importFile", "importResult"]);
        $this->importErrors = [];
    }

    public function saveToDatabase($data): array
    {
        $defaultVendorId = InvCeVendor::query()->value("id");
        if (!$defaultVendorId) {
            throw new \Exception("No vendor found in inv_ce_vendors. Please create at least 1 vendor before import.");
        }

        $created = 0;
        $existing = 0;
        $skipped = 0;

        // Save only new chemicals by item_code.
        foreach ($data as $row) {
            $itemCode = trim((string) ($row["item_cd"] ?? $row["item_code"] ?? ""));
            if ($itemCode === "") {
                $skipped++;
                continue;
            }

            $name = trim((string) ($row["item_nm"] ?? $row["name"] ?? $row["descriptions"] ?? ""));
            $uom = trim((string) ($row["uom"] ?? ""));

            $chemical = InvCeChemical::firstOrCreate(
                ["item_code" => $itemCode],
                [
                    "name" => $name !== "" ? $name : $itemCode,
                    "inv_ce_vendor_id" => $defaultVendorId,
                    "uom" => $uom !== "" ? $uom : "G",
                    "category_chemical" => "single",
                    "is_active" => true,
                    "status_bom" => false,
                ]
            );

            if ($chemical->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        return [
            "created" => $created,
            "existing" => $existing,
            "skipped" => $skipped,
        ];
    }

    #[On("updated")]
    public function with(): array
    {
        $counts = $this->getCountsQuery()->paginate($this->perPage);
        return [
            "counts" => $counts,
        ];
    }

} ?>

<x-slot name="title">{{ __("Cari") . " — " . __("Inventaris") }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-ce></x-nav-inventory-ce>
</x-slot>

<div class="py-6 max-w-8xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200 gap-4">
    <!-- filter section -->
    <div class="static lg:sticky top-0 z-10 py-2">
        <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 px-4 py-3 lg:py-0 lg:px-5">
                <div class="flex items-center gap-2 px-3 py-2 flex-1">
                    <span class="icon-search text-neutral-500"></span>
                    <input type="text" id="search" wire:model.live="search" placeholder="{{ __("Cari berdasarkan item code, deskripsi, atau user") }}" class="w-full border-0 bg-transparent p-0 text-sm text-neutral-700 outline-none focus:ring-0 dark:text-neutral-100">
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-700">
                    <label for="start_at" class="whitespace-nowrap text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">{{ __("Dari") }}</label>
                    <input type="date" id="start_at" wire:model.live="start_at" class="border-0 bg-transparent p-0 text-sm text-neutral-700 outline-none focus:ring-0 dark:text-neutral-100">
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-700">
                    <label for="end_at" class="whitespace-nowrap text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400">{{ __("Sampai") }}</label>
                    <input type="date" id="end_at" wire:model.live="end_at" class="border-0 bg-transparent p-0 text-sm text-neutral-700 outline-none focus:ring-0 dark:text-neutral-100">
                </div>
            </div>

            <div class="flex items-center px-4 py-3 lg:py-0 lg:px-4">
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <x-text-button class="h-9 rounded-lg border border-neutral-200 px-3 text-xs font-semibold uppercase text-neutral-700 dark:border-neutral-700 dark:text-neutral-200">
                            {{ __("Tipe") }}
                            <i class="icon-chevron-down ms-1"></i>
                        </x-text-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="#" wire:click.prevent="">
                            {{ __("New") }}
                        </x-dropdown-link>
                        <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                            {{ __("Return") }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>

    <!-- button add -->
     <div class="flex justify-end w-full mt-4">
        <button type="button" x-on:click.prevent="$dispatch('open-modal', 'circ-import-html')" class="inline-flex items-center gap-2 rounded-3xl border border-caldy-500 bg-caldy-500/10 px-3 py-2 text-xs font-semibold uppercase text-caldy-500 hover:bg-caldy-500/20">
            <i class="icon-plus"></i>
        </button>
     </div>

    <!-- table section -->
    <div>
        <table class="table table-sm text-sm mt-4 table-truncate text-neutral-600 dark:text-neutral-400 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg divide-y divide-neutral-200 dark:divide-neutral-700">
            <tr class="uppercase text-xs">
                <th>{{ __("Plant") }}</th>
                <th>{{ __("Area") }}</th>
                <th>{{ __("Item Code") }}</th>
                <th>{{ __("Descriptions") }}</th>
                <th>{{ __("UOM") }}</th>
                <th>{{ __("Qty") }}</th>
                <th>{{ __("Lot Number") }}</th>
                <th>{{ __("Exp Date")}}</th>
                <th>{{ __("User") }}</th>
                <th>{{ __("Updated At") }}</th>
            </tr>
             
            @if ($counts->count() > 0)
                @foreach ($counts as $count)
                    <tr wire:key="count-tr-{{ $count->id }}" class="hover:bg-neutral-50 dark:hover:bg-neutral-700">
                        <td>{{ $count->plant }}</td>
                        <td>{{ $count->area }}</td>
                        <td>{{ $count->item_code }}</td>
                        <td>{{ $count->descriptions }}</td>
                        <td>{{ $count->uom }}</td>
                        <td>{{ $count->qty }}</td>
                        <td>{{ $count->lot_number }}</td>
                        <td>{{ $count->exp_date }}</td>
                        <td>{{ $count->user ? $count->user->name : "-" }}</td>
                        <td>{{ $count->created_at }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="10" class="text-center py-10">
                        <div class="text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                            <i class="icon-folder-open"></i>
                        </div>
                        <div class="text-neutral-400 dark:text-neutral-600">{{ __("Tidak ada data sirkulasi") }}</div>
                    </td>
                </tr>
            @endif
        </table>
    </div>

    <x-modal name="circ-import-html" maxWidth="lg">
        <form wire:submit="importHtml" class="p-6">
            <div class="flex justify-between items-start pb-4 border-b border-neutral-200 dark:border-neutral-700">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __("Import HTML") }}
                </h2>
                <x-text-button type="button" wire:click="resetImportForm" x-on:click="$dispatch('close-modal', 'circ-import-html')"><i class="icon-x"></i></x-text-button>
            </div>

            <div class="mt-4">
                <label for="import-file-html" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                    {{ __("Pilih File HTML") }} *
                </label>
                <input
                    type="file"
                    id="import-file-html"
                    wire:model="importFile"
                    accept=".html,.htm"
                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                >
                @error("importFile")
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            @if ($importFile)
                <div class="mt-3 p-3 bg-neutral-50 dark:bg-neutral-900 rounded-lg">
                    <div class="flex items-center gap-2">
                        <i class="icon-file text-neutral-500"></i>
                        <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ $importFile->getClientOriginalName() }}</span>
                        <span class="text-xs text-neutral-500">({{ round($importFile->getSize() / 1024) }} KB)</span>
                    </div>
                </div>
            @endif

            @if (!empty($importErrors))
                <div class="mt-4 p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
                    <h4 class="text-sm font-medium text-red-800 dark:text-red-200">{{ __("Errors") }}:</h4>
                    <ul class="mt-2 list-disc list-inside text-sm text-red-700 dark:text-red-300">
                        @foreach ($importErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($importResult)
                <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/30 rounded-lg text-sm text-green-700 dark:text-green-300">
                    <div><span class="font-medium">{{ __("Title") }}:</span> {{ $importResult["title"] ?: "-" }}</div>
                    <div><span class="font-medium">{{ __("Rows") }}:</span> {{ $importResult["rows"] }}</div>
                    <div><span class="font-medium">{{ __("Columns") }}:</span> {{ $importResult["columns"] }}</div>
                    <div><span class="font-medium">{{ __("New Chemicals") }}:</span> {{ $importResult["created_chemicals"] ?? 0 }}</div>
                    <div><span class="font-medium">{{ __("Existing Chemicals") }}:</span> {{ $importResult["existing_chemicals"] ?? 0 }}</div>
                    <div><span class="font-medium">{{ __("Skipped Rows") }}:</span> {{ $importResult["skipped_rows"] ?? 0 }}</div>
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" wire:click="resetImportForm" x-on:click="$dispatch('close-modal', 'circ-import-html')">
                    {{ __("Batal") }}
                </x-secondary-button>
                <x-primary-button type="submit" wire:disabled="importing">
                    @if ($importing)
                        <i class="icon-loader animate-spin mr-1"></i> {{ __("Mengimport...") }}
                    @else
                        <i class="icon-upload mr-1"></i> {{ __("Upload") }}
                    @endif
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>