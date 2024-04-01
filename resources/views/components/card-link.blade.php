@props(['rounded'])

@php
    $classes =
        'block bg-white dark:bg-neutral-800  shadow overflow-hidden focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 transition ease-in-out duration-150';
    if (isset($rounded)) {
        switch ($rounded) {
            case 'sm':
                $classes .= ' sm:rounded-sm';
                break;

            case 'md':
                $classes .= ' sm:rounded-md';
                break;
        }
    } else {
        $classes .= ' sm:rounded-lg';
    }

@endphp

<a {{ $attributes->merge(['class' => $classes]) }}> {{ $slot }}
</a>
