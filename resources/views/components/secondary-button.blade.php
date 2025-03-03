@props(['type', 'size', 'disabled'])

@php
$size = isset($size) ? $size : false;
$disabled = isset($disabled) ? $disabled : false;

switch ($size) {
    case 'sm':
        $classes = 'text-xs inline-flex items-center px-2 py-1 bg-white dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 border border-neutral-300 dark:border-neutral-700 rounded-full font-semibold text-neutral-700 dark:text-neutral-300 uppercase shadow-sm focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 transition ease-in-out duration-150';
        break;

    case 'lg':
        $classes = 'text-lg inline-flex items-center px-8 py-3 bg-white dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 border border-neutral-300 dark:border-neutral-500 rounded-md font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-widest shadow-sm focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 transition ease-in-out duration-150';
        break;
    
    default:
        $classes = 'text-xs inline-flex items-center px-4 py-2 bg-white dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 border border-neutral-300 dark:border-neutral-500 rounded-md font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-widest shadow-sm focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 transition ease-in-out duration-150';
        break;
}
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} {{ $disabled ? 'disabled' : '' }}>
    {{ $slot }}
</button>
