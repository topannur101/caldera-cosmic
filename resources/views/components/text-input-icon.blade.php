@props(['disabled' => false, 'id', 'icon'])

<div class="relative">
    <label for="{{ $id }}" class="absolute top-3 left-3 opacity-30 leading-none"><i class="{{ $icon }}"></i></label>
    <input id="{{ $id }}" {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'block w-full pl-10 border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm']) !!}>
</div>