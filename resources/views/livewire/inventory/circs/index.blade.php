<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Models\InvCirc;
use App\Models\InvItem;
use App\Models\InvArea;
use App\Models\User;
use Carbon\Carbon;

new #[Layout('layouts.app')] 
class extends Component {

    use WithPagination;
    
    public int $perPage = 24;

    public string $view = 'list';

    public string $sort = 'updated';

    public array $areas = [];

    public array $area_ids = [];

    public array $circ_eval_status = ['pending', 'approved'];

    public array $circ_types = ['deposit', 'withdrawal'];

    public string $date_fr = '';

    public string $date_to = '';

    public array $users = [];

    public int $user_id = 0;

    public array $remarks = ['', ''];

    public array $circ_ids = [];

    public string $eval_remarks = '';

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
            $this->area_ids         = $savedParams['area_ids']          ?? [];
            $this->circ_eval_status = $savedParams['circ_eval_status']  ?? [];
            $this->circ_types       = $savedParams['circ_types']        ?? [];
            $this->date_fr          = $savedParams['date_fr']           ?? '';
            $this->date_to          = $savedParams['date_to']           ?? '';
            $this->user_id          = $savedParams['user_id']           ?? 0;
            $this->remarks          = $savedParams['remarks']           ?? ['', ''];
        } else {
            $this->area_ids = $areas->pluck('id')->toArray();
        }
    }

    private function InvCircQuery()
    {
        $circ_remarks = trim ($this->remarks[0]);
        $eval_remarks = trim ($this->remarks[1]);

        $inv_circs_params = [
            'area_ids'          => $this->area_ids,
            'circ_eval_status'  => $this->circ_eval_status,
            'circ_types'        => $this->circ_types,
            'date_fr'           => $this->date_fr,
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
            'user'
        ])
        ->whereHas('inv_item', function($query) {
            $query->whereIn('inv_area_id', $this->area_ids);
        })
        ->whereIn('eval_status', $this->circ_eval_status)
        ->whereIn('type', $this->circ_types);

        if($this->date_fr && $this->date_to) {
            $fr = Carbon::parse($this->date_fr)->startOfDay();
            $to = Carbon::parse($this->date_to)->endOfDay();
            $inv_circs_query->whereBetween('updated_at', [$fr, $to]);
        }

        if($this->user_id) {
            $inv_circs_query->where('user_id', $this->user_id);
        }

        if($this->remarks[0]) {
            $inv_circs_query->where('remarks', 'like', "%{$this->remarks[0]}%");
        }

        if($this->remarks[1]) {
            $inv_circs_query->where('eval_remarks', 'like', "%{$this->remarks[0]}%");
        }

        switch ($this->sort) {
            case 'updated':
                $inv_circs_query->orderByDesc('updated_at');
                break;
            case 'qty_low':
                $inv_circs_query->orderBy('qty_relative');
                break;
            case 'qty_high':
                $inv_circs_query->orderByDesc('qty_relative');
                break;
            case 'amount_low':
                $inv_circs_query->orderBy('amount');
                break;
            case 'amount_high':
                $inv_circs_query->orderByDesc('amount');
                break;

        }

        return $inv_circs_query;
    }

    #[On('circ-updated')]
    #[On('circ-evaluated')]
    public function with(): array
    {
        $inv_circs_query = $this->InvCircQuery();

        $inv_circs = $inv_circs_query->paginate($this->perPage);
        $user_ids = $inv_circs_query->limit(1000)->get()->pluck('user_id')->unique();
        $this->users = User::whereIn('id', $user_ids)->orderBy('name')->get()->toArray();

        return [
            'inv_circs' => $inv_circs,
        ];
    }

    public function resetQuery()
    {
        session()->forget('inv_circs_params');
        $this->redirect(route('inventory.circs.index'), navigate: true);
    }

    public function loadMore()
    {
        $this->perPage += 24;
    }

    public function evalCircIds()
    {
        $this->dispatch('eval-circ-ids', $this->circ_ids);
        $this->js('$dispatch("open-modal", "circs-evaluate")');
    }

    public function printCircIds()
    {
        $this->dispatch('print-circ-ids', $this->circ_ids);
        $this->js('$dispatch("open-spotlight", "printing")');
    }

    public function printAll()
    {
        $inv_circs_query = $this->InvCircQuery();
        $circ_ids = $inv_circs_query->limit(500)->get()->pluck('id');
        
        $this->dispatch('print-circ-ids', $circ_ids);
        $this->js('$dispatch("open-spotlight", "printing")');
    }
    
    #[On('print-ready')]
    public function printExecute()
    {
        $this->js("window.print()");
        $this->js('window.dispatchEvent(escKey)');
        $this->resetCircIds();
    }

    #[On('circ-evaluated')]
    public function resetCircIds()
    {
        $this->reset(['circ_ids']);
    }

    public function updated($property)
    {
        $props = ['view', 'sort', 'area_ids', 'circ_eval_status', 'circ_types', 'date_fr', 'date_to', 'user_id', 'remarks'];
        if(in_array($property, $props)) {
            $this->reset(['perPage', 'circ_ids']);
        }
    }

    public function download()
    {
        // Create a unique token for this download request
        $token = md5(uniqid());

        // Store the token in the session
        session()->put('inv_circs_token', $token);
        
        // Redirect to a temporary route that will handle the streaming
        return redirect()->route('download.inv-circs', ['token' => $token]);
    }

    public function resetDates()
    {
        $this->reset(['date_fr', 'date_to']);
    }
}

