@props(['disabled' => false])

<a @if(!$disabled) @click="open = false" @endif {{ $attributes->merge(
['class' => 'block w-full px-4 py-2 text-sm leading-5 transition duration-150 ease-in-out ' . ($disabled 
? 'text-neutral-300 dark:text-neutral-600' 
: 'text-neutral-700 dark:text-neutral-300
hover:bg-neutral-100 dark:hover:bg-neutral-800
focus:outline-none focus:bg-neutral-100 dark:focus:bg-neutral-800 ')]) }}>{{ $slot }}</a>
