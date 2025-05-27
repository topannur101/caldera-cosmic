<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use App\Models\InvOrderEval;
use App\Models\InvOrderItem;
use App\Models\InvArea;
use App\Models\User;
use Carbon\Carbon;

new class extends Component {

    use WithPagination;

    public int $perPage = 24;

    public string $sort = 'updated';

    public array $areas = [];

    public array $area_ids = [];

    public bool $area_multiple = false;

    public array $users = [];

    public int $user_id = 0;

    public string $purpose = '';

    public array $order_item_ids = [];

    #[Url]
    public string $q = '';
    
    public array $qwords = [];

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

        $orderItemsParams = session('inv_order_items_params', []);

        if ($orderItemsParams) {
            $this->q         = $orderItemsParams['q']         ?? '';
            $this->sort      = $orderItemsParams['sort']      ?? 'updated';
            $this->area_ids  = $orderItemsParams['area_ids']  ?? [];
            $this->user_id   = $orderItemsParams['user_id']   ?? 0;
            $this->purpose   = $orderItemsParams['purpose']   ?? '';
        }

        $areasParams = session('inv_areas_params', []);

        if (!empty($areasParams)) {
            $this->area_ids = $areasParams['ids'] ?? [];
            
            // If more than one area ID, force multiple mode
            if (count($this->area_ids) > 1) {
                $this->area_multiple = true;
            } else {
                // Honor the stored preference for single/zero selections
                $this->area_multiple = $areasParams['multiple'] ?? false;
            }
        } else {
            // Default behavior: single selection mode with first available area
            $this->area_multiple = false;
            $this->area_ids = !empty($this->areas) ? [$this->areas[0]['id']] : [];
        }
    }

    private function InvOrderItemQuery()
    {
        $q = trim($this->q);
        $purpose = trim($this->purpose);

        $inv_order_items_params = [
            'q'         => $q,
            'sort'      => $this->sort,
            'area_ids'  => $this->area_ids,
            'user_id'   => $this->user_id,
            'purpose'   => $purpose,
        ];

        $inv_areas_params = [
            'multiple'      => $this->area_multiple,
            'ids'           => $inv_order_items_params['area_ids'],
        ];

        session(['inv_order_items_params' => $inv_order_items_params]);
        session(['inv_areas_params' => $inv_areas_params]);

        $inv_order_items_query = InvOrderItem::with([
            'inv_area',
            'inv_curr',
            'inv_order_budget',
            'inv_order_budget.inv_curr',
            'inv_item',
            'inv_order_evals.user'
        ])
        ->whereNull('inv_order_id') // Only open orders
        ->whereIn('inv_area_id', $this->area_ids);

        if($q) {
            $inv_order_items_query->where(function ($query) use ($q) {
                $query->where('name', 'like', "%$q%")
                      ->orWhere('desc', 'like', "%$q%")
                      ->orWhere('code', 'like', "%$q%")
                      ->orWhere('purpose', 'like', "%$q%");
            });
        }

        if($this->user_id) {
            $inv_order_items_query->whereHas('inv_order_evals', function($query) {
                $query->where('user_id', $this->user_id);
            });
        }

        if($purpose) {
            $inv_order_items_query->where('purpose', 'like', "%{$purpose}%");
        }

        switch ($this->sort) {
            case 'updated':
                $inv_order_items_query->orderByDesc('updated_at');
                break;
            case 'name':
                $inv_order_items_query->orderBy('name');
                break;
            case 'qty_low':
                $inv_order_items_query->orderBy('qty');
                break;
            case 'qty_high':
                $inv_order_items_query->orderByDesc('qty');
                break;
            case 'amount_low':
                $inv_order_items_query->orderBy('amount_budget');
                break;
            case 'amount_high':
                $inv_order_items_query->orderByDesc('amount_budget');
                break;
        }

        return $inv_order_items_query;
    }

    #[On('order-item-updated')]
    #[On('order-item-created')]
    #[On('order-items-finalized')]
    #[On('order-items-bulk-edited')]
    public function with(): array
    {
        $inv_order_items_query = $this->InvOrderItemQuery();

        $inv_order_items = $inv_order_items_query->paginate($this->perPage);
        
        // Get users who have made evaluations
        $user_ids = InvOrderEval::whereIn('inv_order_item_id', 
            $inv_order_items_query->limit(1000)->pluck('id')
        )->distinct()->pluck('user_id');
        
        $this->users = User::whereIn('id', $user_ids)->orderBy('name')->get()->toArray();

        return [
            'inv_order_items' => $inv_order_items,
        ];
    }

    public function resetQuery()
    {
        session()->forget('inv_order_items_params');
        session()->forget('inv_areas_params');
        $this->redirect(route('inventory.orders.index'), navigate: true);
    }

    public function loadMore()
    {
        $this->perPage += 24;
    }

    public function bulkEditOrderItems()
    {
        $this->dispatch('bulk-edit-order-items', $this->order_item_ids);
        $this->js('$dispatch("open-slide-over", "order-bulk-edit")');
    }

    public function deleteOrderItems()
    {
        try {
            InvOrderItem::whereIn('id', $this->order_item_ids)
                ->whereNull('inv_order_id') // Only open orders
                ->delete();

            $this->js('toast("' . __('Butir pesanan berhasil dihapus') . '", { type: "success" })');
            $this->resetOrderItemIds();
        } catch (\Exception $e) {
            $this->js('toast("' . __('Terjadi kesalahan saat menghapus') . '", { type: "danger" })');
        }
    }

    #[On('order-items-finalized')]
    #[On('order-items-bulk-edited')]
    public function resetOrderItemIds()
    {
        $this->reset(['order_item_ids']);
    }

    public function updated($property)
    {
        $props = ['sort', 'area_ids', 'user_id', 'purpose'];
        if(in_array($property, $props)) {
            $this->reset(['perPage', 'order_item_ids']);
        }
    }

    public function download()
    {
        $token = md5(uniqid());
        session()->put('inv_order_items_token', $token);
        return redirect()->route('download.inv-order-items', ['token' => $token]);
    }
}