?>

<x-slot name="title">{{ __('Sirkulasi') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <link href="/print-potrait.css" type="text/css" rel="stylesheet" media="print">
    <x-nav-inventory></x-nav-inventory>
</x-slot>

<x-slot name="printable">    
    <livewire:inventory.circs.print />
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200"
    x-data="{ 
        ids: @entangle('circ_ids'),
        status: @entangle('circ_eval_status').live, 
        types: @entangle('circ_types').live
    }">
    <div wire:key="circs-modals">
      <x-modal name="circ-show">
         <livewire:inventory.circs.circ-show />
      </x-modal>
      <x-modal name="circs-evaluate" focusable>
        <livewire:inventory.circs.evaluate />
      </x-modal>
    </div>
    <div wire:key="circs-spotlights">
        <x-spotlight name="printing" maxWidth="sm">
            <div class="w-full flex flex-col gap-y-6 pb-10 text-center ">
                <div class="relative">
                    <i class="text-4xl fa-solid fa-spinner fa-spin-pulse"></i>
                </div>
                <header>
                    <h2 class="text-xl font-medium">
                        {{ __('Memanggil dialog cetak...') }}
                    </h2>
                </header>
            </div>
        </x-spotlight>
    </div>
    <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2 mb-6">
        <div class="flex justify-between px-8 lg:px-3 py-3 lg:py-0 divide-x divide-neutral-200 dark:divide-neutral-700">
            <div class="btn-group h-9 pr-3">
                <x-checkbox-button-t x-model="status" grow value="pending" name="circ_eval_status" id="circ_eval_status-pending">
                    <div class="text-center my-auto"><i class="fa fa-fw fa-hourglass"></i></div>
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
        <div class="flex items-center gap-x-4 p-4 lg:py-0 ">
            <x-date-selector isQuery="true" class="text-xs font-semibold uppercase" />
        </div>
        <div class="flex items-center gap-x-4 p-4 lg:py-0 ">
            <x-inv-user-selector isQuery="true" class="text-xs font-semibold uppercase" />
        </div>
        <div class="grow flex items-center gap-x-4 p-4 lg:py-0 ">
            <x-inv-remarks-filter isQuery="true" class="text-xs font-semibold uppercase" />
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
                        <x-dropdown-link href="#" wire:click="printAll">
                            <i class="fa fa-fw fa-print me-2"></i>{{ __('Cetak semua') }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
    <div class="h-auto sm:h-12">
        <div x-show="!ids.length" class="flex items-center flex-col gap-y-6 sm:flex-row justify-between w-full h-full px-8">
            <div class="text-center sm:text-left">{{ $inv_circs->total() . ' ' . __('sirkulasi') }}</div>
            <div class="grow flex justify-center sm:justify-end">
                <x-select wire:model.live="sort" class="mr-3">
                    <option value="updated">{{ __('Diperbarui') }}</option>
                    <option value="amount_low">{{ __('Amount terendah') }}</option>
                    <option value="amount_high">{{ __('Amount tertinggi') }}</option>
                    <option value="qty_low">{{ __('Qty terendah') }}</option>
                    <option value="qty_high">{{ __('Qty tertinggi') }}</option>
                </x-select>
                <div class="btn-group">
                    <x-radio-button wire:model.live="view" value="list" name="view" id="view-list"><i
                            class="fa fa-fw fa-grip-lines text-center m-auto"></i></x-radio-button>
                    <!-- <x-radio-button wire:model.live="view" value="content" name="view" id="view-content"><i
                            class="fa fa-fw fa-list text-center m-auto"></i></x-radio-button>
                    <x-radio-button wire:model.live="view" value="grid" name="view" id="view-grid"><i
                            class="fa fa-fw fa-border-all text-center m-auto"></i></x-radio-button> -->
                </div>
            </div>
        </div>
        <div x-show="ids.length" x-cloak class="flex items-center justify-between w-full h-full px-8">
            <div class="font-bold"><span x-text="ids.length"></span><span>{{ ' ' . __('dipilih') }}</span></div>
            <div class="flex gap-x-2">
                <x-secondary-button type="button" wire:click="printCircIds">
                    <div class="relative">
                        <span wire:loading.class="opacity-0" wire:target="printCircIds"><i class="fa fa-print mr-2"></i>{{ __('Cetak') }}</span>
                        <x-spinner wire:loading.class.remove="hidden" wire:target="printCircIds" class="hidden sm mono"></x-spinner>                
                    </div>                
                </x-secondary-button>
                <x-secondary-button type="button" wire:click="evalCircIds">
                    <div class="relative">
                        <span wire:loading.class="opacity-0" wire:target="evalCircIds">{{ __('Evaluasi') }}</span>
                        <x-spinner wire:loading.class.remove="hidden" wire:target="evalCircIds" class="hidden sm mono"></x-spinner>
                    </div>
                </x-secondary-button>
            </div>
        </div>
    </div>
    <div wire:loading.class="cal-shimmer">
        @if (!$inv_circs->count())
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
                @case('list')
                    <div class="p-0 sm:p-1 overflow-auto mt-6">
                        <table class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg w-full table text-sm [&_th]:px-1 [&_th]:py-3 [&_td]:p-1">
                            <tr class="uppercase text-xs">
                                <th></th>
                                <th>{{ __('Qty') }}</th>
                                <th colspan="2">{{ __('Nama') . ' & ' . __('Deskripsi') }}</th>
                                <th>{{ __('Kode') }}</th>
                                <th>{{ __('Lokasi') }}</th>
                                <th>{{ __('Pengguna') }}</th>
                                <th>{{ __('Keterangan') }}</th>
                                <th>{{ __('Diperbarui') }}</th>
                            </tr>
                            @foreach ($inv_circs as $circ)
                                <x-inv-circ-circs-tr
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
                    @break
                
            @endswitch
            <div wire:key="observer" class="flex items-center relative h-16">
                @if (!$inv_circs->isEmpty())
                    @if ($inv_circs->hasMorePages())
                        <div wire:key="more" x-data="{
                            observe() {
                                const observer = new IntersectionObserver((inv_circs) => {
                                    inv_circs.forEach(inv_circ => {
                                        if (inv_circ.isIntersecting) {
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
