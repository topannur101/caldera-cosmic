<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ session('bg') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @if (isset($title))
            <title>{{ $title }}</title>
        @else
            <title>Caldera Cosmic</title>
        @endif 

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans {{ session('accent') }} antialiased">
        <div class="min-h-screen bg-neutral-100 dark:bg-neutral-900 overflow-hidden">
            <livewire:layout.navigation />

            <!-- Page Header -->
            @if (isset($header))
            {{ $header }}
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        <!-- Page Printable -->
        @if (isset($printable))
        {{ $printable }}
        @endif
        
    </body>
</html>
