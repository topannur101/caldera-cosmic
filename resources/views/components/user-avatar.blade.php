@props(['user', 'size' => 'default'])

@php
$sizeClasses = [
    'xs' => 'w-5 h-5',
    'sm' => 'w-6 h-6',
    'default' => 'w-8 h-8',
    'lg' => 'w-10 h-10',
];

$textSizeClasses = [
    'xs' => 'text-xs',
    'sm' => 'text-sm',
    'default' => 'text-sm',
    'lg' => 'text-base',
];

$sizeClass = $sizeClasses[$size] ?? $sizeClasses['default'];
$textSizeClass = $textSizeClasses[$size] ?? $textSizeClasses['default'];
@endphp

<div class="inline-flex items-center gap-2">
    <div class="relative {{ $sizeClass }} rounded-full overflow-hidden bg-neutral-200 dark:bg-neutral-700 flex-shrink-0">
        @if($user->photo)
            <img src="{{ '/storage/users/' . $user->photo }}" 
                 alt="{{ $user->name }}" 
                 class="w-full h-full object-cover" />
        @else
            <div class="w-full h-full flex items-center justify-center {{ $textSizeClass }} text-neutral-600 dark:text-neutral-400">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
        @endif
    </div>
    @if($size !== 'xs')
        <div class="min-w-0">
            <div class="font-medium {{ $textSizeClass }} text-neutral-900 dark:text-neutral-100 truncate">
                {{ $user->name }}
            </div>
            @if($size === 'lg' && $user->emp_id)
                <div class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ $user->emp_id }}
                </div>
            @endif
        </div>
    @endif
</div>