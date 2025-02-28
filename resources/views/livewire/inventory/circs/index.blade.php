<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\InvCirc;
use App\Models\InvItem;
use App\Models\InvArea;
use App\Models\User;

new #[Layout('layouts.app')] 
class extends Component {

    use WithPagination;
    
    public int $perPage = 24;

    public string $view = 'list';

    public string $sort = '';

    public array $areas = [];

    public array $area_ids = [];

    public array $circ_eval_status = ['pending', 'approved'];

    public array $circ_types = ['deposit', 'capture', 'withdrawal'];

    public string $date_from = '';

    public string $date_to = '';

    public int $user_id = 0;

    public array $remarks = ['', ''];

    public function mount()
    {
        $user_id = Auth::user()->id;

        if($user_id === 1) {
            $areas = InvArea::all();

        } else {
            $user = User::find($user_id);
            $areas = $user->inv_areas;

        }

        $this->areas = $areas->toArray();

        $savedParams = session('inv_circs_params', []);

        if ($savedParams) {
            $this->area_ids         = $savedParams['area_ids'] ?? [];
            $this->circ_eval_status = $savedParams['circ_eval_status'] ?? [];
            $this->circ_types       = $savedParams['circ_types'] ?? [];
            $this->date_from        = $savedParams['date_from'] ?? '';
            $this->date_to          = $savedParams['date_to'] ?? '';
            $this->user_id          = $savedParams['user_id'] ?? 0;
            $this->remarks          = $savedParams['remarks'] ?? ['', ''];
        } else {
            $this->area_ids = $areas->pluck('id')->toArray();
        }
    }

    public function with(): array
    {
        $circ_remarks = trim ($this->remarks[0]);
        $eval_remarks = trim ($this->remarks[1]);

        $inv_circs_params = [
            'area_ids'          => $this->area_ids,
            'circ_eval_status'  => $this->circ_eval_status,
            'circ_types'        => $this->circ_types,
            'date_from'         => $this->date_from,
            'date_to'           => $this->date_to,
            'user_id'           => $this->user_id,
            'remarks'           => [ $circ_remarks, $eval_remarks ],
        ];

        session(['inv_circs_params' => $inv_circs_params]);

        $inv_circs_query = InvCirc::with([
            'inv_stock',
            'inv_stock.inv_item',
            'inv_stock.inv_item.inv_area',
            'inv_curr',
        ])
        ->whereHas('inv_item', function($query) {
            $query->whereIn('inv_area_id', $this->area_ids);
        })
        ->whereIn('eval_status', $this->circ_eval_status)
        ->whereIn('type', $this->circ_types);
        // ->whereBetween('updated_at', [$this->date_from, $this->date_to])
        // ->where('user_id', $this->user_id)
        // ->where('remarks', $this->remarks[0])
        // ->where('eval_remarks', $this->remarks[1]);

        $inv_circs = $inv_circs_query->paginate($this->perPage);

        return [
            'inv_circs' => $inv_circs
        ];
    }

    public function resetQuery()
    {
        session()->forget('inv_circs_params');
        $this->redirect(route('inventory.circs.index'), navigate: true);
    }
}

?>

