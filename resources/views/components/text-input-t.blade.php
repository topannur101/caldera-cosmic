@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} 
{!! $attributes->merge([
'class' => 'w-full appearance-none border-transparent hover:border-neutral-300 hover:dark:border-neutral-700
bg-transparent dark:focus:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 
dark:focus:border-caldy-600 focus:ring-caldy-500 
dark:focus:ring-caldy-600 rounded-md']) !!}>
