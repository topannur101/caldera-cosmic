<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use App\Models\InvOrder;
use App\Models\InvArea;
use App\Models\User;
use Carbon\Carbon;

new class extends Component {

    use WithPagination;

    public int $perPage = 24;

    public array $areas = [];
    public array $area_ids = [];
    public bool $area_multiple = false;

    #[Url]
    public string $q = '';
    
    #[Url]
    public string $date_fr = '';
    
    #[Url]
    public string $date_to = '';

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

        // Set default date range to current month
        if (!$this->date_fr) {
            $this->date_fr = now()->startOfMonth()->format('Y-m-d');
        }
        if (!$this->date_to) {
            $this->date_to = now()->endOfMonth()->format('Y-m-d');
        }

        $ordersParams = session('inv_orders_params', []);

        if ($ordersParams) {
            $this->q        = $ordersParams['q']        ?? '';
            $this->area_ids = $ordersParams['area_ids'] ?? [];
            $this->date_fr  = $ordersParams['date_fr']  ?? $this->date_fr;
            $this->date_to  = $ordersParams['date_to']  ?? $this->date_to;
        }

        $areasParams = session('inv_areas_params', []);

        if (!empty($areasParams)) {
            $this->area_ids = $areasParams['ids'] ?? [];
            
            if (count($this->area_ids) > 1) {
                $this->area_multiple = true;
            } else {
                $this->area_multiple = $areasParams['multiple'] ?? false;
            }
        } else {
            $this->area_multiple = false;
            $this->area_ids = !empty($this->areas) ? [$this->areas[0]['id']] : [];
        }
    }

    private function InvOrderQuery()
    {
        $q = trim($this->q);

        $inv_orders_params = [
            'q'        => $q,
            'area_ids' => $this->area_ids,
            'date_fr'  => $this->date_fr,
            'date_to'  => $this->date_to,
        ];

        $inv_areas_params = [
            'multiple'      => $this->area_multiple,
            'ids'           => $inv_orders_params['area_ids'],
        ];

        session(['inv_orders_params' => $inv_orders_params]);
        session(['inv_areas_params' => $inv_areas_params]);

        $inv_orders_query = InvOrder::with([
            'user',
            'inv_order_items.inv_area',
            'inv_order_items.inv_order_budget.inv_curr',
            'inv_order_budget_snapshots.inv_order_budget'
        ]);

        // Filter by areas through order items
        if (!empty($this->area_ids)) {
            $inv_orders_query->whereHas('inv_order_items', function($query) {
                $query->whereIn('inv_area_id', $this->area_ids);
            });
        }

        // Filter by date range
        if ($this->date_fr) {
            $inv_orders_query->whereDate('created_at', '>=', $this->date_fr);
        }
        if ($this->date_to) {
            $inv_orders_query->whereDate('created_at', '<=', $this->date_to);
        }

        // Search by order number or notes
        if($q) {
            $inv_orders_query->where(function ($query) use ($q) {
                $query->where('order_number', 'like', "%$q%")
                      ->orWhere('notes', 'like', "%$q%");
            });
        }

        $inv_orders_query->orderByDesc('created_at');

        return $inv_orders_query;
    }

    public function with(): array
    {
        $inv_orders_query = $this->InvOrderQuery();
        $orders = $inv_orders_query->paginate($this->perPage);
        
        // Group orders by month
        $grouped_orders = $orders->getCollection()->groupBy(function($order) {
            return $order->created_at->format('Y-m');
        });

        return [
            'inv_orders' => $orders,
            'grouped_orders' => $grouped_orders,
        ];
    }

    public function resetQuery()
    {
        session()->forget('inv_orders_params');
        session()->forget('inv_areas_params');
        $this->redirect(route('inventory.orders.index'), navigate: true);
    }

    public function loadMore()
    {
        $this->perPage += 24;
    }

    public function updated($property)
    {
        $props = ['area_ids', 'date_fr', 'date_to'];
        if(in_array($property, $props)) {
            $this->reset(['perPage']);
        }
    }

   public function resetDates()
   {
      $this->reset(['date_fr', 'date_to']);
   }

    public function download()
    {
        $token = md5(uniqid());
        session()->put('inv_orders_token', $token);
        return redirect()->route('download.inv-orders', ['token' => $token]);
    }

    public function getMonthLabel($monthKey)
    {
        return Carbon::createFromFormat('Y-m', $monthKey)->format('F Y');
    }
};

