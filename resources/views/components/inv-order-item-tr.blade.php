@props([
    'id',
    'qty',
    'uom',
    'item_photo',
    'item_name',
    'item_desc',
    'item_code',
    'purpose',
    'budget_name',
    'amount_budget',
    'budget_currency',
    'eval_count',
    'updated_at',
    'is_inventory_based',
    'checked' => false,
    'disabled' => false,
])

<tr class="text-nowrap hover:bg-caldy-500 hover:bg-opacity-10">
    <td class="w-[1%]">
        <label for="{{ 'order-item-' . $id }}" class="flex items-center p-2 gap-x-2">
            <input 
                {{ $checked ? 'checked' : '' }} 
                {{ $attributes->merge([
                    'class' => 'w-4 h-4 text-caldy-600 bg-neutral-100 border-neutral-300 rounded focus:ring-2 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-neutral-800 dark:bg-neutral-700 dark:border-neutral-600'
                ]) }}
                id="{{ 'order-item-' . $id }}"
                type="checkbox"
                value="{{ $id }}"
                x-model="ids"
                x-on:click="handleCheck($event, '{{ $id }}')">
                @if($is_inventory_based)
                    <i class="icon-link-2 text-caldy-500" title="{{ __('Dari inventaris') }}"></i>
                @else
                    <i class="icon-unlink-2 text-neutral-500" title="{{ __('Manual entry') }}"></i>
                @endif
                <div class="text-base font-medium">
                    {{ $qty . ' ' . $uom }}
                </div>
        </label>
    </td>
    
    <td class="w-[1%]">
        <div class="rounded-sm overflow-hidden relative flex w-12 h-6 bg-neutral-200 dark:bg-neutral-700">
            <div class="m-auto">
                <svg xmlns="http://www.w3.org/2000/svg" class="block w-4 h-4 fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793">
                    <path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" />
                </svg>
            </div>
            @if($item_photo)
                <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ '/storage/inv-items/' . $item_photo }}" />
            @endif
        </div>   
    </td>
    
    <td class="max-w-40 truncate">
        <div class="truncate font-medium">{{ $item_name }}</div>
        <div class="truncate text-xs text-neutral-500">{{ $item_desc }}</div>
    </td>
    
    <td class="max-w-40">
        <span>{{ $item_code ?: '-' }}</span>
    </td>
    
    <td class="max-w-60 truncate" title="{{ $purpose }}">
        {{ $purpose }}
    </td>
    
    <td class="max-w-40 truncate">
        {{ $budget_name }}
    </td>
    
    <td class="max-w-40 text-right font-mono">
        {{ number_format($amount_budget, 2) }} {{ $budget_currency }}
    </td>
    
    <td class="max-w-40">
        {{ $updated_at }}
    </td>
    
    <td class="max-w-40">
        <div class="flex gap-x-1">
            <x-text-button type="button" 
                x-on:click="$dispatch('open-slide-over', 'order-item-show'); $dispatch('order-item-edit', { id: {{ $id }} })"
                title="{{ __('Edit') }}">
                <i class="icon-pen"></i>
            </x-text-button>
            @if($eval_count > 0)
                <x-text-button type="button" 
                class="ml-2 rounded-full text-xs px-2 bg-caldy-600 bg-opacity-40 text-white" 
                x-on:click="$dispatch('open-slide-over', 'order-item-show'); $dispatch('order-item-evals', { id: {{ $id }} })">
                    <i class="icon-message-square mr-1"></i>{{ $eval_count }}
                </x-text-button>
            @endif
        </div>
    </td>
</tr>