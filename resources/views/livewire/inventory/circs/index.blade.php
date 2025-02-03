<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\InvCirc;
use App\Models\InvItem;

new #[Layout('layouts.app')] 
class extends Component {

    use WithPagination;

    public array $areas = [];
    public array $area_ids = [];
    public array $qwords = [];
    public array $qlocs = [];
    public array $qtags = [];
    public string $q = '';
    public string $status = '';
 
    public function with(): array
    {
        $circs = InvCirc::paginate(10);
        $inv_items = InvItem::paginate(10);
        return [
            'circs' => $circs,
            'inv_items' => $inv_items,
        ];

    }
}

?>

<x-slot name="title">{{ __('Sirkulasi') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
  <x-nav-inventory></x-nav-inventory>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2 mb-6">
        <div class="flex gap-x-4 px-8 py-3 lg:py-0">
            <div class="btn-group h-10">
                <x-checkbox-button wire:model.live="status" grow value="pending" name="status" id="status-pending">
                    <div class="text-center my-auto"><i class="fa fa-hourglass-half"></i></div>
                </x-checkbox-button>
                <x-checkbox-button wire:model.live="status" grow value="approved" name="status" id="status-approved">
                    <div class="text-center my-auto"><i class="fa fa-thumbs-up"></i></div>
                </x-checkbox-button>
                <x-checkbox-button wire:model.live="status" grow value="rejected" name="status" id="status-rejected">
                    <div class="text-center my-auto"><i class="fa fa-thumbs-down"></i></div>
                </x-checkbox-button>
            </div>
            <div class="btn-group h-10">
                <x-checkbox-button wire:model.live="type" grow value="deposit" name="type" id="type-deposit">
                    <div class="text-center my-auto"><i class="fa fa-plus text-green-500"></i></div>
                </x-checkbox-button>
                <x-checkbox-button wire:model.live="type" grow value="withdrawal" name="type" id="type-withdrawal">
                    <div class="text-center my-auto"><i class="fa fa-minus text-red-500"></i></div>
                </x-checkbox-button>
                <x-checkbox-button wire:model.live="type" grow value="capture" name="type" id="type-capture">
                    <div class="text-center my-auto"><i class="fa fa-code-commit text-yellow-500"></i></div>
                </x-checkbox-button>
            </div>
        </div>
        <div class="px-4 py-4 lg:py-0 grow flex items-center">
            <x-text-button type="button" class="px-4 text-xs font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-widest hover:bg-neutral-200 dark:hover:bg-neutral-700"><i class="fa fa-fw fa-filter mr-3"></i>{{ __('Filter') }}</x-secondary-button>
        </div>
        <div class="px-4 py-4 lg:py-0 flex items-center justify-between gap-x-4">
            <x-text-button type="button" class="px-4 text-xs font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-widest hover:bg-neutral-200 dark:hover:bg-neutral-700"><i class="fa fa-fw fa-tent mr-3"></i>TT MM +1</x-secondary-button>
            <div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <x-text-button><i class="fa fa-fw fa-ellipsis-h"></i></x-text-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="{{ route('inventory.items.create') }}" wire:navigate>
                            <i class="fa fa-fw me-2"></i>{{ __('Sirkulasi massal')}}
                        </x-dropdown-link>
                        <hr class="border-neutral-300 dark:border-neutral-600" />
                        <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')">
                            <i class="fa fa-fw me-2"></i>{{ __('Hapus semua filter')}}
                        </x-dropdown-link>
                        <hr class="border-neutral-300 dark:border-neutral-600" />
                        <x-dropdown-link href="#" wire:click.prevent="download">
                            <i class="fa fa-fw fa-download me-2"></i>{{ __('Unduh sebagai CSV') }}
                        </x-dropdown-link>
                        <x-dropdown-link href="#" wire:click.prevent="download">
                            <i class="fa fa-fw fa-print me-2"></i>{{ __('Cetak') }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
    <div class="w-full">
        <div class="flex flex-col gap-y-6 sm:flex-row justify-between w-full px-8">
            <div class="text-center sm:text-left">{{ 0 . ' ' . __('sirkulasi') }}</div>
            <x-select wire:model.live="sort" class="mr-3">
                <option value="updated">{{ __('Diperbarui') }}</option>
                <option value="created">{{ __('Dibuat') }}</option>
                <option value="price_low">{{ __('Termurah') }}</option>
                <option value="price_high">{{ __('Termahal') }}</option>
                <option value="qty_low">{{ __('Paling sedikit') }}</option>
                <option value="qty_high">{{ __('Paling banyak') }}</option>
            </x-select>
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
    <div class="flex flex-col gap-x-2 md:gap-x-4 sm:flex-row">
        <div>
            <div class="w-full sm:w-44 md:w-64 px-3 sm:px-0">
                <x-text-input-icon wire:model.live="q" icon="fa fa-fw fa-search" id="inv-q" type="search"
                    placeholder="{{ __('Cari...') }}" autofocus autocomplete="q" />
                <div class="btn-group w-full h-11 mt-5">
                    <x-checkbox-button wire:model.live="status" grow value="pending" name="status" id="status-pending">
                        <div class="text-center my-auto"><i class="fa fa-hourglass-half"></i></div>
                    </x-checkbox-button>
                    <x-checkbox-button wire:model.live="status" grow value="approved" name="status" id="status-approved">
                        <div class="text-center my-auto"><i class="fa fa-thumbs-up"></i></div>
                    </x-checkbox-button>
                    <x-checkbox-button wire:model.live="status" grow value="rejected" name="status" id="status-rejected">
                        <div class="text-center my-auto"><i class="fa fa-thumbs-down"></i></div>
                    </x-checkbox-button>
                </div>
                <div class="mt-4 bg-white dark:bg-neutral-800 shadow rounded-lg p-4">
    
                    <x-text-input-icon wire:model.live="user" icon="fa fa-fw fa-user" id="inv-user" class="my-2"
                        type="search" placeholder="{{ __('Pengguna') }}" />
                    <div class="mt-4">
                        <x-checkbox wire:model.live="qdirs" id="inv-dir-1" value="deposit"><i
                                class="fa fa-fw fa-plus mr-2"></i>{{ __('Penambahan') }}</x-checkbox>
                    </div>
                    <div class="mt-4">
                        <x-checkbox wire:model.live="qdirs" id="inv-dir-2" value="withdrawal"><i
                                class="fa fa-fw fa-minus mr-2"></i>{{ __('Pengambilan') }}</x-checkbox>
                    </div>
                    <div class="mt-4">
                        <x-checkbox wire:model.live="qdirs" id="inv-dir-3" value="capture"><i
                                class="fa fa-fw fa-code-commit mr-2"></i>{{ __('Pencatatan') }}</x-checkbox>
                    </div>
    
                </div>
                <div class="mt-4 bg-white dark:bg-neutral-800 shadow rounded-lg py-5 px-4">
                    <div class="flex items-start justify-between">
                        <div><i class="fa fa-calendar mr-3"></i>{{ __('Rentang') }}</div>
                        <div class="flex items-center">
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <x-text-button><i class="fa fa-fw fa-ellipsis-v"></i></x-text-button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link href="#" wire:click.prevent="setToday">
                                        {{ __('Hari ini') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                        {{ __('Kemarin') }}
                                    </x-dropdown-link>
                                    <hr class="border-neutral-300 dark:border-neutral-600" />
                                    <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                        {{ __('Bulan ini') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                        {{ __('Bulan lalu') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </div>
                    <div class="mt-5">
                        <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                        <x-text-input wire:model.live="end_at" id="cal-date-end" type="date"
                            class="mt-3 mb-1"></x-text-input>
                    </div>
                </div>
            </div>
            <div class="sticky top-0 py-5 opacity-0 sm:opacity-100">
                <div class="bg-white dark:bg-neutral-800 shadow rounded-lg py-3 px-4">
                    @foreach ($areas as $area)
                        <div class="my-2">
                            <x-checkbox wire:model.live="area_ids" wire:key="inv-area-{{ $area['id'] }}"
                                id="inv-area-{{ $area['id'] }}"
                                value="{{ $area['id'] }}">{{ $area['name'] }}</x-checkbox>
                        </div>
                    @endforeach
                </div>
                <div class="py-4">
                    <div class="m-3">
                        <x-text-button wire:click="resetCircs" type="button" class="text-sm"><i
                                class="fa fa-fw mr-2 fa-undo"></i>{{ __('Atur ulang') }}</x-text-button>
                    </div>
                    <div class="m-3">
                        <x-text-button x-on:click="$dispatch('open-modal', 'inv-circs-print')" type="button"
                            class="text-sm"><i class="fa fa-fw mr-2 fa-print"></i>{{ __('Cetak semua') }}</x-text-button>
                    </div>
                    <div class="m-3">
                        <x-text-button type="button" wire:click="download" class="text-sm">
                            <i class="fa fa-fw mr-2 fa-download"></i>{{ __('Unduh CSV sirkulasi') }}</x-text-button>
                    </div>
                </div>
    
                <x-link-secondary-button href="#content"><i
                        class="fa fa-fw mr-2 fa-arrows-up-to-line"></i>{{ __('Ke atas') }}</x-link-secondary-button>
            </div>
        </div>
        <div x-data="{ ids: @entangle('ids') }" class="w-full" x-on:click.away="ids = []">
            <x-modal name="inv-circs-print">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Cetak sirkulasi') }}
                    </h2>
                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                        <p class="mt-3">
                            {{ __('Mengarahkanmu ke halaman cetak...') }}
                        </p>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">
                            {{ __('Tutup') }}
                        </x-secondary-button>
                    </div>
                </div>
    
            </x-modal>
            <div x-show="!ids.length" class="flex justify-between w-full p-3">
                <div class="my-auto">{{ 0 . ' ' . __('sirkulasi') }}</div>
                <div class="flex">
                    <x-select wire:model.live="sort">
                        <option value="updated">{{ __('Diperbarui') }}</option>
                        <option value="created">{{ __('Dibuat') }}</option>
                        <option value="amount_low">{{ __('Termurah') }}</option>
                        <option value="amount_high">{{ __('Termahal') }}</option>
                        <option value="qty_low">{{ __('Paling sedikit') }}</option>
                        <option value="qty_high">{{ __('Paling banyak') }}</option>
                    </x-select>
                </div>
            </div>
            <div x-show="ids.length" x-cloak
                class="sticky z-10 top-0 flex justify-between w-full p-4 bg-neutral-100 dark:bg-neutral-900">
                <div class="my-auto"><span x-text="ids.length"></span><span
                        class="hidden lg:inline">{{ ' ' . __('terpilih') }}</span></div>
                <div class="flex gap-x-2 items-center">
                    <x-secondary-button type="button" x-show="ids.length === 1" class="flex items-center h-full"
                        x-data="" x-on:click.prevent="$dispatch('open-modal', 'circ-show-'+ids[0])"><i
                            class="fa fa-fw fa-eye"></i></x-secondary-button>
                    <x-secondary-button type="button" wire:click="print" x-on:click="$dispatch('open-modal', 'inv-circs-print');"
                        class="flex items-center h-full"><i class="fa fa-fw fa-print"></i><span class="ml-2 hidden lg:inline">{{ __('Cetak') }}</span></x-secondary-button>
                    <div class="btn-group">
                        <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'inv-circs-approve')"
                            class="flex items-center"><i class="fa fa-fw fa-thumbs-up"></i><span
                                class="ml-2">{{ __('Setujui') }}</span></x-secondary-button>
                        <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'inv-circs-reject')"
                            class="flex items-center"><i class="fa fa-fw fa-thumbs-down"></i></x-secondary-button>
                    </div>
                    <x-text-button type="button" @click="ids = []" class="ml-2"><i
                            class="fa fa-fw fa-times"></i></x-text-button>
                </div>
            </div>
    
            @if (!$circs->count())
                @if (!count($area_ids))
                    <div wire:key="no-area" class="py-20">
                        <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                            <i class="fa fa-building relative"><i
                                    class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                        </div>
                        <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih area') }}
                        </div>
                    </div>
                @elseif (!count($status))
                    <div wire:key="no-qdirs" class="py-20">
                        <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                            <i class="fa fa-thumbs-up relative"><i
                                    class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                        </div>
                        <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih status sirkulasi') }}
                        </div>
                    </div>
                @elseif (!count($qdirs))
                    <div wire:key="no-qdirs" class="py-20">
                        <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                            <i class="fa fa-plus-minus relative"><i
                                    class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                        </div>
                        <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih arah sirkulasi') }}
                        </div>
                    </div>
                @elseif (!$start_at || !$end_at)
                    <div wire:key="no-qdirs" class="py-20">
                        <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                            <i class="fa fa-calendar relative"><i
                                    class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                        </div>
                        <div class="text-center text-neutral-400 dark:text-neutral-600">
                            {{ __('Pilih rentang tanggal') }}
                        </div>
                    </div>
                @else
                    <div wire:key="no-match" class="py-20">
                        <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                            <i class="fa fa-ghost"></i>
                        </div>
                        <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
                        </div>
                    </div>
                @endif
            @else
                <div wire:key="circs" class="inv-circs mt-1 grid px-0 sm:px-3">
                    @foreach ($circs as $circ)
                        <div class="truncate p-1 -mt-1" wire:key="circ-container-{{ $circ->id }}">
                            <x-circ-checkbox wire:key="circ-{{ $circ->id }}" id="{{ $circ->id }}"
                                model="ids" name="{{ $circ->inv_item->name }}" desc="{{ $circ->inv_item->desc }}"
                                code="{{ $circ->inv_item->code ?? __('Tak ada kode') }}"
                                uom="{{ $circ->inv_item->inv_uom->name }}"
                                loc="{{ $circ->inv_item->inv_loc->name ?? __('Tak ada lokasi') }}"
                                photo="{{ $circ->inv_item->photo }}" dir_icon="{{ $circ->getDirIcon() }}"
                                qty="{{ $circ->qty }}" qtype="{{ $circ->qtype }}" curr="{{ $inv_curr->name }}"
                                amount="{{ $circ->amount }}"
                                assigner="{{ $circ->assigner_id ? __('Didelegasikan oleh:') . ' ' . $circ->assigner->name . ' (' . $circ->assigner->emp_id . ')' : '' }}"
                                user_name="{{ $circ->user->name }}" remarks="{{ $circ->remarks }}"
                                status="{{ $circ->status }}" user_photo="{{ $circ->user->photo }}"
                                date_human="{{ $circ->updated_at->diffForHumans() }}">
                            </x-circ-checkbox>
                            <x-modal name="circ-show-{{ $circ->id }}">
                                <livewire:inv-circ-edit wire:key="modal-{{ $circ->id }}" :$circ lazy />
                            </x-modal>
                        </div>
                    @endforeach
                    <div class="flex items-center relative h-16">
                        @if (!$circs->isEmpty())
                            @if ($circs->hasMorePages())
                                <div wire:key="more" x-data="{
                                    observe() {
                                        const observer = new IntersectionObserver((circs) => {
                                            circs.forEach(circ => {
                                                if (circ.isIntersecting) {
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
                </div>
            @endif
        </div>
    </div>
    
</div>
