@props([
    "guests",
    "users",
    "time",
    "centered" => false,
    "bgColors" => [
        "bg-blue-200 dark:bg-blue-700",
        "bg-green-200 dark:bg-green-700",
        "bg-yellow-200 dark:bg-yellow-700",
    ],
])

<div class="max-w-4xl mx-auto text-neutral-500 text-sm mb-10">
    <div class="{{ $centered ? "text-center" : "" }} mb-8">
        <div class="mb-1">{{ __("Waktu server:") . " " . $time }}</div>
        <div>{{ $users->count() + $guests->count() . " " . __("pengguna daring") }}</div>
    </div>
    <div class="flex flex-wrap {{ $centered ? "justify-center" : "" }} gap-3">
        @foreach ($users as $user)
            <div class="inline-block bg-white dark:bg-neutral-800 rounded-full p-2">
                <div class="flex w-28 h-full truncate items-center gap-2">
                    <div>
                        <div class="w-6 h-6 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                            @if ($user->photo)
                                <img class="w-full h-full object-cover dark:brightness-75" src="{{ "/storage/users/" . $user->photo }}" />
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
                    <div class="truncate">{{ $user->name }}</div>
                </div>
            </div>
        @endforeach

        <div class="inline-block bg-white dark:bg-neutral-800 rounded-full p-2">
            <div class="flex w-28 h-full truncate items-center gap-2">
                <div>
                    <div class="flex items-center">
                        @for ($i = 0; $i < min($guests->count(), 3); $i++)
                            <div class="w-6 h-6 {{ $bgColors[$i] }} rounded-full overflow-hidden {{ $i > 0 ? "-ml-4" : "" }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200" viewBox="0 0 1000 1000">
                                    <path
                                        d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"
                                    />
                                </svg>
                            </div>
                        @endfor
                    </div>
                </div>
                <div class="truncate">{{ $guests->count() . " " . __("tamu") }}</div>
            </div>
        </div>
        <div class="-ml-4 bg-blue-200 dark:bg-blue-700 bg-green-200 dark:bg-green-700 bg-yellow-200 dark:bg-yellow-700"></div>
    </div>
</div>
