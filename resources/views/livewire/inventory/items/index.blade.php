<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

use App\Inventory;
use App\Models\Pref;
use App\Models\User;
use App\Models\InvLoc;
use App\Models\InvTag;
use League\Csv\Writer;
use App\Models\InvArea;
use App\Models\InvCurr;
use App\Models\InvItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] 
class extends Component {

  use WithPagination;
  #[Url]
  public $q = '';
  public $qwords = [];
  #[Url]
  public $status = 'active';
  #[Url]
  public $qty = 'total';
  #[Url]
  public $filter = false;
  #[Url]
  public $loc = '';
  public $qlocs = [];
  #[Url]
  public $tag = '';
  public $qtags = [];
  #[Url]
  public $without = '';
  public $areas;
  #[Url]
  public $area_ids = [];
  public $area_ids_clean = [];
  #[Url]
  public $sort = 'updated';
  #[Url]
  public $view = 'content';
  public $inv_curr;
  public $perPage = 24;

  public function mount()
  {
      $user = User::find(Auth::user()->id);
      // check for superuser
      $this->areas = $user->id === 1 ? InvArea::all() : $user->inv_areas;

      $pref = Pref::where('user_id', $user->id)->where('name', 'inv-search')->first();
      $pref = json_decode($pref->data ?? '{}', true);
      $this->q        = isset($pref['q'])         ? $pref['q']        : '';
      $this->status   = isset($pref['status'])    ? $pref['status']   : 'active';
      $this->qty      = isset($pref['qty'])       ? $pref['qty']      : 'total';
      $this->filter   = isset($pref['filter'])    ? $pref['filter']   : 'false';
      $this->loc      = isset($pref['loc'])       ? $pref['loc']      : '';
      $this->tag      = isset($pref['tag'])       ? $pref['tag']      : '';
      $this->without  = isset($pref['without'])   ? $pref['without']  : '';
      $this->area_ids = isset($pref['area_ids'])  ? $pref['area_ids'] : $this->areas->pluck('id')->toArray();
      $this->sort     = isset($pref['sort'])      ? $pref['sort']     : 'updated';
      $this->view     = isset($pref['view'])      ? $pref['view']     : 'content';

      $this->inv_curr = InvCurr::find(1);
  }

  public function resetSearch()
    {
        // reset according user access rights
        $this->area_ids = ['1'];
        $this->reset('q', 'status', 'qty', 'filter', 'loc', 'tag', 'without');
    }

    public function download()
    {
      $this->redirectRoute('download.inv-items', []);
      $this->js('$dispatch("close")');
      $this->js('notyfSuccess("' . __('Pengunduhan dimulai...') . '")');
    }

    public function updatedLoc()
    {
        $qloc = trim($this->loc);
        $qlocs = InvLoc::whereIn('inv_locs.inv_area_id', $this->area_ids)
            ->where('name', 'LIKE', '%' . $qloc . '%')
            ->orderBy('name')
            ->take(100)
            ->get()
            ->pluck('name');
        $this->qlocs = $qlocs->toArray();
    }

    public function updatedTag()
    {
        $qtag = trim($this->tag);
        $qtags = InvTag::whereIn('inv_tags.inv_area_id', $this->area_ids)
            ->where('name', 'LIKE', '%' . $qtag . '%')
            ->orderBy('name')
            ->take(100)
            ->get()
            ->pluck('name');
        $this->qtags = $qtags->toArray();
    }