?>

<div>
    <div class="static lg:sticky top-0 z-10 pb-6">
        <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2">
            <div class="flex gap-x-2 items-center px-8 py-2 lg:px-4 lg:py-0">
                <i wire:loading.remove class="icon-search {{ $q ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600' }}"></i>
                <i wire:loading class="w-4 relative">
                    <x-spinner class="sm mono"></x-spinner>
                </i>
                <div class="w-full md:w-32">
                    <x-text-input-t wire:model.live="q" id="order-q" name="order-q" class="h-9 py-1 placeholder-neutral-400 dark:placeholder-neutral-600"
                        type="search" placeholder="{{ __('Cari pesanan...') }}" autocomplete="order-q" />
                </div>
            </div> 
            
            <div class="flex items-center gap-x-4 p-4 lg:py-0">
                <x-date-selector isQuery="true" class="text-xs font-semibold uppercase" />
            </div>

            <div class="grow"></div>

            <div class="flex items-center gap-x-4 p-4 lg:py-0">
                <x-inv-area-selector is_grow="true" class="text-xs font-semibold uppercase" :$areas />
            </div>

            <div class="flex items-center justify-between gap-x-4 p-4 lg:py-0">
                <div>
                    <x-dropdown align="right" width="60">
                        <x-slot name="trigger">
                            <x-text-button><i class="icon-ellipsis"></i></x-text-button>
                        </x-slot>
                        <x-slot name="content">
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

    {{-- Orders List --}}
    <div class="h-auto sm:h-12 mb-6">
        <div class="flex items-center flex-col gap-y-6 sm:flex-row justify-between w-full h-full px-8">
            <div class="text-center sm:text-left">{{ ($inv_orders->total() > 9999 ? ( '9999+' ) : $inv_orders->total()) . ' ' . __('pesanan') }}</div>
        </div>
    </div>

    <div>
        @if (!$inv_orders->count())
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
            {{-- Grouped Orders by Month --}}
            @foreach($grouped_orders as $month => $orders)
                <div class="mb-8">
                    {{-- Month Header --}}
                    <div class="bg-neutral-50 dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-700 px-6 py-3 mb-4">
                        <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ $this->getMonthLabel($month) }}
                            <span class="text-sm font-normal text-neutral-500 ml-2">({{ $orders->count() }} {{ __('pesanan') }})</span>
                        </h3>
                    </div>

                    {{-- Orders in this month --}}
                    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
                        <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach($orders as $order)
                                <div class="p-6 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-x-4">
                                                <x-link href="{{ route('inventory.orders.show', $order->id) }}" wire:navigate 
                                                       class="text-lg font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500">
                                                    {{ $order->order_number }}
                                                </x-link>
                                                <span class="text-sm text-neutral-500">
                                                    {{ $order->created_at->format('d M Y, H:i') }}
                                                </span>
                                            </div>
                                            
                                            <div class="mt-2 flex items-center gap-x-6 text-sm text-neutral-600 dark:text-neutral-400">
                                                <div>
                                                    <i class="icon-user mr-1"></i>{{ $order->user->name }}
                                                </div>
                                                <div>
                                                    <i class="icon-package mr-1"></i>{{ $order->inv_order_items->count() }} {{ __('item') }}
                                                </div>
                                                <div>
                                                    <i class="icon-map-pin mr-1"></i>{{ $order->inv_order_items->pluck('inv_area.name')->unique()->implode(', ') }}
                                                </div>
                                            </div>

                                            @if($order->notes)
                                                <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                                                    <i class="icon-message-square mr-1"></i>{{ $order->notes }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="text-right">
                                            <div class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                                                {{ $order->getTotalBudgetAllocation() ? number_format($order->getTotalBudgetAllocation(), 2) : '0.00' }}
                                            </div>
                                            <div class="text-sm text-neutral-500">
                                                {{ $order->inv_order_budget_snapshots->first()?->inv_order_budget?->inv_curr?->name ?? 'IDR' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            <div wire:key="observer" class="flex items-center relative h-16">
                @if (!$inv_orders->isEmpty())
                    @if ($inv_orders->hasMorePages())
                        <div wire:key="more" x-data="{
                            observe() {
                                const observer = new IntersectionObserver((inv_orders) => {
                                    inv_orders.forEach(inv_order => {
                                        if (inv_order.isIntersecting) {
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