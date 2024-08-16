@props(['color'])

@php
$color = isset($color) ? $color : false;
switch ($color) {

   case 'green':
        $classes = 'text-xs font-medium me-2 px-2.5 py-0.5 rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        break;

   case 'red':
        $classes = 'text-xs font-medium me-2 px-2.5 py-0.5 rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        break;

    case 'yellow':
        $classes = 'text-xs font-medium me-2 px-2.5 py-0.5 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        break;
    
    default:
        $classes = 'text-xs font-medium me-2 px-2.5 py-0.5 rounded-full  bg-caldy-100 text-caldy-800 dark:bg-caldy-900 dark:text-caldy-200';
        break;
}
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>