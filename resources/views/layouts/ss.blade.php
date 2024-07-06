<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if (isset($title))
        <title>{{ $title }}</title>
    @else
        <title>{{ __('Tayangan slide') }}</title>
    @endif 

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-neutral-600 dark:text-neutral-200">
    <div class="min-h-screen bg-neutral-200 dark:bg-neutral-900">
        {{ $slot }}
    </div>
    @livewireScripts

</body>

</html>
