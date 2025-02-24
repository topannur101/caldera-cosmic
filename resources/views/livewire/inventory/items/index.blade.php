<?php

use App\Models\InvItem;
use App\Models\InvStock;
use App\Models\InvArea;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

new #[Layout('layouts.app')]
class extends Component
{
    use WithPagination;

    public int $perPage = 24;

    public string $view = 'content';

    public string $sort = '';

    public array $areas = [];
    
    public array $area_ids = [];

    public string $loc_parent = '';

    public array $loc_parents = [];
    
    public string $loc_bin = '';

    public array $loc_bins = [];

    public array $tags = [];
    
    #[Url]
    public string $q = '';
    
    public array $qwords = [];

    public string $filter = '';

    public function mount()
    {
        $user_id = Auth::user()->id;

        if ($user_id === 1) {
            $areas = InvArea::all();
        } else {
            $user = User::find($user_id);
            $areas = $user->inv_areas;
        }

        $this->areas = $areas->toArray();

        $savedParams = session('inv_search_params', []);
    
        if ($savedParams) {
            $this->q            = $savedParams['q'] ?? '';
            $this->loc_parent   = $savedParams['loc_parent'] ?? '';
            $this->loc_bin      = $savedParams['loc_bin'] ?? '';
            $this->tags         = $savedParams['tags'] ?? [];
            $this->area_ids     = $savedParams['area_ids'] ?? [];
            $this->filter       = $savedParams['filter'] ?? '';
            $this->view         = $savedParams['view'] ?? 'content';
        } else {
            $this->area_ids     = $areas->pluck('id')->toArray();
        }


    }

    public function with(): array
    {
        $q          = trim($this->q);
        $loc_parent = trim($this->loc_parent);
        $loc_bin    = trim($this->loc_bin);
        $tags       = $this->tags;

        $inv_search_params = [
            'q'             => $q,
            'loc_parent'    => $loc_parent,
            'loc_bin'       => $loc_bin,
            'tags'          => $tags,
            'area_ids'      => $this->area_ids,
            'filter'        => $this->filter,
            'view'          => $this->view
        ];
        
        session(['inv_search_params' => $inv_search_params]);

        $inv_search_query = InvStock::with([
            'inv_item', 
            'inv_curr',
            'inv_item.inv_loc', 
            'inv_item.inv_area', 
            'inv_item.inv_tags'
        ])
        ->whereHas('inv_item', function ($query) use ($q, $loc_parent, $loc_bin, $tags) {
            // search
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%$q%")
                         ->orWhere('code', 'like', "%$q%")
                         ->orWhere('desc', 'like', "%$q%");
            })
            ->whereIn('inv_area_id', $this->area_ids);

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
        })
        ->where('is_active', true);

        $inv_stocks = $inv_search_query->paginate($this->perPage);

        return [
            'inv_stocks' => $inv_stocks,
        ];
    }

    public function resetSearch()
    {
        session()->forget('inv_search_params');
        $this->redirect(route('inventory.items.index'), navigate: true);
    }
};

?>



