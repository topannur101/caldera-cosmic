<button {{ $attributes->merge(['class' => 'bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>