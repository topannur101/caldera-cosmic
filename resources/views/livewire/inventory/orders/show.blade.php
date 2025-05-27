<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use App\Models\InvOrder;
use App\Models\InvOrderBudgetSnapshot;
use App\Models\InvOrderItem;
use App\Models\InvOrderBudget;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')]
class extends Component
{
    #[Url]
    public int $id = 0;

    public array $order = [];
    public array $order_items = [];
    public array $budget_snapshots = [];
    public array $budget_summaries = [];
    public bool $can_cancel = true;

    public function mount()
    {
        $order = InvOrder::with([
            'user',
            'inv_order_items.inv_area',
            'inv_order_items.inv_curr',
            'inv_order_items.inv_order_budget.inv_curr',
            'inv_order_items.inv_item',
            'inv_order_items.inv_order_evals.user',
            'inv_order_budget_snapshots.inv_order_budget.inv_curr'
        ])->findOrFail($this->id);

        if ($order) {
            $this->order = [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'notes' => $order->notes,
                'created_at' => $order->created_at->format('d M Y, H:i'),
                'created_at_diff' => $order->created_at->diffForHumans(),
                'user_name' => $order->user->name,
                'user_emp_id' => $order->user->emp_id,
                'user_photo' => $order->user->photo,
                'total_items' => $order->inv_order_items->count(),
                'total_budget_allocation' => $order->getTotalBudgetAllocation(),
            ];

            $this->order_items = $order->inv_order_items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'desc' => $item->desc,
                    'code' => $item->code,
                    'photo' => $item->photo,
                    'purpose' => $item->purpose,
                    'qty' => $item->qty,
                    'uom' => $item->uom,
                    'unit_price' => $item->unit_price,
                    'total_amount' => $item->total_amount,
                    'amount_budget' => $item->amount_budget,
                    'exchange_rate_used' => $item->exchange_rate_used,
                    'area_name' => $item->inv_area->name,
                    'currency_name' => $item->inv_curr->name,
                    'budget_name' => $item->inv_order_budget->name,
                    'budget_currency' => $item->inv_order_budget->inv_curr->name,
                    'is_inventory_based' => !is_null($item->inv_item_id),
                    'eval_count' => $item->inv_order_evals->count(),
                ];
            })->toArray();

            $this->budget_snapshots = $order->inv_order_budget_snapshots->map(function ($snapshot) {
                return [
                    'id' => $snapshot->id,
                    'budget_name' => $snapshot->inv_order_budget->name,
                    'currency_name' => $snapshot->inv_order_budget->inv_curr->name,
                    'balance_before' => $snapshot->balance_before,
                    'balance_after' => $snapshot->balance_after,
                    'allocated_amount' => $snapshot->allocated_amount,
                    'budget_usage_percentage' => $snapshot->budget_usage_percentage,
                ];
            })->toArray();

            // Calculate budget summaries by currency
            $this->budget_summaries = collect($this->budget_snapshots)
                ->groupBy('currency_name')
                ->map(function ($snapshots, $currency) {
                    return [
                        'currency' => $currency,
                        'total_allocated' => $snapshots->sum('allocated_amount'),
                        'affected_budgets' => $snapshots->count(),
                    ];
                })->values()->toArray();
        }
    }

    public function cancelOrder()
    {
        if (!$this->can_cancel) {
            $this->js('toast("' . __('Pesanan tidak dapat dibatalkan') . '", { type: "danger" })');
            return;
        }

        try {
            DB::beginTransaction();

            $order = InvOrder::findOrFail($this->id);

            // 1. Restore budget balances
            foreach ($order->inv_order_budget_snapshots as $snapshot) {
                $budget = InvOrderBudget::find($snapshot->inv_order_budget_id);
                if ($budget) {
                    $budget->update([
                        'balance' => $snapshot->balance_before
                    ]);
                }
            }

            // 2. Revert order items back to open orders (set inv_order_id to null)
            InvOrderItem::where('inv_order_id', $order->id)
                ->update(['inv_order_id' => null]);

            // 3. Delete the order (this will cascade delete budget snapshots)
            $order->delete();

            DB::commit();

            $this->js('toast("' . __('Pesanan berhasil dibatalkan dan budget dikembalikan') . '", { type: "success" })');
            
            // Redirect back to orders list
            $this->redirect(route('inventory.orders.index', ['view' => 'order-list']), navigate: true);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->js('toast("' . __('Terjadi kesalahan saat membatalkan pesanan') . '", { type: "danger" })');
        }
    }

    public function getTotalAmountsByCurrency(): array
    {
        $amounts = [];
        foreach ($this->order_items as $item) {
            $currency = $item['currency_name'];
            if (!isset($amounts[$currency])) {
                $amounts[$currency] = 0;
            }
            $amounts[$currency] += $item['total_amount'];
        }
        return $amounts;
    }
}

