@props([
      'is_print' => false,
      'id',
      'color',
      'icon',
      'qty_relative',
      'uom',
      'user_name',
      'user_photo',
      'is_delegated',
      'eval_status',
      'eval_user_name',
      'eval_user_emp_id',
      'updated_at',
      'remarks',
      'eval_icon',
      'item_photo',
      'item_name',
      'item_desc',
      'item_code',
      'item_loc',
      'checked' => false,
      'disabled' => false
    ])

<tr class="text-nowrap hover:bg-caldy-500 hover:bg-opacity-10 {{ $eval_status == 'rejected' ? 'opacity-50 grayscale' : '' }}">
   @if(!$is_print)
   <td class="w-[1%]">
      <label for="{{ 'circ-' . $id }}" class="flex p-2 gap-x-2">
         <input 
            {{ $checked ? 'checked' : '' }} 
            {{ $attributes->merge(
            ['class' => 'w-4 h-4 text-caldy-600 bg-neutral-100 border-neutral-300 rounded focus:ring-2 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-neutral-800 dark:bg-neutral-700 dark:border-neutral-600' ]) }}
            id="{{ 'circ-' . $id }}"
            type="checkbox"
            value="{{ $id }}"
            x-model="ids">
         <i class="fa fa-fw {{ $eval_icon }}"></i>
      </label>
   </td>
   @endif
   <td class="w-[1%]">
      <x-text-button class="truncate text-base" type="button" x-on:click="$dispatch('open-modal', 'circ-show'); $dispatch('circ-show', { id: '{{ $id }}'})">
         <span class="{{ $color }}"><i class="fa fa-fw {{ $icon }} mr-1"></i>{{ $qty_relative . ' ' . $uom }}</span>
      </x-text-button>
   </td>
   <td class="w-[1%]">
      <div class="rounded-sm overflow-hidden relative flex w-10 h-10 bg-neutral-200 dark:bg-neutral-700">
         <div class="m-auto">
            <svg xmlns="http://www.w3.org/2000/svg"  class="block w-10 h-10 fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793"><path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" /></svg>
         </div>
         @if($item_photo)
            <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ '/storage/inv-items/' . $item_photo }}" />
         @endif
      </div>   
   </td>
   <td class="max-w-40 truncate">
      {{ $item_name }}
   </td>
   <td class="max-w-40 truncate">
      {{ $item_desc }}
   </td>
   <td class="max-w-60">
      @if($item_loc)
         <span>{{ $item_code }}</span>
      @else
         <span class="text-neutral-500">{{ __('Tanpa kode') }}</span>
      @endif
   </td>
   <td class="max-w-60">
      @if($item_loc)
         <span>{{ $item_loc }}</span>
      @else
         <span class="text-neutral-500">{{ __('Tanpa lokasi') }}</span>
      @endif
   </td>
   <td class="max-w-60">
      <div class="flex items-center gap-x-1">
         <div>
            <div class="w-4 h-4 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
               @if ($user_photo)
                  <img class="w-full h-full object-cover dark:brightness-75"
                     src="{{ '/storage/users/' . $user_photo }}" />
               @else
                  <svg xmlns="http://www.w3.org/2000/svg"
                     class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                     viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                     <path
                           d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                  </svg>
               @endif
            </div>
         </div>
         <div>
            <span>{{ $user_name }}</span> 
            @if($is_delegated)
               <span title="{{ __('Didelegasikan') }}"><i class="fa fa-handshake-angle"></i></span>
            @endif
         </div>
      </div>
   </td>
   <td class="max-w-60 truncate">
      {{ $remarks }}
   </td>
   <td class="max-w-60">
      {{ $updated_at }}
   </td>
</tr>