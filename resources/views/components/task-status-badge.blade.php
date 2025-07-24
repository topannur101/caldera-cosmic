@props(['status', 'size' => 'default'])

@php
$colors = [
    'todo' => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-300',
    'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    'review' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    'done' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
];

$labels = [
    'todo' => __('To Do'),
    'in_progress' => __('Dalam Proses'),
    'review' => __('Review'),
    'done' => __('Selesai'),
];

$sizeClasses = [
    'xs' => 'px-1.5 py-0.5 text-xs',
    'sm' => 'px-2 py-1 text-xs',
    'default' => 'px-2.5 py-1 text-sm',
];

$colorClass = $colors[$status] ?? $colors['todo'];
$label = $labels[$status] ?? $status;
$sizeClass = $sizeClasses[$size] ?? $sizeClasses['default'];
@endphp

<span class="inline-flex items-center {{ $sizeClass }} font-medium rounded-full {{ $colorClass }}">
    {{ $label }}
</span>