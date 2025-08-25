@props(['disabled' => false, 'checked' => false])

<label class="relative inline-flex items-center cursor-pointer">
   <input {{ $disabled ? 'disabled' : '' }} {{ $checked ? 'checked' : '' }} type="checkbox" {{ $attributes->merge(['class' => 'sr-only peer']) }}>
   <div class="w-9 h-5 bg-neutral-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-caldy-300 dark:peer-focus:ring-caldy-800 rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-neutral-600 peer-checked:bg-caldy-600"></div>
   <span class="ml-3 text-sm">{{ $slot }}</span>
</label>