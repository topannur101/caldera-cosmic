<button type="button" {{ $attributes->merge(['class' => 'sm:rounded-lg text-left hover:bg-white dark:hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>