@props(['status', 'size' => 'md'])

@php
    $sizeClasses = [
        'xs' => 'px-1.5 py-0.5 text-xs',
        'sm' => 'px-2 py-1 text-xs',
        'md' => 'px-2.5 py-1 text-sm',
        'lg' => 'px-3 py-1.5 text-sm'
    ];
    
    $statusConfig = [
        'todo' => [
            'label' => 'To Do',
            'colors' => 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600',
            'icon' => 'icon-circle'
        ],
        'in_progress' => [
            'label' => 'In Progress',
            'colors' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-600',
            'icon' => 'icon-play-circle'
        ],
        'review' => [
            'label' => 'Review',
            'colors' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 border border-yellow-300 dark:border-yellow-600',
            'icon' => 'icon-eye'
        ],
        'done' => [
            'label' => 'Done',
            'colors' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-300 dark:border-green-600',
            'icon' => 'icon-check-circle'
        ]
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['todo'];
@endphp

<span class="inline-flex items-center gap-1.5 {{ $sizeClasses[$size] }} {{ $config['colors'] }} rounded-full font-semibold">
    <i class="{{ $config['icon'] }} {{ $size === 'xs' ? 'text-xs' : 'text-sm' }}"></i>
    {{ $config['label'] }}
</span>