<x-slot name="title">{{ __('Sirkulasi') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
  <x-nav-inventory></x-nav-inventory>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2 mb-6">
        <div x-data="{ 
                status: @entangle('circ_eval_status').live, 
                types: @entangle('circ_types').live
            }"
            class="flex justify-between px-8 lg:px-3 py-3 lg:py-0 divide-x divide-neutral-200 dark:divide-neutral-700">
            <div class="btn-group h-9 pr-3">
                <x-checkbox-button-t x-model="status" grow value="pending" name="circ_eval_status" id="circ_eval_status-pending">
                    <div class="text-center my-auto"><i class="fa fa-fw fa-hourglass-half"></i></div>
                </x-checkbox-button-t>
                <x-checkbox-button-t x-model="status" grow value="approved" name="circ_eval_status" id="circ_eval_status-approved">
                    <div class="text-center my-auto"><i class="fa fa-fw fa-thumbs-up"></i></div>
                </x-checkbox-button-t>
                <x-checkbox-button-t x-model="status" grow value="rejected" name="circ_eval_status" id="circ_eval_status-rejected">
                    <div class="text-center my-auto"><i class="fa fa-fw fa-thumbs-down"></i></div>
                </x-checkbox-button-t>
            </div>
            <div class="btn-group h-9 pl-3">
                <x-checkbox-button-t x-model="types" grow value="deposit" name="circ_types" id="circ_types-deposit">
                    <div class="text-center my-auto"><i class="fa fa-fw fa-plus text-green-500"></i></div>
                </x-checkbox-button-t>
                <x-checkbox-button-t x-model="types" grow value="capture" name="circ_types" id="circ_types-capture">
                    <div class="text-center my-auto"><i class="fa fa-fw fa-code-commit text-yellow-600"></i></div>
                </x-checkbox-button-t>
                <x-checkbox-button-t x-model="types" grow value="withdrawal" name="circ_types" id="circ_types-withdrawal">
                    <div class="text-center my-auto"><i class="fa fa-fw fa-minus text-red-500"></i></div>
                </x-checkbox-button-t>
            </div>
        </div>
        <div class="px-6 py-4 lg:py-0 flex items-center">
            <x-text-button type="button" class="text-neutral-400 dark:text-neutral-600 text-xs font-semibold uppercase"><i class="fa fa-fw fa-calendar me-3"></i><span>{{ __('Tanggal') }}</span></x-text-button>
        </div>
        <div class="px-6 py-4 lg:py-0 flex items-center">
            <x-text-button type="button" class="text-neutral-400 dark:text-neutral-600 text-xs font-semibold uppercase"><i class="fa fa-fw fa-user me-3"></i><span>{{ __('Pengguna') }}</span></x-text-button>
        </div>
        <div class="px-6 py-4 lg:py-0 grow flex items-center">
            <x-text-button type="button" class="text-neutral-400 dark:text-neutral-600 text-xs font-semibold uppercase"><span>{{ __('Keterangan') }}</span></x-text-button>
        </div>
        <div class="flex items-center justify-between gap-x-4 p-4 lg:py-0">
            <x-inv-area-selector class="text-xs font-semibold uppercase" :$areas />
            <div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <x-text-button><i class="fa fa-fw fa-ellipsis-h"></i></x-text-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="{{ route('inventory.items.create') }}" wire:navigate>
                            <i class="fa fa-fw fa-arrows-turn-right me-2"></i>{{ __('Sirkulasi massal')}}
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('inventory.circs.summary.index') }}" wire:navigate>
                            <i class="fa fa-fw fa-line-chart me-2"></i>{{ __('Ringkasan')}}
                        </x-dropdown-link>
                        <hr class="border-neutral-300 dark:border-neutral-600" />
                        <x-dropdown-link href="#" wire:click.prevent="resetQuery">
                            <i class="fa fa-fw fa-undo me-2"></i>{{ __('Reset')}}
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
        <div class="flex items-center flex-col gap-y-6 sm:flex-row justify-between w-full px-8">
            <div class="text-center sm:text-left">{{ $inv_circs->total() . ' ' . __('sirkulasi') }}</div>
            <div class="grow flex justify-center sm:justify-end">
                <x-select wire:model.live="sort" class="mr-3">
                    <option value="updated">{{ __('Diperbarui') }}</option>
                    <option value="created">{{ __('Dibuat') }}</option>
                    <option value="price_low">{{ __('Terakhir ditambah') }}</option>
                    <option value="price_high">{{ __('Terakhir diambil') }}</option>
                    <option value="price_low">{{ __('Termurah') }}</option>
                    <option value="price_high">{{ __('Termahal') }}</option>
                    <option value="qty_low">{{ __('Paling sedikit') }}</option>
                    <option value="qty_high">{{ __('Paling banyak') }}</option>
                    <option value="alpha">{{ __('Alfabet') }}</option>
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
    </div>
    <div class="p-0 sm:p-1 overflow-auto mt-6 ">
        <table class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg w-full table [&_th]:p-2 [&_td]:px-2 [&_td]:py-1">
            <tr class="uppercase text-xs">
                <th></th>
                <th></th>
                <th>{{ __('Qty') }}</th>
                <th></th>
                <th>{{ __('Nama') }}</th>
                <th>{{ __('Deskripsi') }}</th>
                <th>{{ __('Kode') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th>{{ __('Pengguna') }}</th>
                <th>{{ __('Keterangan') }}</th>
                <th>{{ __('Diperbarui') }}</th>
            </tr>
            @foreach ($inv_circs as $circ)
                <x-inv-circ-circs-tr wire:key="circ-{{ $circ->id }}"
                    id="{{ $circ->id }}"
                    color="{{ $circ->type_color() }}" 
                    icon="{{ $circ->type_icon() }}" 
                    qty_relative="{{ $circ->qty_relative }}" 
                    uom="{{ $circ->inv_stock->uom }}" 
                    user_name="{{ $circ->user->name }}" 
                    user_emp_id="{{ $circ->user->emp_id }}"
                    user_photo="{{ $circ->user->photo }}"
                    is_delegated="{{ $circ->is_delegated }}" 
                    eval_status="{{ $circ->eval_status }}"
                    eval_user_name="{{ $circ->eval_user?->name }}" 
                    eval_user_emp_id="{{ $circ->eval_user?->emp_id }}" 
                    updated_at="{{ $circ->updated_at }}" 
                    remarks="{{ $circ->remarks }}" 
                    eval_icon="{{ $circ->eval_icon() }}"
                    item_photo="{{ $circ->inv_stock->inv_item->photo }}"
                    item_name="{{ $circ->inv_stock->inv_item->name }}"
                    item_desc="{{ $circ->inv_stock->inv_item->desc }}"
                    item_code="{{ $circ->inv_stock->inv_item->code }}"
                    item_loc="{{ $circ->inv_stock->inv_item->inv_loc_id ? ($circ->inv_stock->inv_item->inv_loc->parent . '-' . $circ->inv_stock->inv_item->inv_loc->bin) : null }}">
                </x-inv-circ-circs-tr>     
            @endforeach
        </table>
    </div>


</div>
