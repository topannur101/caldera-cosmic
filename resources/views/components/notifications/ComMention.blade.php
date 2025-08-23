<a
    href="{{ $url }}"
    @click="open = false"
    class="block w-full @if($presentation === 'page') p-4 @else px-4 py-2 @endif text-sm leading-5 transition duration-150 ease-in-out text-neutral-700 dark:text-neutral-300 @if($presentation === 'page') hover:bg-caldy-500 hover:bg-opacity-10 @else hover:bg-neutral-100 dark:hover:bg-neutral-800 @endif focus:outline-none focus:bg-neutral-100 dark:focus:bg-neutral-800"
    wire:navigate
>
    <div class="flex">
        <div>
            <div class="mt-1 @if($presentation === 'page') w-6 h-6 @else w-4 h-4 @endif bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                @if ($user?->photo)
                    <img class="w-full h-full object-cover dark:brightness-75" src="/storage/users/{{ $user->photo }}" />
                @else
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                        viewBox="0 0 1000 1000"
                        xmlns:v="https://vecta.io/nano"
                    >
                        <path
                            d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"
                        />
                    </svg>
                @endif
            </div>
        </div>
        <div class="grow ml-2">
            <div>
                <span class="font-bold">{{ $user?->name }}</span>
                {{ __("menyebutmu") }}: {{ $content }}
            </div>
            <div class="text-xs text-neutral-500">
                {{ $notification->created_at->diffForHumans() }}
            </div>
        </div>
        @if (! $notification->read_at)
            <div>
                <div class="w-2 h-2 mt-2 rounded-full bg-caldy-500"></div>
            </div>
        @endif
    </div>
</a>
