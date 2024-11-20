@props(['disabled' => false, 'readonly' => false, 'id', 'curr'])

<div class="relative">
    <label for="{{ $id }}"  class="text-xs uppercase absolute top-3 left-3 font-mono">{{ $curr }}</label>
    <input id="{{ $id }}" {{ $readonly ? 'readonly' : '' }} {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'block w-full pl-11 border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm']) !!}>
    @if($readonly)
    <i class="fa fa-lock absolute top-3 right-3"></i>
    @endif
</div>