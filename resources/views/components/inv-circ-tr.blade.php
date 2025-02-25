@props([
      'color',
      'icon',
      'qty_relative',
      'uom',
      'user_name',
      'is_delegated',
      'eval_user_name',
      'eval_user_emp_id',
      'created_at_friendly',
      'remarks',
      'eval_icon'
    ])

<tr class="text-sm hover:bg-caldy-500 hover:bg-opacity-10">
   <td class="px-3 sm:px-6">
      <x-text-button class="truncate text-base" type="button" x-on:click="$dispatch('open-modal', 'circ-edit')">
         <span class="{{ $color }}"><i class="fa fa-fw {{ $icon }} mr-1"></i>{{ $qty_relative . ' ' . $uom }}</span>
      </x-text-button>
   </td>
   <td class="max-w-60">
      <div class="flex items-center">
         <div>
            <div class="w-8 h-8 mr-2 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
               <svg xmlns="http://www.w3.org/2000/svg"
                  class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                  viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                  <path
                     d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
               </svg>
            </div>
         </div>
         <div class="truncate">
            <div class="text-xs text-neutral-400 dark:text-neutral-600">
               <span>{{ $user_name }}</span> 
               @if($is_delegated)
                  <span title="{{ __('Didelegasikan oleh:') . ' ' . $eval_user_name . ' (' . $eval_user_emp_id . ')' }}"><i class="fa fa-handshake-angle"></i></span>
               @endif
               <span class="mx-1">•</span><span>{{ $created_at_friendly }}</span>
            </div>
            <div class="text-base truncate">
               {{ $remarks }}
            </div>
         </div>
      </div>
   </td>
   <td class="px-3 sm:px-6">
      <i class="fa fa-fw {{ $eval_icon }}"></i>
   </t>
</tr>