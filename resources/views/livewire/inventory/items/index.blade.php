<?php

use App\Models\InvItem;
use App\Models\InvStock;
use App\Models\InvArea;
use App\Models\InvLoc;
use App\Models\InvTag;
use App\Models\InvCurr;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Carbon\Carbon;

new #[Layout('layouts.app')]
class extends Component
{
    use WithPagination;

    public int $perPage = 24;

    #[Url]
    public string $view = 'content';

    #[Url]
    public string $sort = 'updated';

    public array $areas = [];
    
    #[Url]
    public array $area_ids = [];

    #[Url]
    public array $tags = [];
    
    // public array $tag_hints = [];
    
    public string $loc_parent = '';    
    
    public string $loc_bin = '';
    
    public array $loc_parent_hints = [];
    
    public array $loc_bin_hints = [];
    
    #[Url]
    public string $q = '';

    #[Url]
    public string $name = '';

    #[Url]
    public string $desc = '';

    #[Url]
    public string $code = '';
    
    public array $qwords = []; // caldera: do you need it?

    #[Url]
    public string $filter = '';

    #[Url]
    public string $aging = '';

    #[Url]
    public bool $is_deleted = false;

    #[Url]
    public bool $is_linked = true;

    #[Url]
    public bool $ignore_params = false;

    public string $download_as = 'inv_stocks';

    public function mount()
    {        
        $this->areas = Auth::user()->auth_inv_areas();

        if (!$this->ignore_params) {
            $itemsParams = session('inv_items_params', []);

            if ($itemsParams) {
                $this->q            = $itemsParams['q'] ?? '';
                $this->name         = $itemsParams['name'] ?? '';
                $this->desc         = $itemsParams['desc'] ?? '';
                $this->code         = $itemsParams['code'] ?? '';
                $this->is_linked    = $itemsParams['is_linked'] ?? true;
                $this->loc_parent   = $itemsParams['loc_parent'] ?? '';
                $this->loc_bin      = $itemsParams['loc_bin'] ?? '';
                $this->tags         = $itemsParams['tags'] ?? [];
                $this->area_ids     = $itemsParams['area_ids'] ?? [];
                $this->filter       = $itemsParams['filter'] ?? '';
                $this->aging        = $itemsParams['aging'] ?? '';
                $this->view         = $itemsParams['view'] ?? 'content';
                $this->sort         = $itemsParams['sort'] ?? '';
            }
            
            $areasParam  = session('inv_areas_param', []);
    
            $areasParam 
            ? $this->area_ids = $areasParam ?? [] 
            : $this->area_ids = array_column($this->areas, 'id');
        }

        if($this->is_deleted) {
            $this->js('toast("' . __('Barang dihapus') . '", { type: "success" } )');
        }

    }