?>

<x-slot name="title">{{ $order['order_number'] . ' — ' . __('Pesanan') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Detail pesanan') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div class="space-y-8">
        
        {{-- Order Header --}}
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
            <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                            {{ $order['order_number'] }}
                        </h1>
                        <div class="mt-2 flex items-center gap-x-6 text-sm text-neutral-600 dark:text-neutral-400">
                            <div>
                                <i class="icon-calendar mr-1"></i>{{ $order['created_at'] }}
                            </div>
                            <div>
                                <i class="icon-clock mr-1"></i>{{ $order['created_at_diff'] }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-x-4">
                        <x-secondary-button type="button" 
                            wire:click="cancelOrder"
                            wire:confirm="{{ __('Yakin ingin membatalkan pesanan ini? Budget akan dikembalikan dan item akan kembali ke status terbuka.') }}"
                            class="text-red-600 hover:text-red-500">
                            <i class="icon-x-circle mr-2"></i>{{ __('Batalkan Pesanan') }}
                        </x-secondary-button>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {{-- User Info --}}
                    <div>
                        <h3 class="text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">{{ __('Dibuat oleh') }}</h3>
                        <div class="flex items-center gap-x-3">
                            <div class="w-10 h-10 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                @if ($order['user_photo'])
                                    <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/' . $order['user_photo'] }}" />
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                        viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                        <path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <div class="font-medium">{{ $order['user_name'] }}</div>
                                <div class="text-sm text-neutral-500">{{ $order['user_emp_id'] }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Order Summary --}}
                    <div>
                        <h3 class="text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">{{ __('Ringkasan') }}</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm">{{ __('Total item') }}:</span>
                                <span class="text-sm font-medium">{{ $order['total_items'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm">{{ __('Budget dialokasikan') }}:</span>
                                <span class="text-sm font-medium">{{ number_format($order['total_budget_allocation'], 2) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Amount by Currency --}}
                    <div>
                        <h3 class="text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">{{ __('Total per mata uang') }}</h3>
                        <div class="space-y-2">
                            @foreach($this->getTotalAmountsByCurrency() as $currency => $amount)
                                <div class="flex justify-between">
                                    <span class="text-sm">{{ $currency }}:</span>
                                    <span class="text-sm font-medium">{{ number_format($amount, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                @if($order['notes'])
                    <div class="mt-6 pt-6 border-t border-neutral-200 dark:border-neutral-700">
                        <h3 class="text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-2">{{ __('Catatan') }}</h3>
                        <p class="text-sm text-neutral-700 dark:text-neutral-300">{{ $order['notes'] }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Budget Impact --}}
        @if(count($budget_snapshots) > 0)
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Dampak Budget') }}
                    </h2>
                </div>
                <div class="p-6">
                    {{-- Budget Summary by Currency --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        @foreach($budget_summaries as $summary)
                            <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4">
                                <div class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ $summary['currency'] }}</div>
                                <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                                    {{ number_format($summary['total_allocated'], 2) }}
                                </div>
                                <div class="text-xs text-neutral-500">
                                    {{ $summary['affected_budgets'] }} {{ __('budget terdampak') }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Detailed Budget Snapshots --}}
                    <div class="space-y-4">
                        @foreach($budget_snapshots as $snapshot)
                            <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="font-medium">{{ $snapshot['budget_name'] }}</div>
                                    <div class="text-sm text-neutral-500">{{ $snapshot['currency_name'] }}</div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                                    <div>
                                        <span class="text-neutral-600 dark:text-neutral-400">{{ __('Saldo sebelum') }}:</span>
                                        <div class="font-mono">{{ number_format($snapshot['balance_before'], 2) }}</div>
                                    </div>
                                    <div>
                                        <span class="text-neutral-600 dark:text-neutral-400">{{ __('Dialokasikan') }}:</span>
                                        <div class="font-mono text-red-600 dark:text-red-400">-{{ number_format($snapshot['allocated_amount'], 2) }}</div>
                                    </div>
                                    <div>
                                        <span class="text-neutral-600 dark:text-neutral-400">{{ __('Saldo sesudah') }}:</span>
                                        <div class="font-mono">{{ number_format($snapshot['balance_after'], 2) }}</div>
                                    </div>
                                    <div>
                                        <span class="text-neutral-600 dark:text-neutral-400">{{ __('Persentase') }}:</span>
                                        <div class="font-mono">{{ number_format($snapshot['budget_usage_percentage'], 1) }}%</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Order Items --}}
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
            <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Item Pesanan') }}
                    <span class="text-sm font-normal text-neutral-500 ml-2">({{ count($order_items) }} {{ __('item') }})</span>
                </h2>
            </div>
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach($order_items as $item)
                    <div class="p-6">
                        <div class="flex items-start gap-x-4">
                            {{-- Item Photo --}}
                            <div class="rounded-sm overflow-hidden relative flex w-16 h-16 bg-neutral-200 dark:bg-neutral-700 flex-shrink-0">
                                <div class="m-auto">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block w-8 h-8 fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793">
                                        <path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" />
                                    </svg>
                                </div>
                                @if($item['photo'])
                                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ '/storage/inv-order-items/' . $item['photo'] }}" />
                                @endif
                            </div>

                            {{-- Item Details --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-x-2 mb-1">
                                            <h3 class="font-medium text-neutral-900 dark:text-neutral-100">{{ $item['name'] }}</h3>
                                            @if($item['is_inventory_based'])
                                                <i class="icon-link-2 text-caldy-500 text-sm" title="{{ __('Dari inventaris') }}"></i>
                                            @else
                                                <i class="icon-unlink-2 text-neutral-500 text-sm" title="{{ __('Manual entry') }}"></i>
                                            @endif
                                        </div>
                                        
                                        <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ $item['desc'] }}</p>
                                        
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <span class="text-neutral-500">{{ __('Kode') }}:</span>
                                                <div class="font-mono">{{ $item['code'] ?: '-' }}</div>
                                            </div>
                                            <div>
                                                <span class="text-neutral-500">{{ __('Area') }}:</span>
                                                <div>{{ $item['area_name'] }}</div>
                                            </div>
                                            <div>
                                                <span class="text-neutral-500">{{ __('Quantity') }}:</span>
                                                <div class="font-mono">{{ $item['qty'] }} {{ $item['uom'] }}</div>
                                            </div>
                                            <div>
                                                <span class="text-neutral-500">{{ __('Budget') }}:</span>
                                                <div>{{ $item['budget_name'] }}</div>
                                            </div>
                                        </div>

                                        <div class="mt-3 p-3 bg-neutral-50 dark:bg-neutral-900 rounded">
                                            <div class="text-xs text-neutral-500 mb-1">{{ __('Keperluan') }}:</div>
                                            <div class="text-sm">{{ $item['purpose'] }}</div>
                                        </div>
                                    </div>

                                    {{-- Pricing Info --}}
                                    <div class="text-right ml-6">
                                        <div class="space-y-1 text-sm">
                                            <div>
                                                <span class="text-neutral-500">{{ __('Harga satuan') }}:</span>
                                                <div class="font-mono">{{ $item['currency_name'] }} {{ number_format($item['unit_price'], 2) }}</div>
                                            </div>
                                            <div>
                                                <span class="text-neutral-500">{{ __('Total') }}:</span>
                                                <div class="font-mono font-medium">{{ $item['currency_name'] }} {{ number_format($item['total_amount'], 2) }}</div>
                                            </div>
                                            @if($item['exchange_rate_used'] != 1)
                                                <div class="pt-2 border-t border-neutral-200 dark:border-neutral-700">
                                                    <span class="text-neutral-500">{{ __('Budget') }}:</span>
                                                    <div class="font-mono font-medium">{{ $item['budget_currency'] }} {{ number_format($item['amount_budget'], 2) }}</div>
                                                    <div class="text-xs text-neutral-400">Rate: {{ $item['exchange_rate_used'] }}</div>
                                                </div>
                                            @endif
                                        </div>

                                        @if($item['eval_count'] > 0)
                                            <div class="mt-3">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    <i class="icon-message-square mr-1"></i>{{ $item['eval_count'] }} {{ __('evaluasi') }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>