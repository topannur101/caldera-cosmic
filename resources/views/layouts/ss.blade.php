<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    {{-- <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" /> --}}

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-neutral-800 dark:text-neutral-200">
    <div class="min-h-screen p-4 bg-neutral-100 dark:bg-neutral-900">
        {{-- <div class="flex">
                <a href="/" wire:navigate>
                    <x-application-logo class="w-8 h-8 fill-current text-neutral-500" />
                </a>
                <div class="text-4xl text-neutral-500 my-auto tracking-widest ml-8">
                    Caldera
                </div>
            </div> --}}
        <div class="w-full h-full grid grid-cols-2 grid-rows-2 gap-4 ">
            <div class="col-span-2 px-4 py-8">
                <div class="grid grid-cols-6 grid-rows-1 gap-4">
                    <div class="col-span-2">
                        <div>
                            <div class="text-xl uppercase mb-3 mx-1">{{ __('Model') }}</div>
                            <div class="text-5xl">Placeholder model name</div>
                        </div>
                    </div>
                    <div class="col-start-3">
                        <div>
                            <div class="text-xl uppercase mb-3 mx-1">{{ __('OG/RS') }}</div>
                            <div class="text-7xl py-3">000</div>
                        </div>
                    </div>
                    <div class="col-start-4">
                        <div>
                            <div class="text-xl uppercase mb-3 mx-1">{{ __('Min') }}</div>
                            <div class="text-7xl py-3">0.00</div>
                        </div>
                    </div>
                    <div class="col-start-5">
                        <div>
                            <div class="text-xl uppercase mb-3 mx-1">{{ __('Max') }}</div>
                            <div class="text-7xl py-3">0.00</div>
                        </div>
                    </div>
                    <div class="col-start-6">                        <div>
                        <div class="text-xl uppercase mb-3 mx-1">{{ __('Line') }}</div>
                        <x-select class="text-7xl">
                            <option value="1">1</option>
                        </x-select>
                    </div></div>
                </div>
            </div>
            <div class="col-start-1 row-start-2">
                <div class="w-full px-8 py-6 bg-white dark:bg-neutral-800 shadow-md overflow-hidden sm:rounded-lg">
                    <div class="flex justify-between">
                        <div class="text-xl uppercase">{{ __('Kiri') }}</div>
                        <div class="text-7xl">0.00</div>
                    </div>
                </div>
            </div>
            <div class="col-start-2 row-start-2">
                <div class="w-full px-8 py-6 bg-white dark:bg-neutral-800 shadow-md overflow-hidden sm:rounded-lg">
                    <div class="flex justify-between">
                        <div class="text-xl uppercase">{{ __('Kanan') }}</div>
                        <div class="text-7xl">0.00</div>
                    </div>
                </div>
            </div>
        </div>
        {{-- <div class="w-full px-6 py-4 bg-white dark:bg-neutral-800 shadow-md overflow-hidden sm:rounded-lg">
                Content here
            </div> --}}
        {{-- <div class="mt-10">
                <x-link class="text-sm uppercase font-medium leading-5 text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300" href="{{ route('home') }}" wire:navigate>{{ __('Beranda') }}</x-link>
            </div> --}}
    </div>
</body>

</html>