<x-slot name="title">{{ __('Cari') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory></x-nav-inventory>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div wire:key="modals">
        <x-modal name="create-from-code">
            <livewire:inventory.items.create-from-code :$areas lazy />
        </x-modal>
    </div>
    <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2 mb-6">
        <div class="flex gap-x-2 items-center px-8 py-2 lg:px-4 lg:py-0">
            <i wire:loading.remove class="fa fa-fw fa-search {{ $q ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600' }}"></i>
            <i wire:loading class="fa fa-fw relative">
                <x-spinner class="sm mono"></x-spinner>
            </i>
            <div class="w-full md:w-40">
                <x-text-input-t wire:model.live="q" id="inv-q" name="inv-q" class="py-1 placeholder-neutral-400 dark:placeholder-neutral-600"
                    type="search" list="qwords" placeholder="{{ __('Cari...') }}" autofocus autocomplete="inv-q" />
                <datalist id="qwords">
                    @if (count($qwords))
                        @foreach ($qwords as $qword)
                            <option value="{{ $qword }}">
                        @endforeach
                    @endif
                </datalist>
            </div>
        </div>

        <div class="flex items-center gap-x-4 p-4 lg:py-0 ">
            <x-inv-loc-selector isQuery="true" class="text-xs font-semibold uppercase" />
        </div>

        <div class="flex items-center gap-x-4 p-4 lg:py-0 ">
            <x-inv-tag-selector isQuery="true" class="text-xs font-semibold uppercase" />
        </div>

        <div class="grow flex items-center gap-x-4 p-4 lg:py-0 ">
            <x-inv-search-filter class="text-xs font-semibold uppercase" />
        </div>

        <div class="flex items-center justify-between gap-x-4 p-4 lg:py-0">
            <x-inv-area-selector class="text-xs font-semibold uppercase" :$areas />
            <div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <x-text-button><i class="fa fa-fw fa-ellipsis-h"></i></x-text-button>
                    </x-slot>
                    <x-slot name="content">
                        @can('create', InvItem::class)
                            <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'create-from-code')">
                                <i class="fa fa-fw fa-plus me-2"></i>{{ __('Barang baru')}}
                            </x-dropdown-link>
                        @else
                        <x-dropdown-link href="#" disabled="true">
                            <i class="fa fa-fw fa-plus me-2"></i>{{ __('Barang baru')}}
                        </x-dropdown-link>
                        @endcan
                        <x-dropdown-link href="{{ route('inventory.items.create') }}" wire:navigate>
                            <i class="fa fa-fw me-2"></i>{{ __('Perbarui massal')}}
                        </x-dropdown-link>
                        <hr class="border-neutral-300 dark:border-neutral-600" />
                        <!-- <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')">
                            <i class="fa fa-fw fa-map-marker-alt me-2"></i>{{ __('Kelola lokasi ')}}
                        </x-dropdown-link>
                        <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')">
                            <i class="fa fa-fw fa-tag me-2"></i>{{ __('Kelola tag ')}}
                        </x-dropdown-link>
                        <hr class="border-neutral-300 dark:border-neutral-600" /> -->
                        <x-dropdown-link href="#" wire:click.prevent="resetSearch">
                            <i class="fa fa-fw fa-undo me-2"></i>{{ __('Reset pencarian')}}
                        </x-dropdown-link>
                        <hr class="border-neutral-300 dark:border-neutral-600" />
                        <x-dropdown-link href="#">
                            <i class="fa fa-fw fa-download me-2"></i>{{ __('Unduh sebagai CSV') }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
    <div class="w-full">
        <div class="flex items-center flex-col gap-y-6 sm:flex-row justify-between w-full px-8">
            <div class="text-center sm:text-left">{{ $inv_stocks->total() . ' ' . __('barang') }}</div>
            <div class="grow flex justify-center sm:justify-end">
                <x-select wire:model.live="sort" class="mr-3">
                    <option value="updated">{{ __('Diperbarui') }}</option>
                    <option value="created">{{ __('Dibuat') }}</option>
                    <option value="price_low">{{ __('Termurah') }}</option>
                    <option value="price_high">{{ __('Termahal') }}</option>
                    <option value="qty_low">{{ __('Paling sedikit') }}</option>
                    <option value="qty_high">{{ __('Paling banyak') }}</option>
                    <option value="alpha">{{ __('Abjad') }}</option>
                </x-select>
                <div class="btn-group">
                    <x-radio-button wire:model.live="view" value="list" name="view" id="view-list"><i
                            class="fa fa-fw fa-grip-lines text-center m-auto"></i></x-radio-button>
                    <x-radio-button wire:model.live="view" value="content" name="view" id="view-content"><i
                            class="fa fa-fw fa-list text-center m-auto"></i></x-radio-button>
                    <x-radio-button wire:model.live="view" value="grid" name="view" id="view-grid"><i
                            class="fa fa-fw fa-border-all text-center m-auto"></i></x-radio-button>
                </div>
            </div>
        </div>
        <div wire:loading.class="cal-shimmer">
            @if (!$inv_stocks->count())
                @if (count($area_ids))
                    <div wire:key="no-match" class="py-20">
                        <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                            <i class="fa fa-ghost"></i>
                        </div>
                        <div class="text-center text-neutral-400 dark:text-neutral-600">
                            {{ __('Tidak ada yang cocok') }}
                        </div>
                    </div>
                @else
                    <div wire:key="no-area" class="py-20">
                        <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                            <i class="fa fa-tent relative"><i
                                    class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
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
                                :uom="$inv_stock->uom"
                                :loc="$inv_stock->inv_item->inv_loc_id ? ($inv_stock->inv_item->inv_loc->parent . '-' . $inv_stock->inv_item->inv_loc->bin ) : null" 
                                :qty="$inv_stock->qty" 
                                :photo="$inv_stock->inv_item->photo ? '/storage/inv-items/' . $inv_stock->inv_item->photo : null">
                                </x-inv-card-grid>
                            @endforeach
                        </div>
                    @break

                    @case('list')
                        <div wire:key="list" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto mt-6">
                            <table class="table table-sm table-truncate text-neutral-600 dark:text-neutral-400">
                                <tr class="uppercase text-xs">
                                    <th>{{ __('Qty') }}</th>
                                    <th>{{ __('Nama') }}</th>
                                    <th>{{ __('Kode') }}</th>
                                    <th>{{ __('Harga') }}</th>
                                    <th>{{ __('Lokasi') }} </th>
                                    <th>{{ __('Tag') }} </th>
                                    <th>{{ __('Area') }}</th>
                                </tr>
                                @foreach($inv_stocks as $inv_stock)
                                    <tr>
                                        <td>{{ $inv_stock->qty . ' ' . $inv_stock->uom }}</td>
                                        <td class="font-bold"><x-link href="{{ route('inventory.items.show', [ 'id' => $inv_stock->inv_item_id, 'stock_id' => $inv_stock->id ]) }}" wire:navigate>{{ $inv_stock->inv_item->name }}</x-link></td>
                                        <td>{{ $inv_stock->inv_item->desc }}</td>
                                        <td>{{ $inv_stock->inv_item->code ?? __('Tanpa kode') }}</td>
                                        <td>{{ $inv_stock->inv_item->inv_loc_id ? ($inv_stock->inv_item->inv_loc->parent . '-' .$inv_stock->inv_item->inv_loc->bin) : __('Tanpa lokasi') }}</td>
                                        <td>{{ $inv_stock->inv_item->tags_facade() ?? null }}</td>
                                        <td>{{ $inv_stock->inv_item->inv_area->name }}</td>
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
                                :price="$inv_stock->inv_item->price" 
                                :uom="$inv_stock->uom"
                                :loc="$inv_stock->inv_item->inv_loc_id ? ($inv_stock->inv_item->inv_loc->parent . '-' . $inv_stock->inv_item->inv_loc->bin ) : null" 
                                :tags="$inv_stock->inv_item->tags_facade() ?? null" 
                                :qty="$inv_stock->qty" 
                                :photo="$inv_stock->inv_item->photo ? '/storage/inv-items/' . $inv_stock->inv_item->photo : null">
                                </x-inv-card-content>
                            @endforeach
                        </div>
                @endswitch
            @endif
        </div>
    </div>
</div>