?>

<div x-data="{ 
        ids: @entangle('order_item_ids'),
        lastChecked: null,
        handleCheck(event, id) {
            if (event.shiftKey && this.lastChecked !== null) {
                const allIds = this.getAllIds();
                const start = allIds.indexOf(this.lastChecked);
                const end = allIds.indexOf(id);
                
                if (start !== -1 && end !== -1) {
                    const [lower, upper] = start < end ? [start, end] : [end, start];
                    
                    for (let i = lower; i <= upper; i++) {
                        const currentId = allIds[i];
                        if (!this.ids.includes(currentId)) {
                            this.ids.push(currentId);
                        }
                    }
                }
            }
            this.lastChecked = id;
        },
        getAllIds() {
            return Array.from(document.querySelectorAll('input[type=checkbox][id^=order-item-]'))
                    .map(checkbox => checkbox.value);
        }
    }">
    
    <div wire:key="order-items-modals">
        <x-slide-over name="order-finalize">
            <livewire:inventory.orders.order-finalize :$areas />
        </x-slide-over>
        <x-slide-over name="order-bulk-edit" focusable>
            <livewire:inventory.orders.order-bulk-edit />
        </x-slide-over>
        <x-slide-over name="order-item-show">
            <livewire:inventory.orders.order-item-show />
        </x-slide-over>
        <x-slide-over name="create-order-item">
            <livewire:inventory.orders.form />
        </x-slide-over>
    </div>

    <div class="static lg:sticky top-0 z-10 pb-6">
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
            
            <div class="flex items-center gap-x-4 p-4 lg:py-0 ">
                <x-inv-user-selector isQuery="true" class="text-xs font-semibold uppercase" />
            </div>

            <div class="grow flex items-center gap-x-4 p-4 lg:py-0 ">
                <x-inv-purpose-filter class="text-xs font-semibold uppercase" />
            </div>

            <div class="flex items-center justify-between gap-x-4 p-4 lg:py-0">
               <x-inv-area-selector is_grow="true" class="text-xs font-semibold uppercase" :$areas />
               <x-primary-button type="button" @click="$dispatch('open-slide-over', 'create-order-item')"
                    class="flex items-center gap-x-2">
                  <i class="icon-plus"></i>{{ __('Buat') }}
               </x-primary-button>
                <div>
                    <x-dropdown align="right" width="60">
                        <x-slot name="trigger">
                            <x-text-button><i class="icon-ellipsis"></i></x-text-button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link href="#" x-on:click="$dispatch('open-slide-over', 'order-finalize')">
                                <i class="icon-circle-check me-2"></i>{{ __('Finalisasi pesanan')}}
                            </x-dropdown-link>
                            <hr class="border-neutral-300 dark:border-neutral-600" />
                            <x-dropdown-link href="#" wire:click.prevent="resetQuery">
                                <i class="icon-rotate-cw me-2"></i>{{ __('Reset')}}
                            </x-dropdown-link>
                            <x-dropdown-link href="#" wire:click.prevent="download">
                                <i class="icon-download me-2"></i>{{ __('Unduh sebagai CSV') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </div>

    <div class="h-auto sm:h-12">
        <div x-show="!ids.length" class="flex items-center flex-col gap-y-6 sm:flex-row justify-between w-full h-full px-8">
            <div class="text-center sm:text-left">{{ ($inv_order_items->total() > 9999 ? ( '9999+' ) : $inv_order_items->total()) . ' ' . __('butir pesanan') }}</div>
            <div class="grow flex justify-center sm:justify-end">
                <x-select wire:model.live="sort" class="mr-3">
                    <option value="updated">{{ __('Diperbarui') }}</option>
                    <option value="name">{{ __('Nama') }}</option>
                    <option value="amount_low">{{ __('Amount terendah') }}</option>
                    <option value="amount_high">{{ __('Amount tertinggi') }}</option>
                    <option value="qty_low">{{ __('Qty terendah') }}</option>
                    <option value="qty_high">{{ __('Qty tertinggi') }}</option>
                </x-select>
            </div>
        </div>
        <div x-show="ids.length" x-cloak class="flex items-center justify-between w-full h-full px-8">
            <div class="font-bold"><span x-text="ids.length"></span><span>{{ ' ' . __('dipilih') }}</span></div>
            <div class="flex gap-x-2">
                <x-secondary-button type="button" x-on:click="ids = []; lastChecked = null">{{ __('Batal') }}</x-secondary-button>
                <div class="btn-group">
                    <x-secondary-button type="button" wire:click="deleteOrderItems" 
                        wire:confirm="{{ __('Yakin ingin menghapus butir pesanan yang dipilih?') }}">
                        <div class="relative">
                            <span wire:loading.class="opacity-0" wire:target="deleteOrderItems"><i class="icon-trash text-red-500"></i></span>
                            <x-spinner wire:loading.class.remove="hidden" wire:target="deleteOrderItems" class="hidden sm mono"></x-spinner>                
                        </div>
                    </x-secondary-button>
                    <x-secondary-button type="button" wire:click="bulkEditOrderItems">
                        <div class="relative">
                            <span wire:loading.class="opacity-0" wire:target="bulkEditOrderItems"><i class="icon-pen mr-2"></i>{{ __('Edit') }}</span>
                            <x-spinner wire:loading.class.remove="hidden" wire:target="bulkEditOrderItems" class="hidden sm mono"></x-spinner>
                        </div>
                    </x-secondary-button>
                </div>                
            </div>
        </div>
    </div>

    <div>
        @if (!$inv_order_items->count())
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
            <div class="p-0 sm:p-1 overflow-auto mt-6">
                <table class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg w-full table text-sm [&_th]:px-1 [&_th]:py-3 [&_td]:p-1">
                    <tr class="uppercase text-xs">
                        <th></th>
                        <th colspan="2">{{ __('Barang') }}</th>
                        <th>{{ __('Kode') }}</th>
                        <th>{{ __('Keperluan') }}</th>
                        <th>{{ __('Anggaran') }}</th>
                        <th class="flex justify-end">{{ __('Amount') }}</th>
                        <th>{{ __('Diperbarui') }}</th>
                        <th></th>
                    </tr>
                    @foreach ($inv_order_items as $order_item)
                        <x-inv-order-item-tr                                    
                            id="{{ $order_item->id }}"
                            qty="{{ $order_item->qty }}" 
                            uom="{{ $order_item->uom }}" 
                            item_photo="{{ $order_item->photo }}"
                            item_name="{{ $order_item->name }}"
                            item_desc="{{ $order_item->desc }}"
                            item_code="{{ $order_item->code }}"
                            purpose="{{ $order_item->purpose }}"
                            budget_name="{{ $order_item->inv_order_budget->name }}"
                            amount_budget="{{ $order_item->amount_budget }}"
                            budget_currency="{{ $order_item->inv_order_budget->inv_curr->name }}"
                            eval_count="{{ $order_item->inv_order_evals->count() }}"
                            updated_at="{{ $order_item->updated_at }}"
                            is_inventory_based="{{ !is_null($order_item->inv_item_id) }}">
                        </x-inv-order-item-tr>     
                    @endforeach
                </table>
            </div>
            
            <div wire:key="observer" class="flex items-center relative h-16">
                @if (!$inv_order_items->isEmpty())
                    @if ($inv_order_items->hasMorePages())
                        <div wire:key="more" x-data="{
                            observe() {
                                const observer = new IntersectionObserver((inv_order_items) => {
                                    inv_order_items.forEach(inv_order_item => {
                                        if (inv_order_item.isIntersecting) {
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