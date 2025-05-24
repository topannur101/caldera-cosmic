<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Models\InvCirc;
use App\Models\InvItem;
use App\Models\InvArea;
use App\Models\InvCurr;
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

    public string $print_as = 'approval-form';

    public bool $print_commit = false;

    #[Url]
    public string $q = '';
    
    public array $qwords = []; // caldera: do you need it?

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

        $circsParams = session('inv_circs_params', []);

        if ($circsParams) {
            $this->q                = $circsParams['q']                 ?? '';
            $this->sort             = $circsParams['sort']              ?? '';
            $this->area_ids         = $circsParams['area_ids']          ?? [];
            $this->circ_eval_status = $circsParams['circ_eval_status']  ?? [];
            $this->circ_types       = $circsParams['circ_types']        ?? [];
            $this->date_fr          = $circsParams['date_fr']           ?? '';
            $this->date_to          = $circsParams['date_to']           ?? '';
            $this->user_id          = $circsParams['user_id']           ?? 0;
            $this->remarks          = $circsParams['remarks']           ?? ['', ''];
        }

        $areasParam = session('inv_areas_param', []);

        $areasParam 
        ? $this->area_ids = $areasParam ?? [] 
        : $this->area_ids = $areas->pluck('id')->toArray();
    }

    private function InvCircQuery()
    {
        $q              = trim($this->q);
        $circ_remarks   = trim ($this->remarks[0]);
        $eval_remarks   = trim ($this->remarks[1]);

        $inv_circs_params = [
            'q'                 => $q,
            'sort'              => $this->sort,
            'circ_eval_status'  => $this->circ_eval_status,
            'circ_types'        => $this->circ_types,
            'date_fr'           => $this->date_fr,
            'date_to'           => $this->date_to,
            'user_id'           => $this->user_id,
            'area_ids'          => $this->area_ids,
            'remarks'           => [ $circ_remarks, $eval_remarks ],
        ];

        session(['inv_circs_params' => $inv_circs_params]);
        session(['inv_areas_param'  => $this->area_ids]);

        $inv_circs_query = InvCirc::with([
            'inv_stock',
            'inv_stock.inv_item',
            'inv_stock.inv_item.inv_area',
            'inv_curr',
            'user'
        ])
        ->whereHas('inv_item', function ($query) use ($q) {
            // items
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%$q%")
                         ->orWhere('code', 'like', "%$q%")
                         ->orWhere('desc', 'like', "%$q%");
            })
            ->whereIn('inv_area_id', $this->area_ids);
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
        $this->dispatch('print-circ-ids', $this->circ_ids, 'approval-form');
        $this->js('$dispatch("open-spotlight", "printing")');
    }

    public function printAs()
    {   
        $inv_circs_query = $this->InvCircQuery();
        $circ_ids = $inv_circs_query->limit(500)->get()->pluck('id');
     
        $this->dispatch('print-circ-ids', $circ_ids, $this->print_as);
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
    <livewire:inventory.circs.print.index />
</x-slot>

<div id="content" class="py-6 max-w-8xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200"
    x-data="{ 
        ids: @entangle('circ_ids'),
        status: @entangle('circ_eval_status').live, 
        types: @entangle('circ_types').live,
        lastChecked: null,
        handleCheck(event, id) {
            if (event.shiftKey && this.lastChecked !== null) {
                console.log('shift selection');
                // Find the positions of both selected items in the full list of all IDs
                const allIds = this.getAllIds(); // You'll need to define this function to get all available IDs
                const start = allIds.indexOf(this.lastChecked);
                const end = allIds.indexOf(id);
                console.log('Start:', start, 'End:', end);
                
                if (start !== -1 && end !== -1) {
                    // Sort to handle selection in either direction
                    const [lower, upper] = start < end ? [start, end] : [end, start];
                    
                    // Select all IDs in the range
                    for (let i = lower; i <= upper; i++) {
                        const currentId = allIds[i];
                        if (!this.ids.includes(currentId)) {
                            this.ids.push(currentId);
                        }
                    }
                }
            }
            this.lastChecked = id;
            console.log('lastChecked: ' + this.lastChecked);
        },
        getAllIds() {
        return Array.from(document.querySelectorAll('input[type=checkbox][id^=circ-]'))
                .map(checkbox => checkbox.value);
        }
    }">
    <div wire:key="circs-modals">
        <x-modal name="circ-show">
            <livewire:inventory.circs.circ-show />
        </x-modal>
        <x-modal name="circs-evaluate" focusable>
            <livewire:inventory.circs.evaluate />
        </x-modal>
        <x-modal name="print-all" focusable>
            <div class="p-6 flex flex-col gap-y-6">
                <div class="flex justify-between items-start">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        <i class="icon-printer mr-2"></i>
                        {{ __('Cetak semua sebagai...') }}
                    </h2>
                    <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
                </div>
                <div x-data="{ print_as: @entangle('print_as') }">
                    <x-radio x-model="print_as" id="as-approval-form" name="as-approval-form" value="approval-form">{{ __('Formulir persetujuan') }}</x-radio>
                    <x-radio x-model="print_as" id="as-label-small" name="as-label-small" value="label-small">{{  __('Label kecil') }}</x-radio>
                    <x-radio x-model="print_as" id="as-label-large" name="as-label-large" value="label-large">{{  __('Label besar') }}</x-radio>
                </div>
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="printAs" x-on:click="$dispatch('close')">
                        <div class="relative">
                            <span wire:loading.class="opacity-0" wire:target="printAs"><i class="icon-printer"></i><span class="ml-0 hidden md:ml-2 md:inline">{{ __('Cetak') }}</span></span>
                            <x-spinner wire:loading.class.remove="hidden" wire:target="printAs" class="hidden sm mono"></x-spinner>                
                        </div>  
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>
    </div>
    <div wire:key="circs-spotlights">
        <x-spotlight name="printing" maxWidth="sm">
            <div class="w-full flex flex-col gap-y-6 pb-10 text-center ">
                <div class="h-16 relative">
                    <x-spinner class="mono" />
                </div>
                <header>
                    <h2 class="text-xl font-medium">
                        {{ __('Memanggil dialog cetak...') }}
                    </h2>
                </header>
            </div>
        </x-spotlight>
    </div>
    <div class="static lg:sticky top-0 z-10 py-6 ">
        <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2">
            <div class="flex gap-x-2 items-center px-8 py-2 lg:px-4 lg:py-0">
                <i wire:loading.remove class="icon-search {{ $q ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600' }}"></i>
                <i wire:loading class="w-4 relative">
                    <x-spinner class="sm mono"></x-spinner>
                </i>
                <div class="w-full md:w-32">
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
            </div>
            <div class="flex justify-between px-8 lg:px-3 py-3 lg:py-0 divide-x divide-neutral-200 dark:divide-neutral-700">
                <div class="btn-group h-9 pr-3">
                    <x-checkbox-button-t title="{{ __('Tertunda') }}" x-model="status" grow value="pending" name="circ_eval_status" id="circ_eval_status-pending">
                        <div class="text-center my-auto"><i class="icon-hourglass"></i></div>
                    </x-checkbox-button-t>
                    <x-checkbox-button-t title="{{ __('Disetujui') }}" x-model="status" grow value="approved" name="circ_eval_status" id="circ_eval_status-approved">
                        <div class="text-center my-auto"><i class="icon-thumbs-up"></i></div>
                    </x-checkbox-button-t>
                    <x-checkbox-button-t title="{{ __('Ditolak') }}" x-model="status" grow value="rejected" name="circ_eval_status" id="circ_eval_status-rejected">
                        <div class="text-center my-auto"><i class="icon-thumbs-down"></i></div>
                    </x-checkbox-button-t>
                </div>
                <div class="btn-group h-9 pl-3">
                    <x-checkbox-button-t title="{{ __('Tambah') }}" x-model="types" grow value="deposit" name="circ_types" id="circ_types-deposit">
                        <div class="text-center my-auto"><i class="icon-plus text-green-500"></i></div>
                    </x-checkbox-button-t>
                    <x-checkbox-button-t title="{{ __('Catat') }}" x-model="types" grow value="capture" name="circ_types" id="circ_types-capture">
                        <div class="text-center my-auto"><i class="icon-git-commit-horizontal text-yellow-600"></i></div>
                    </x-checkbox-button-t>
                    <x-checkbox-button-t title="{{ __('Ambil') }}" x-model="types" grow value="withdrawal" name="circ_types" id="circ_types-withdrawal">
                        <div class="text-center my-auto"><i class="icon-minus text-red-500"></i></div>
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
                    <x-dropdown align="right" width="60">
                        <x-slot name="trigger">
                            <x-text-button><i class="icon-ellipsis"></i></x-text-button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link href="{{ route('inventory.circs.summary') }}" wire:navigate>
                                <i class="icon-chart-line me-2"></i>{{ __('Ringkasan sirkulasi')}}
                            </x-dropdown-link>
                            <x-dropdown-link href="{{ route('inventory.circs.bulk-operation.index') }}" wire:navigate>
                                <i class="icon-blank me-2"></i>{{ __('Operasi massal sirkulasi')}}
                            </x-dropdown-link>
                            <hr class="border-neutral-300 dark:border-neutral-600" />
                            <x-dropdown-link href="#" wire:click.prevent="resetQuery">
                                <i class="icon-rotate-cw me-2"></i>{{ __('Reset')}}
                            </x-dropdown-link>
                            <hr class="border-neutral-300 dark:border-neutral-600" />
                            <x-dropdown-link href="#" wire:click.prevent="download">
                                <i class="icon-download me-2"></i>{{ __('Unduh sebagai CSV') }}
                            </x-dropdown-link>
                            <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'print-all')">
                                <i class="icon-printer me-2"></i>{{ __('Cetak semua...') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </div>
    <div class="h-auto sm:h-12">
        <div x-show="!ids.length" class="flex items-center flex-col gap-y-6 sm:flex-row justify-between w-full h-full px-8">
            <div class="text-center sm:text-left">{{ ($inv_circs->total() > 9999 ? ( '9999+' ) : $inv_circs->total()) . ' ' . __('sirkulasi') }}</div>
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
                            class="icon-align-justify text-center m-auto"></i></x-radio-button>
                    <!-- <x-radio-button wire:model.live="view" value="content" name="view" id="view-content"><i
                            class="icon-layout-list text-center m-auto"></i></x-radio-button>
                    <x-radio-button wire:model.live="view" value="grid" name="view" id="view-grid"><i
                            class="icon-layout-grid text-center m-auto"></i></x-radio-button> -->
                </div>
            </div>
        </div>
        <div x-show="ids.length" x-cloak class="flex items-center justify-between w-full h-full px-8">
            <div class="font-bold"><span x-text="ids.length"></span><span>{{ ' ' . __('dipilih') }}</span></div>
            <div class="flex gap-x-2">
                <x-secondary-button type="button" x-on:click="ids = []; lastChecked = null">{{ __('Batal') }}  </x-secondary-button>
                <div class="btn-group">
                    <x-secondary-button type="button" wire:click="printCircIds">
                        <div class="relative">
                            <span wire:loading.class="opacity-0" wire:target="printCircIds"><i class="icon-printer"></i><span class="ml-0 hidden md:ml-2 md:inline">{{ __('Cetak') }}</span></span>
                            <x-spinner wire:loading.class.remove="hidden" wire:target="printCircIds" class="hidden sm mono"></x-spinner>                
                        </div>                
                    </x-secondary-button>
                    <x-secondary-button type="button" wire:click="evalCircIds">
                        <div class="relative">
                            <span wire:loading.class="opacity-0" wire:target="evalCircIds"><i class="icon-gavel"></i><span class="ml-0 hidden md:ml-2 md:inline">{{ __('Evaluasi') }}</span></span>
                            <x-spinner wire:loading.class.remove="hidden" wire:target="evalCircIds" class="hidden sm mono"></x-spinner>
                        </div>
                    </x-secondary-button>
                </div>
            </div>
        </div>
    </div>
    <div>
        @if (!$inv_circs->count())
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
                @case('list')
                    <div class="p-0 sm:p-1 overflow-auto mt-6">
                        <table class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg w-full table text-sm [&_th]:px-1 [&_th]:py-3 [&_td]:p-1">
                            <tr class="uppercase text-xs">
                                <th></th>
                                <th>{{ __('Qty') }}</th>
                                <th colspan="2">{{ __('Nama') . ' & ' . __('Deskripsi') }}</th>
                                <th>{{ __('Kode') }}</th>
                                <th><i class="icon-map-pin"></i></th>
                                <th><i class="icon-user"></i></th>
                                <th>{{ __('Keterangan') }}</th>
                                <th class="flex justify-end">{{ 'Σ ' . InvCurr::find(1)->name }}</th>
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
                                    amount="{{ $circ->amount }}"
                                    curr="{{ InvCurr::find(1)->name }}"
                                    eval_icon="{{ $circ->eval_icon() }}"
                                    item_id="{{ $circ->inv_stock->inv_item->id }}"
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