    public function with(): array
    {
        $q          = trim($this->q);
        $name       = trim($this->name);
        $desc       = trim($this->desc);
        $code       = trim($this->code);
        $loc_parent = trim($this->loc_parent);
        $loc_bin    = trim($this->loc_bin);
        $tags       = $this->tags;

        $inv_items_params = [
            'q'             => $q,
            'name'          => $name,
            'desc'          => $desc,
            'code'          => $code,
            'is_linked'     => $this->is_linked,
            'loc_parent'    => $loc_parent,
            'loc_bin'       => $loc_bin,
            'tags'          => $tags,
            'area_ids'      => $this->area_ids,
            'filter'        => $this->filter,
            'aging'         => $this->aging,
            'view'          => $this->view,
            'sort'          => $this->sort
        ];

        session(['inv_items_params' => $inv_items_params]);
        session(['inv_areas_param' => $this->area_ids]);

        $inv_stocks_query = InvStock::with([
            'inv_item', 
            'inv_curr',
            'inv_item.inv_loc', 
            'inv_item.inv_area', 
            'inv_item.inv_tags'
        ])
            ->whereHas('inv_item', function ($query) use ($q, $name, $desc, $code, $loc_parent, $loc_bin, $tags) {

        
            // items (if linked)
            if($this->is_linked) {
                $query->where(function ($subQuery) use ($q) {
                    $subQuery->where('name', 'like', "%$q%")
                             ->orWhere('code', 'like', "%$q%")
                             ->orWhere('desc', 'like', "%$q%");
                });

            } else {

                if($name) {
                    $query->where('name', 'like', "%$name%");
                }

                if($desc) {
                    $query->where('desc', 'like', "%$desc%");
                }

                if($code) {
                    $query->where('code', 'like', "%$code%");
                }

            }

            $query->whereIn('inv_area_id', $this->area_ids);

            // location
            $query->where(function ($subQuery) use ($loc_parent, $loc_bin) {
                if ($loc_parent || $loc_bin) {
                    $subQuery->whereHas('inv_loc', function ($subSubQuery) use ($loc_parent, $loc_bin) {
                        if ($loc_parent) {
                            $subSubQuery->where('parent', 'like', "%$loc_parent%");
                        }
                        if ($loc_bin) {
                            $subSubQuery->where('bin', 'like', "%$loc_bin%");
                        }
                    });
                }
            });

            // tags
            $query->where(function ($subQuery) use ($tags) {
                if (count($tags)) {
                    $subQuery->whereHas('inv_tags', function ($subSubQuery) {
                        $subSubQuery->whereIn('name', $this->tags);
                    });
                }
            });
            
            // filter
            switch ($this->filter) {
                case 'no-code':
                    $query->whereNull('code');
                    break;

                case 'no-photo':
                    $query->whereNull('photo');
                    break;

                case 'no-location':
                    $query->whereNull('inv_loc_id');
                    break;

                case 'no-tags':
                    $query->whereDoesntHave('inv_tags');
                    break;

                case 'inactive':
                    $query->where('is_active', false);
                    break;

                default:
                    $query->where('is_active', true);
                    break;
            }

            switch ($this->aging) {
                case 'gt-100-days':               
                case 'gt-90-days':
                case 'gt-60-days':
                case 'gt-30-days':
                case 'lt-30-days':
                    $now            = Carbon::now();
                    $sub_100_days   = $now->copy()->subDays(100);
                    $sub_90_days    = $now->copy()->subDays(90);
                    $sub_60_days    = $now->copy()->subDays(60);
                    $sub_30_days    = $now->copy()->subDays(30);
                    break;
            }

            
            // aging
            switch ($this->aging) {
                case 'gt-100-days':
                    $query->where(function ($q) use ($sub_100_days) {
                        $q->where('last_withdrawal', '<', $sub_100_days)
                        ->orWhereNull('last_withdrawal');
                    });
                    break;                        

                case 'gt-90-days':
                    $query->whereBetween('last_withdrawal', [$sub_100_days, $sub_90_days]);
                    break;

                case 'gt-60-days':
                    $query->whereBetween('last_withdrawal', [$sub_90_days, $sub_60_days]);
                    break;

                case 'gt-30-days':
                    $query->whereBetween('last_withdrawal', [$sub_60_days, $sub_30_days]);
                    break;

                case 'lt-30-days':
                    $query->where('last_withdrawal', '>', $sub_30_days);
                    break;
            }
        });  
        
        // active inv_stocks only
        $inv_stocks_query->where('is_active', true);        

        switch ($this->sort) {
            case 'updated':
                $inv_stocks_query->orderByRaw('
                (SELECT updated_at FROM inv_items 
                WHERE inv_items.id = inv_stocks.inv_item_id) DESC');
                break;
            case 'created':
                $inv_stocks_query->orderByRaw('
                (SELECT created_at FROM inv_items 
                WHERE inv_items.id = inv_stocks.inv_item_id) DESC');
                break;
            case 'loc':
                $inv_stocks_query->whereHas('inv_item.inv_loc');
                $inv_stocks_query->orderByRaw('
                (SELECT bin FROM inv_locs WHERE 
                inv_locs.id = (SELECT inv_loc_id FROM inv_items 
                WHERE inv_items.id = inv_stocks.inv_item_id)) ASC,

                (SELECT parent FROM inv_locs WHERE 
                inv_locs.id = (SELECT inv_loc_id FROM inv_items 
                WHERE inv_items.id = inv_stocks.inv_item_id)) ASC');
            case 'last_deposit':
                $inv_stocks_query->orderByRaw('
                (SELECT last_deposit FROM inv_items 
                WHERE inv_items.id = inv_stocks.inv_item_id) DESC');
                break;
            case 'last_withdrawal':
                $inv_stocks_query->orderByRaw('
                (SELECT last_withdrawal FROM inv_items 
                WHERE inv_items.id = inv_stocks.inv_item_id) DESC');
                break;
            case 'qty_low':
                $inv_stocks_query->orderBy('qty');
                break;
            case 'qty_high':
                $inv_stocks_query->orderByDesc('qty');
                break;
            case 'amt_low':
                $inv_stocks_query->orderBy('amount_main');
                break;
            case 'amt_high':
                $inv_stocks_query->orderByDesc('amount_main');
                break;
            case 'wf_low':
                $inv_stocks_query->orderBy('wf')->where('wf', '>', 0);
            case 'wf_high':
                $inv_stocks_query->orderByDesc('wf')->where('wf', '>', 0);
            case 'alpha':
                $inv_stocks_query->orderByRaw('
                (SELECT name FROM inv_items 
                WHERE inv_items.id = inv_stocks.inv_item_id) ASC');
                break;

        }

        $inv_stocks = $inv_stocks_query->paginate($this->perPage);

        return [
            'inv_stocks' => $inv_stocks,
        ];
    }

    public function download()
    {
        // Create a unique token for this download request
        $token = md5(uniqid());        

        switch ($this->download_as) {
            case 'inv_stocks':
                session()->put('inv_stocks_token', $token);
                return redirect()->route('download.inv-stocks', ['token' => $token]);

            case 'inv_items':
                session()->put('inv_items_token', $token);
                return redirect()->route('download.inv-items', ['token' => $token]);            
        }  
    }

    public function resetQuery()
    {
        session()->forget('inv_items_params');
        $this->redirect(route('inventory.items.index'), navigate: true);
    }

    public function loadMore()
    {
        $this->perPage += 24;
    }

    public function updated($property)
    {
        $resetProps = ['q', 'view', 'sort', 'area_ids', 'loc_parent', 'loc_bin', 'tags'];
        if(in_array($property, $resetProps)) {
            $this->reset(['perPage']);
        }

        if ($property == 'loc_parent') {
            $hint = trim($this->loc_parent);
            if ($hint) {
                $hints = InvLoc::where('parent', 'LIKE', '%' . $hint . '%')
                    ->orderBy('parent')
                    ->limit(100)
                    ->get()
                    ->pluck('parent')
                    ->toArray();
                $this->loc_parent_hints = array_unique($hints);
            }
        }

        if ($property == 'loc_bin') {
            $hint = trim($this->loc_bin);
            if ($hint) {
                $hints = InvLoc::where('bin', 'LIKE', '%' . $hint . '%')
                    ->orderBy('bin')
                    ->limit(100)
                    ->get()
                    ->pluck('bin')
                    ->toArray();
                $this->loc_bin_hints = array_unique($hints);
            }
        }
    }

};

?>

<x-slot name="title">{{ __('Cari') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory></x-nav-inventory>
</x-slot>

<div id="content" class="py-6 max-w-8xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div wire:key="modals">
        <x-modal name="create-from-code">
            <livewire:inventory.items.create-from-code :$areas lazy />
        </x-modal>
        <x-modal name="download" focusable>
            <div class="p-6 flex flex-col gap-y-6">
                <div class="flex justify-between items-start">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        <i class="icon-download mr-2"></i>
                        {{ __('Unduh sebagai...') }}
                    </h2>
                    <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
                </div>
                <div x-data="{ download_as: @entangle('download_as') }">
                    <x-radio x-model="download_as" id="as-inv_stocks" name="as-inv_stocks" value="inv_stocks">{{ __('Daftar unit stok') }}</x-radio>
                    <x-radio x-model="download_as" id="as-inv_items" name="as-inv_items" value="inv_items">{{  __('Daftar barang') }}</x-radio>
                </div>
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="download" x-on:click="$dispatch('close')">
                        <div class="relative">
                            <span wire:loading.class="opacity-0" wire:target="download"><i class="icon-download"></i><span class="ml-0 hidden md:ml-2 md:inline">{{ __('Unduh') }}</span></span>
                            <x-spinner wire:loading.class.remove="hidden" wire:target="download" class="hidden sm mono"></x-spinner>                
                        </div>  
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>
        <x-modal name="hidden-warning" focusable>
            <div class="p-6 flex flex-col gap-y-6">
                <div class="flex justify-between items-start">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Potensi barang tersembunyi') }}
                    </h2>
                    <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
                </div>
                <div class="text-sm">{{ __('Menggunakan penyortiran "Jarang diambil", "Sering diambil" dan "Lokasi" akan menyebabkan barang yang tidak memiliki frekuensi pengambilan atau lokasi menjadi tersaring.') }}</div>
                <div class="flex justify-end">
                    <x-primary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Paham') }}
                    </x-primary-button>
                </div>
            </div>
        </x-modal>
    </div>
    <div class="static lg:sticky top-0 z-10 py-6">
        <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2">
            <div x-data="{ is_linked: @entangle('is_linked').live }" class="flex gap-x-2 items-center px-8 py-2 lg:px-4 lg:py-0">
                <i wire:loading.remove class="icon-search {{ $q ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600' }}"></i>
                <i wire:loading class="relative">
                    <x-spinner class="sm mono"></x-spinner>
                </i>
                <div x-show="is_linked" class="w-full md:w-32">
                    <x-text-input-t wire:model.live="q" id="inv-q" name="inv-q" class="h-9 py-1 placeholder-neutral-400 dark:placeholder-neutral-600"
                        type="search" list="qwords" placeholder="{{ __('Cari...') }}" autofocus autocomplete="inv-q" />
                    <datalist id="qwords">
                        @if (count($qwords))
                            @foreach ($qwords as $qword)
                                <option value="{{ $qword }}">
                            @endforeach
                        @endif
                    </datalist>
                </div>
                <div x-cloak x-show="!is_linked" class="w-full md:w-24">
                    <x-text-input-t wire:model.live="name" id="inv-name" name="inv-name" class="h-9 py-1 placeholder-neutral-400 dark:placeholder-neutral-600"
                        type="search" placeholder="{{ __('Nama') }}" autocomplete="inv-name" />
                </div>
                <div x-cloak x-show="!is_linked" class="w-full md:w-28">
                    <x-text-input-t wire:model.live="desc" id="inv-desc" name="inv-desc" class="h-9 py-1 placeholder-neutral-400 dark:placeholder-neutral-600"
                        type="search" placeholder="{{ __('Deskripsi') }}" autocomplete="inv-desc" />
                </div>
                <div x-cloak x-show="!is_linked" class="w-full md:w-24">
                    <x-text-input-t wire:model.live="code" id="inv-code" name="inv-code" class="h-9 py-1 placeholder-neutral-400 dark:placeholder-neutral-600"
                        type="search" placeholder="{{ __('Kode') }}" autocomplete="inv-code" />
                </div>
                <x-text-button type="button" x-on:click="is_linked = !is_linked">
                    <i x-show="is_linked" class="icon-link-2"></i>
                    <i x-cloak x-show="!is_linked" class="icon-link-2-off"></i>
                </x-text-button>
            </div>            

            <div class="flex items-center gap-x-4 p-4 lg:py-0 ">
                <x-inv-loc-selector isQuery="true" class="text-xs font-semibold uppercase" />
            </div>

            <div class="flex items-center gap-x-4 p-4 lg:py-0 ">
                <x-inv-tag-selector isQuery="true" class="text-xs font-semibold uppercase" />
            </div>

            <div class="grow flex items-center gap-x-4 p-4 lg:py-0 ">
                <x-inv-items-filter class="text-xs font-semibold uppercase" />
            </div>

            <div class="flex items-center justify-between gap-x-4 p-4 lg:py-0">
                <x-inv-area-selector class="text-xs font-semibold uppercase" :$areas />
                <div>
                    <x-dropdown align="right" width="60">
                        <x-slot name="trigger">
                            <x-text-button><i class="icon-ellipsis"></i></x-text-button>
                        </x-slot>
                        <x-slot name="content">
                            @can('create', InvItem::class)
                                <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'create-from-code')">
                                    <i class="icon-plus me-2"></i>{{ __('Barang baru')}}
                                </x-dropdown-link>
                            @else
                            <x-dropdown-link href="#" disabled="true">
                                <i class="icon-plus me-2"></i>{{ __('Barang baru')}}
                            </x-dropdown-link>
                            @endcan
                            <hr class="border-neutral-300 dark:border-neutral-600" />
                            <x-dropdown-link href="{{ route('inventory.items.summary') }}" wire:navigate>
                                <i class="icon-chart-line me-2"></i>{{ __('Ringkasan barang')}}
                            </x-dropdown-link>
                            <!-- <x-dropdown-link href="#" disabled="true">
                                <i class="me-2"></i>{{ __('Perbarui massal')}}
                            </x-dropdown-link> -->
                            <x-dropdown-link href="{{ route('inventory.items.bulk-operation.index') }}" wire:navigate>
                                <i class="me-2"></i>{{ __('Operasi massal barang')}}
                            </x-dropdown-link>
                            <hr class="border-neutral-300 dark:border-neutral-600" />
                            <!-- <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')">
                                <i class="icon-map-pin me-2"></i>{{ __('Kelola lokasi ')}}
                            </x-dropdown-link>
                            <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')">
                                <i class="icon-tag me-2"></i>{{ __('Kelola tag ')}}
                            </x-dropdown-link>
                            <hr class="border-neutral-300 dark:border-neutral-600" /> -->
                            <x-dropdown-link href="#" wire:click.prevent="resetQuery">
                                <i class="icon-undo me-2"></i>{{ __('Reset')}}
                            </x-dropdown-link>
                            <hr class="border-neutral-300 dark:border-neutral-600" />
                            <!-- <x-dropdown-link href="#" wire:click.prevent="download('inv_stocks')">
                                <i class="icon-download me-2"></i>{{ __('Unduh sebagai CSV')}}
                            </x-dropdown-link> -->
                            <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'download')">
                                <i class="icon-download me-2"></i>{{ __('Unduh sebagai...') }}
                            </x-dropdown-link>
                            <!-- <x-dropdown-link href="#">
                                <i class="icon-download me-2"></i>{{ __('Unduh sebagai CSV') }}
                            </x-dropdown-link> -->
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </div>

    <div class="h-auto sm:h-12">
        <div class="flex items-center flex-col gap-y-6 sm:flex-row justify-between w-full h-full px-8">
            <div class="text-center sm:text-left">{{ $inv_stocks->total() . ' ' . __('barang') }}</div>
            <div class="grow flex items-center justify-center sm:justify-end">
                @if($sort == 'wf_low' || $sort == 'wf_high' || $sort == 'loc')
                <x-text-button type="button" x-on:click="$dispatch('open-modal', 'hidden-warning')" class="mr-3">
                    <i class="icon-triangle-alert text-yellow-500"></i>
                </x-text-button>
                @endif
                <x-select wire:model.live="sort" class="mr-3">
                    <option value="updated">{{ __('Diperbarui') }}</option>
                    <option value="created">{{ __('Dibuat') }}</option>
                    <option value="loc">{{ __('Lokasi') }}</option>
                    <option value="last_deposit">{{ __('Terakhir ditambah') }}</option>
                    <option value="last_withdrawal">{{ __('Terakhir diambil') }}</option>
                    <option value="qty_low">{{ __('Qty terendah') }}</option>
                    <option value="qty_high">{{ __('Qty tertinggi') }}</option>
                    <option value="amt_low">{{ __('Amount terendah') }}</option>
                    <option value="amt_high">{{ __('Amount tertinggi') }}</option>
                    <option value="wf_low">{{ __('Sering diambil') }}</option>
                    <option value="wf_high">{{ __('Jarang diambil') }}</option>
                    <option value="alpha">{{ __('Alfabet') }}</option>
                </x-select>
                <div class="btn-group">
                    <x-radio-button wire:model.live="view" value="list" name="view" id="view-list"><i
                            class="icon-align-justify text-center m-auto"></i></x-radio-button>
                    <x-radio-button wire:model.live="view" value="content" name="view" id="view-content"><i
                            class="icon-layout-list text-center m-auto"></i></x-radio-button>
                    <x-radio-button wire:model.live="view" value="grid" name="view" id="view-grid"><i
                            class="icon-layout-grid text-center m-auto"></i></x-radio-button>
                </div>
            </div>
        </div>
    </div>
    <div class="w-full  px-1">
        @if (!$inv_stocks->count())
            @if (count($area_ids))
                <div wire:key="no-match" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-ghost"></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">
                        {{ __('Tidak ada yang cocok') }}
                    </div>
                </div>
            @else
                <div wire:key="no-area" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="icon-house relative"><i
                                class="icon-circle-help absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih area') }}
                    </div>
                </div>
            @endif
        @else
            @switch($view)
                @case('grid')
                    <div wire:key="grid"
                        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-2 mt-6 px-3 sm:px-0">
                        @foreach ($inv_stocks as $inv_stock)
                            <x-inv-card-grid
                            :url="route('inventory.items.show', ['id' => $inv_stock->inv_item_id, 'stock_id' => $inv_stock->id ])"
                            :name="$inv_stock->inv_item->name" 
                            :desc="$inv_stock->inv_item->desc" 
                            :code="$inv_stock->inv_item->code"
                            :uom="$inv_stock->uom"
                            :loc="$inv_stock->inv_item->inv_loc_id ? ($inv_stock->inv_item->inv_loc->parent . '-' . $inv_stock->inv_item->inv_loc->bin ) : null" 
                            :qty="$inv_stock->qty" 
                            :photo="$inv_stock->inv_item->photo">
                            </x-inv-card-grid>
                        @endforeach
                    </div>
                @break

                @case('list')
                    <div wire:key="list" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto mt-6">
                        <table class="text-neutral-600 dark:text-neutral-400 w-full table text-sm [&_th]:text-center [&_th]:px-2 [&_th]:py-3 [&_td]:px-2 [&_td]:py-1">
                            <tr class="uppercase text-xs">
                                <th>{{ __('ID') }}</th>
                                <th></th>
                                <th>{{ __('Nama') }}</th>
                                <th>{{ __('Deskripsi') }}</th>
                                <th>{{ __('Kode') }}</th>
                                <th><i class="icon-map-pin"></i></th>
                                <th><i class="icon-tag"></i></th>
                                <th>{{ __('Qty') }}</th>
                                <th>{{ __('Harga') }}</th>
                                <th>{{ 'Σ (' . InvCurr::find(1)->name . ')' }}</th>
                                <th><i class="icon-house"></i></th>
                            </tr>
                            @foreach($inv_stocks as $inv_stock)
                                <tr class="text-nowrap">
                                    <td class="w-[1%]">{{ $inv_stock->inv_item->id }}</td>
                                    <td class="w-[1%]">
                                        <div class="rounded-sm overflow-hidden relative flex w-12 h-4 bg-neutral-200 dark:bg-neutral-700">
                                            <div class="m-auto">
                                                <svg xmlns="http://www.w3.org/2000/svg"  class="block w-4 h-4 fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793"><path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" /></svg>
                                            </div>
                                            @if($inv_stock->inv_item->photo)
                                                <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ '/storage/inv-items/' . $inv_stock->inv_item->photo }}" />
                                            @endif
                                        </div>   
                                    </td>
                                    <td class="max-w-40 truncate font-bold"><x-link href="{{ route('inventory.items.show', [ 'id' => $inv_stock->inv_item_id, 'stock_id' => $inv_stock->id ]) }}" wire:navigate>{{ $inv_stock->inv_item->name }}</x-link></td>
                                    <td class="max-w-40 truncate">{{ $inv_stock->inv_item->desc }}</td>
                                    <td>{{ $inv_stock->inv_item->code ?? '-' }}</td>
                                    <td class="w-[1%]">{{ $inv_stock->inv_item->inv_loc_id ? ($inv_stock->inv_item->inv_loc->parent . '-' .$inv_stock->inv_item->inv_loc->bin) : '-' }}</td>
                                    <td class="w-[1%]">{{ $inv_stock->inv_item->tags_facade() ?: '-' }}</td>
                                    <td>{{ $inv_stock->qty . ' ' . $inv_stock->uom }}</td>
                                    <td>{{ $inv_stock->inv_curr->name . ' ' . $inv_stock->unit_price }}</td>
                                    <td>{{ $inv_stock->amount_main }}</td>
                                    <td class="w-[1%]">{{ $inv_stock->inv_item->inv_area->name }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @break

                @default
                    <div wire:key="content" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-2 mt-6">
                        @foreach ($inv_stocks as $inv_stock)
                            <x-inv-card-content 
                            :url="route('inventory.items.show', ['id' => $inv_stock->inv_item_id, 'stock_id' => $inv_stock->id])"
                            :name="$inv_stock->inv_item->name" 
                            :desc="$inv_stock->inv_item->desc" 
                            :code="$inv_stock->inv_item->code"
                            :curr="$inv_stock->inv_curr->name" 
                            :price="$inv_stock->unit_price" 
                            :uom="$inv_stock->uom"
                            :loc="$inv_stock->inv_item->inv_loc_id ? ($inv_stock->inv_item->inv_loc->parent . '-' . $inv_stock->inv_item->inv_loc->bin ) : null" 
                            :tags="$inv_stock->inv_item->tags_facade() ?? null" 
                            :qty="$inv_stock->qty" 
                            :photo="$inv_stock->inv_item->photo">
                            </x-inv-card-content>
                        @endforeach
                    </div>
            @endswitch
            <div wire:key="observer" class="flex items-center relative h-16">
                @if (!$inv_stocks->isEmpty())
                    @if ($inv_stocks->hasMorePages())
                        <div wire:key="more" x-data="{
                            observe() {
                                const observer = new IntersectionObserver((inv_stocks) => {
                                    inv_stocks.forEach(inv_stock => {
                                        if (inv_stock.isIntersecting) {
                                            @this.loadMore()
                                        }
                                    })
                                })
                                observer.observe(this.$el)
                            }
                        }" x-init="observe"></div>
                        <x-spinner class="sm" />
                    @else
                        <div class="mx-auto">{{ __('Tidak ada lagi') }}</div>
                    @endif
                @endif
            </div>
        @endif
    </div>
</div>
