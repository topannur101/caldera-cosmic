@props(['type', 'size', 'disabled'])

@php
$size = isset($size) ? $size : false;
$disabled = isset($disabled) ? $disabled : false;

switch ($size) {
    case 'lg':
        $classes = 'text-lg inline-flex items-center px-8 py-3 bg-neutral-800 dark:bg-neutral-200 border border-transparent rounded-md font-semibold text-white dark:text-neutral-800 uppercase tracking-widest hover:bg-neutral-700 dark:hover:bg-white focus:bg-neutral-700 dark:focus:bg-white active:bg-neutral-900 dark:active:bg-neutral-300 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 transition ease-in-out duration-150';
        break;
    
    default:
        $classes = 'text-xs inline-flex items-center px-4 py-2 bg-neutral-800 dark:bg-neutral-200 border border-transparent rounded-md font-semibold text-white dark:text-neutral-800 uppercase tracking-widest hover:bg-neutral-700 dark:hover:bg-white focus:bg-neutral-700 dark:focus:bg-white active:bg-neutral-900 dark:active:bg-neutral-300 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 transition ease-in-out duration-150';
        break;
}
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} {{ $disabled ? 'disabled' : '' }}>
    {{ $slot }}
</button>


