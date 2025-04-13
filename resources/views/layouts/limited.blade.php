<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-neutral-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-neutral-100 dark:bg-neutral-900">
            <div class="flex">
                <a href="/" wire:navigate>
                    <x-application-logo class="w-8 h-8 fill-current text-neutral-500" />
                </a>
                <div class="text-4xl text-neutral-500 my-auto tracking-widest ml-8">
                    Caldera
                </div>
            </div>

            <div class="w-full sm:max-w-md mt-6 p-6 bg-white dark:bg-neutral-800 shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
