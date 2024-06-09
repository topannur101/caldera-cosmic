@props(['active'])

@php
$classes = ($active ?? false)
            ? 'pb-2 border-b-2 border-caldy-400 dark:border-caldy-600 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:border-caldy-700 transition duration-150 ease-in-out'
            : 'pb-2 border-b-2 border-transparent text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300 hover:border-neutral-300 dark:hover:border-neutral-700 focus:outline-none focus:text-neutral-700 dark:focus:text-neutral-300 focus:border-neutral-300 dark:focus:border-neutral-700 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
