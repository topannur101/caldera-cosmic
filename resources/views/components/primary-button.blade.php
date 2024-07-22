@props(['type', 'size'])

@php
$size = isset($size) ? $size : false;
$classes = $size
            ? 'text-' . $size . ' inline-flex items-center px-4 py-2 bg-neutral-800 dark:bg-neutral-200 border border-transparent rounded-md font-semibold text-white dark:text-neutral-800 uppercase tracking-widest hover:bg-neutral-700 dark:hover:bg-white focus:bg-neutral-700 dark:focus:bg-white active:bg-neutral-900 dark:active:bg-neutral-300 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 transition ease-in-out duration-150'
            : 'text-xs inline-flex items-center px-4 py-2 bg-neutral-800 dark:bg-neutral-200 border border-transparent rounded-md font-semibold text-white dark:text-neutral-800 uppercase tracking-widest hover:bg-neutral-700 dark:hover:bg-white focus:bg-neutral-700 dark:focus:bg-white active:bg-neutral-900 dark:active:bg-neutral-300 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 transition ease-in-out duration-150';
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>


