<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InvOrderItem;

new class extends Component
{
    public array $order_item = [
        'id' => 0,
        'name' => '',
        'desc' => '',
        'code' => '',
        'photo' => null,
    ];

    public array $evaluations = [];

    #[On('order-item-evals-show')]
    public function loadOrderItemEvals(int $id)
    {
        $orderItem = InvOrderItem::with([
            'inv_order_evals.user'
        ])->find($id);

        if ($orderItem) {
            $this->order_item = [
                'id' => $orderItem->id,
                'name' => $orderItem->name,
                'desc' => $orderItem->desc,
                'code' => $orderItem->code,
                'photo' => $orderItem->photo,
            ];

            $this->evaluations = $orderItem->inv_order_evals()
                ->with('user')
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($eval) {
                    return [
                        'id' => $eval->id,
                        'user_name' => $eval->user->name,
                        'user_emp_id' => $eval->user->emp_id,
                        'user_photo' => $eval->user->photo,
                        'qty_before' => $eval->qty_before,
                        'qty_after' => $eval->qty_after,
                        'quantity_change' => $eval->qty_after - $eval->qty_before,
                        'message' => $eval->message,
                        'created_at' => $eval->created_at->format('d/m/Y H:i'),
                        'created_at_diff' => $eval->created_at->diffForHumans(),
                    ];
                })
                ->toArray();

        } else {
            $this->handleNotFound();
        }
    }

    public function handleNotFound()
    {
        $this->js('slideOverOpen = false');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
    }
}

?>

<div class="h-full flex flex-col">
    <div class="p-6 border-b border-neutral-200 dark:border-neutral-700">
        <div class="flex justify-between items-start mb-4">
            <h2 class="text-lg font-medium">
                {{ __('Riwayat evaluasi') }}
            </h2>
            <x-text-button type="button" @click="slideOverOpen = false">
                <i class="icon-x"></i>
            </x-text-button>
        </div>

        {{-- Order Item Info --}}
        <div class="flex gap-x-3">
            <div class="rounded-sm overflow-hidden relative flex w-12 h-12 bg-neutral-200 dark:bg-neutral-700">
                <div class="m-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="block w-6 h-6 fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793">
                        <path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" />
                    </svg>
                </div>
                @if($order_item['photo'])
                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ '/storage/inv-order-items/' . $order_item['photo'] }}" />
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-medium truncate">{{ $order_item['name'] }}</div>
                <div class="text-sm text-neutral-500 truncate">{{ $order_item['desc'] }}</div>
                <div class="text-xs text-neutral-400">{{ $order_item['code'] ?: __('Tidak ada kode') }}</div>
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        @if(count($evaluations) > 0)
            <div class="p-6 space-y-4">
                @foreach($evaluations as $eval)
                    <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg p-4">
                        {{-- User Info and Timestamp --}}
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-x-2">
                                <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                    @if ($eval['user_photo'])
                                        <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/' . $eval['user_photo'] }}" />
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                            viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                            <path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                                        </svg>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium text-sm">{{ $eval['user_name'] }}</div>
                                    <div class="text-xs text-neutral-500">{{ $eval['user_emp_id'] }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-neutral-500">{{ $eval['created_at'] }}</div>
                                <div class="text-xs text-neutral-400">{{ $eval['created_at_diff'] }}</div>
                            </div>
                        </div>

                        {{-- Quantity Change --}}
                        <div class="mb-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Perubahan quantity') }}:</span>
                                <div class="flex items-center gap-x-2">
                                    <span class="font-mono">{{ $eval['qty_before'] }}</span>
                                    <i class="icon-arrow-right text-neutral-400"></i>
                                    <span class="font-mono">{{ $eval['qty_after'] }}</span>
                                    @if($eval['quantity_change'] != 0)
                                        <span class="ml-2 px-2 py-1 rounded text-xs {{ $eval['quantity_change'] > 0 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                            {{ $eval['quantity_change'] > 0 ? '+' : '' }}{{ $eval['quantity_change'] }}
                                        </span>
                                    @else
                                        <span class="ml-2 px-2 py-1 rounded text-xs bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200">
                                            {{ __('Tidak ada perubahan') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Message --}}
                        @if($eval['message'])
                            <div class="text-sm text-neutral-700 dark:text-neutral-300 bg-neutral-50 dark:bg-neutral-800 rounded p-3">
                                <div class="text-xs text-neutral-500 mb-1">{{ __('Alasan') }}:</div>
                                <div>{{ $eval['message'] }}</div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center text-neutral-400 dark:text-neutral-600">
                    <i class="icon-message-circle text-4xl mb-2"></i>
                    <div>{{ __('Belum ada evaluasi') }}</div>
                </div>
            </div>
        @endif
    </div>
</div>