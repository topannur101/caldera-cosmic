@props(['id', 'grow'])

<div class="cal-radio relative {{ isset($grow) ? 'grow' : ''}}">
   <input type="radio" id="{{ $id }}" {{ $attributes->merge(['class' => 'w-px h-px absolute top-0 left-0 opacity-0']) }} />
   <label for="{{ $id }}" class="flex gap-x-3 h-full cursor-pointer px-4 py-2 opacity-50 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-500 font-semibold text-xs text-neutral-700 dark:text-neutral-300 uppercase shadow-sm hover:bg-neutral-50 dark:hover:bg-neutral-700  disabled:opacity-25 transition ease-in-out duration-150">
      <div class="cal-checked w-1 h-1 my-auto bg-caldy-500 rounded-full hidden"></div>
      {{ $slot }}
   </label>
</div>