    public function updatedQ()
    {
        $keyword = trim($this->q);
        // Fetch items that contain the keyword in their name column
        if ($keyword) {
            $inv_items = InvItem::select('name', 'desc', 'code')->whereIn('inv_area_id', $this->area_ids)
                ->where(function (Builder $query) use ($keyword) {
                    $query->orWhere('name', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('desc', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('code', 'LIKE', '%' . $keyword . '%');
                })->limit(100)->get()->toArray();
            $inv_items = Arr::flatten($inv_items);
            $suggestions = [];

            // Extract individual words from retrieved names
            foreach ($inv_items as $name) {
                $words = explode(' ', strtolower($name));
                foreach ($words as $word) {
                    // Check if the word starts with the keyword
                    if (stripos($word, $keyword) !== false) {
                        $suggestions[] = $word;
                    }
                }
            }
            // Filter and return unique suggestions
            $suggestions = array_values(array_unique($suggestions));
            sort($suggestions);
            $this->qwords = $suggestions;
        }
    }

    public function with(): array
    {
        // cleanup areas
        $area_ids_set    = $this->area_ids;
        $area_ids_auth   = $this->areas->pluck('id')->toArray();
        
        // chosen area and authorized area
        $area_ids = array_intersect($area_ids_set, $area_ids_auth);
        $this->area_ids_clean = array_values($area_ids);

        $inv_items = Inventory::itemsBuild(
            $this->area_ids_clean,
            $this->q,
            $this->status,
            $this->filter,
            $this->loc,
            $this->tag,
            $this->without,
            $this->sort,
            $this->qty
        );

        $inv_items = $inv_items->paginate($this->perPage);

        // remember preferences
        $pref = Pref::updateOrCreate(
            ['user_id' => Auth::user()->id, 'name' => 'inv-search'],
            ['data' => json_encode([
                'q'         => $this->q,
                'status'    => $this->status,
                'qty'       => $this->qty,
                'filter'    => $this->filter,
                'loc'       => $this->loc,
                'tag'       => $this->tag,
                'without'   => $this->without,
                'area_ids'  => $this->area_ids,
                'sort'      => $this->sort,
                'view'      => $this->view,
            ])]
        );

        // update: please restrict area ids according to authorization

        return [
          'inv_items' => $inv_items
        ];
    }
  
};

?>

<x-slot name="title">{{ __('Cari') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory></x-nav-inventory>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">

    <div class="flex flex-col gap-x-4 md:gap-x-8 sm:flex-row">
        <div>
            <div class="w-full sm:w-44 md:w-56 px-3 sm:px-0">
                <x-text-input-icon wire:model.live="q" icon="fa fa-fw fa-search" id="inv-q" name="inv-q"
                    type="search" list="qwords" placeholder="{{ __('Cari...') }}" autofocus autocomplete="inv-q" />
                <datalist id="qwords">
                    @if (count($qwords))
                        @foreach ($qwords as $qword)
                            <option value="{{ $qword }}">
                        @endforeach
                    @endif
                </datalist>
                <x-select wire:model.live="status" class="w-full mt-4">
                    <option value="active">{{ __('Barang aktif') }}</option>
                    <option value="inactive">{{ __('Barang nonaktif') }}</option>
                    <option value="both">{{ __('Aktif dan nonaktif') }}</option>
                </x-select>
                <x-select wire:model.live="qty" class="w-full mt-4">
                    <option value="total">{{ __('Qty total') }}</option>
                    <option value="main">{{ __('Qty utama saja') }}</option>
                    <option value="used">{{ __('Qty bekas saja') }}</option>
                    <option value="rep">{{ __('Qty diperbaiki saja') }}</option>
                </x-select>
                <div x-data="{ filter: @entangle('filter').live }" class="mt-4 bg-white dark:bg-neutral-800 shadow rounded-lg py-5 px-4">
                    <div class="flex items-start justify-between">
                        <x-toggle x-model="filter">{{ __('Filter') }}</x-toggle>
                        <div class="flex items-center">
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <x-text-button><i class="fa fa-fw fa-ellipsis-v"></i></x-text-button>
                                </x-slot>
                                <x-slot name="content">
                                    <div class="text-sm text-neutral-400 dark:text-neutral-500 p-6 text-center">
                                        {{ __('Tak ada lokasi dan tag favorit') }}</div>
                                    {{-- <x-dropdown-link href="#" class="flex items-center">
                                    <i class="fa fa-tag fa-fw mr-2"></i>
                                    <div>general-electrical-something</div>
                                </x-dropdown-link>
                                <x-dropdown-link href="#" class="flex items-center">
                                    <i class="fa fa-tag fa-fw mr-2"></i>
                                    <div>okc</div>
                                </x-dropdown-link>
                                <x-dropdown-link href="#" class="flex items-center">
                                    <i class="fa fa-map-marker-alt fa-fw mr-2"></i>
                                    <div>G1-2-3</div>
                                </x-dropdown-link> --}}
                                    <hr class="border-neutral-300 dark:border-neutral-600" />
                                    <x-dropdown-link href="#"
                                        x-on:click.prevent="$dispatch('open-modal', 'favs-manage')">
                                        {{ __('Kelola') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                            <x-modal name="favs-manage">
                                <livewire:layout.inv-favs-manage wire:key="favs-manage" lazy />
                            </x-modal>
                        </div>
                    </div>
                    <div x-show="filter === true ? true : false" x-cloak x-init="console.log(filter)">
                        <x-text-input-icon wire:model.live="loc" icon="fa fa-fw fa-map-marker-alt" id="inv-loc"
                            class="mt-4" type="search" placeholder="{{ __('Lokasi') }}" list="qlocs" />
                        <datalist id="qlocs">
                            @if (count($qlocs))
                                @foreach ($qlocs as $qloc)
                                    <option wire:key="{{ 'qloc' . $loop->index }}" value="{{ $qloc }}">
                                @endforeach
                            @endif
                        </datalist>
                        <x-text-input-icon wire:model.live="tag" icon="fa fa-fw fa-tag" class="mt-4" id="inv-tag"
                            type="search" placeholder="{{ __('Tag') }}" list="qtags" />
                        <datalist id="qtags">
                            @if (count($qtags))
                                @foreach ($qtags as $qtag)
                                    <option wire:key="{{ 'qtag' . $loop->index }}" value="{{ $qtag }}">
                                @endforeach
                            @endif
                        </datalist>
                        <x-select wire:model.live="without" name="filter" class="mt-4">
                            <option value=""></option>
                            <option value="loc">{{ __('Tak ada lokasi') }}</option>
                            <option value="tags">{{ __('Tak ada tag') }}</option>
                            <option value="photo">{{ __('Tak ada foto') }}</option>
                            <option value="code">{{ __('Tak ada kode') }}</option>
                            <option value="qty_min">{{ __('Tak ada min qty utama') }}</option>
                            <option value="qty_max">{{ __('Tak ada maks qty utama') }}</option>
                        </x-select>
                    </div>
                </div>
            </div>
            <div class="sticky top-0 py-5 opacity-0 sm:opacity-100">
                <div class="bg-white dark:bg-neutral-800 shadow rounded-lg py-3 px-4">
                    @foreach ($areas as $area)
                        <div class="my-2">
                            <x-checkbox wire:model.live="area_ids" wire:key="inv-area-{{ $area->id }}"
                                id="inv-area-{{ $area->id }}"
                                value="{{ $area->id }}">{{ $area->name }}</x-checkbox>
                        </div>
                    @endforeach
                </div>
                <div class="py-4">
                    <div wire:key="reset-search">
                        @if ($q || $status != 'active' || $qty != 'total' || $filter || $loc || $tag || $without)
                            <div class="m-3">
                                <x-text-button wire:click="resetSearch" type="button" class="text-sm"><i
                                        class="fa fa-fw mr-2 fa-undo"></i>{{ __('Atur ulang') }}</x-text-button>
                            </div>
                        @endif
                    </div>
                    <div class="m-3">
                        <x-text-button type="button" wire:click="download" class="text-sm"><i
                                class="fa fa-fw mr-2 fa-download"></i>{{ __('Unduh CSV barang') }}</x-text-button>
                    </div>
                </div>
                <x-link-secondary-button href="#content"><i
                        class="fa fa-fw mr-2 fa-arrows-up-to-line"></i>{{ __('Ke atas') }}</x-link-secondary-button>
            </div>
        </div>
        <div class="w-full">
            <div class="flex justify-between w-full px-3 sm:px-0">
                <div class="my-auto"><span>{{ $inv_items->total() }}</span><span
                        class="hidden md:inline">{{ ' ' . __('barang') }}</span></div>
                <div class="flex">
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
            @if (!$inv_items->count())
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
                            <i class="fa fa-building relative"><i
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
                            class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-1 mt-4 px-3 sm:px-0">
                            @foreach ($inv_items as $inv_item)
                                <x-inv-card-grid :href="route('inventory.items.show', ['id' => $inv_item->id])" :name="$inv_item->name" :desc="$inv_item->desc" :uom="$inv_item->inv_uom->name"
                                    :loc="$inv_item->inv_loc->name ?? null" :qty="$qty" :qty_main="$inv_item->qty_main" :qty_used="$inv_item->qty_used" :qty_rep="$inv_item->qty_rep"
                                    :url="$inv_item->photo ? '/storage/inv-items/' . $inv_item->photo : null">
                                </x-inv-card-grid>
                            @endforeach
                        </div>
                    @break

                    @case('list')
                        <div wire:key="list" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto mt-4">
                            <table class="table table-sm table-truncate text-neutral-600 dark:text-neutral-400">
                                <tr class="uppercase text-xs">
                                    <th>{{ __('Qty') }}</th>
                                    <th>{{ __('Nama') }}</th>
                                    <th>{{ __('Kode') }}</th>
                                    <th>{{ __('Harga') }}</th>
                                    <th>{{ __('Lokasi') }} </th>
                                    <th>{{ __('Tag') }} </th>
                                    <th></th>
                                </tr>
                                @foreach ($inv_items as $inv_item)
                                    <x-inv-tr :href="route('inventory.items.show', ['id' => $inv_item->id])" :name="$inv_item->name" :desc="$inv_item->desc" :code="$inv_item->code"
                                        :curr="$inv_curr->name" :price="$inv_item->price" :uom="$inv_item->inv_uom->name" :loc="$inv_item->inv_loc->name ?? null"
                                        :tags="$inv_item->tags() ?? null" :qty="$qty" :qty_main="$inv_item->qty_main" :qty_used="$inv_item->qty_used"
                                        :qty_rep="$inv_item->qty_rep">
                                    </x-inv-tr>
                                @endforeach
                            </table>
                        </div>
                    @break

                    @default
                        <div wire:key="content" class="grid grid-cols-1 lg:grid-cols-2 gap-1 mt-4">
                            @foreach ($inv_items as $inv_item)
                                <x-inv-card-content :href="route('inventory.items.show', ['id' => $inv_item->id])" :name="$inv_item->name" :desc="$inv_item->desc" :code="$inv_item->code"
                                    :curr="$inv_curr->name" :price="$inv_item->price" :uom="$inv_item->inv_uom->name" :loc="$inv_item->inv_loc->name ?? null"
                                    :tags="$inv_item->tags() ?? null" :qty="$qty" :qty_main="$inv_item->qty_main" :qty_used="$inv_item->qty_used"
                                    :qty_rep="$inv_item->qty_rep" :url="$inv_item->photo ? '/storage/inv-items/' . $inv_item->photo : null">
                                </x-inv-card-content>
                            @endforeach
                        </div>
                @endswitch
            @endif
        </div>
    </div>
</div>
