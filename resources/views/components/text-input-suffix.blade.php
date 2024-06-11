@props(['disabled' => false, 'suffix'])

<div class="flex">
   <input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'z-10 rounded-l-md block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 shadow-sm']) !!}>
   <div class="flex rounded-r-md px-4 py-2 bg-white dark:bg-neutral-800 border-y border-r border-neutral-300 dark:border-neutral-700 font-semibold text-xs text-neutral-700 dark:text-neutral-300 uppercase tracking-widest shadow-sm">
      <div class="my-auto">{{ $suffix }}</div>
   </div>
</div>