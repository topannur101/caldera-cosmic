@props([
    "color",
])

@php
    $color = isset($color) ? $color : false;
    switch ($color) {
        case "green":
            $classes = "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300";
            break;

        case "red":
            $classes = "bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300";
            break;

        case "yellow":
            $classes = "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300";
            break;

        case "neutral":
            $classes = "bg-neutral-100 text-neutral-800 dark:bg-neutral-900 dark:text-neutral-300";
            break;

        case "neutral-lighter":
            $classes = "bg-neutral-200 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300";
            break;

        default:
            $classes = "bg-caldy-100 text-caldy-800 dark:bg-caldy-900 dark:text-caldy-200";
            break;
    }
@endphp

<div {{ $attributes->merge(["class" => $classes . " inline-block my-px text-xs font-medium px-2.5 py-0.5 rounded-full"]) }}>
    {{ $slot }}
</div>
