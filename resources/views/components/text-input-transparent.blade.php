@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full text-4xl text-center border-0 border-b-2 border-neutral-300 dark:border-neutral-700 bg-transparent dark:text-neutral-300 focus:border-0 focus:border-b-2 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-0']) !!}>
