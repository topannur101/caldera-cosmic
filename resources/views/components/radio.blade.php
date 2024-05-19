@props(['disabled' => false, 'id', 'name', 'checked' => false])

<div class="flex items-center">
    <input {{ $disabled ? 'disabled' : '' }} {{ $checked ? 'checked' : '' }} id="{{ $id }}" name="{{ $name }}" type="radio" {{ $attributes->merge(['class' => 'w-4 h-4 text-caldy-600 bg-neutral-100 border-neutral-300 focus:ring-2 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-neutral-800 dark:bg-neutral-700 dark:border-neutral-600']) }} class="">
    <label for="{{ $id }}" class="p-2 text-sm">{{ $slot }}</label>
</div>