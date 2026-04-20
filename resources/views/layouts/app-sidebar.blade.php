<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ session('bg') }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        @if (isset($title))
            <title>{{ $title }}</title>
        @else
            <title>Caldera Cosmic</title>
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans {{ session('accent') }} {{ session('pattern') }} antialiased">
        @php
            $activeGroup = [
                'rubber' => request()->routeIs('insights.omv*') || request()->routeIs('insights.ctc*') || request()->routeIs('insights.rdc*'),
                'assembly' => request()->routeIs('insights.dwp*') || request()->routeIs('insights.bpm*'),
                'ip' => request()->routeIs('insights.clm*') || request()->routeIs('insights.stc*') || request()->routeIs('insights.ibms*'),
                'okc' => request()->routeIs('insights.ldc*'),
                'stockfit' => request()->routeIs('insights.pds*'),
            ];
        @endphp

        <div
            x-data="{
                sidebarOpen: false,
                groups: {
                    rubber: @js($activeGroup['rubber']),
                    assembly: @js($activeGroup['assembly']),
                    ip: @js($activeGroup['ip']),
                    okc: @js($activeGroup['okc']),
                    stockfit: @js($activeGroup['stockfit']),
                }
            }"
            class="min-h-screen bg-neutral-100 dark:bg-neutral-900"
        >
            <livewire:layout.navigation-full />

            <button
                type="button"
                @click="sidebarOpen = true"
                class="fixed top-20 left-3 z-40 inline-flex items-center rounded-md border border-neutral-300 bg-white p-2 text-neutral-700 shadow-sm hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-caldy-400 sm:hidden dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                aria-controls="insights-sidebar"
                :aria-expanded="sidebarOpen"
            >
                <span class="sr-only">Open sidebar</span>
                <svg class="h-6 w-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M5 7h14M5 12h14M5 17h10" />
                </svg>
            </button>

            <div
                x-show="sidebarOpen"
                x-transition.opacity
                class="fixed inset-0 z-40 bg-black/40 sm:hidden"
                @click="sidebarOpen = false"
                x-cloak
                aria-hidden="true"
            ></div>

            <aside
                id="insights-sidebar"
                class="fixed left-0 top-16 z-50 h-[calc(100vh-4rem)] w-72 border-r border-neutral-200 bg-white transition-transform dark:border-neutral-700 dark:bg-neutral-800"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full sm:translate-x-0'"
                aria-label="Insights Sidebar"
            >
                <div class="h-full overflow-y-auto px-3 py-4">
                    <div class="mb-4 flex items-center justify-between px-2 sm:hidden">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Insights</h2>
                        <button
                            type="button"
                            class="rounded-md p-1 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-700"
                            @click="sidebarOpen = false"
                        >
                            <span class="sr-only">Close sidebar</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <ul class="space-y-2 text-sm">
                        <li>
                            <a
                                href="{{ route('insights') }}"
                                wire:navigate
                                @class([
                                    'group flex items-center rounded-md px-3 py-2 font-medium transition hover:bg-caldy-500/10',
                                    'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights') || request()->routeIs('insights.index'),
                                    'text-neutral-700 dark:text-neutral-200' => ! (request()->routeIs('insights') || request()->routeIs('insights.index')),
                                ])
                            >
                                <i class="icon-layout-grid mr-3 text-base"></i>
                                <span>{{ __('Wawasan') }}</span>
                            </a>
                        </li>

                        <li class="pt-2">
                            <button
                                type="button"
                                class="group flex w-full items-center justify-between rounded-md px-3 py-2 font-medium text-neutral-700 transition hover:bg-caldy-500/10 dark:text-neutral-200"
                                @click="groups.rubber = !groups.rubber"
                                :aria-expanded="groups.rubber"
                            >
                                <span class="inline-flex items-center">
                                    <i class="icon-settings mr-3 text-base"></i>
                                    {{ __('Sistem Rubber Terintegrasi') }}
                                </span>
                                <i class="icon-chevron-down text-xs transition-transform" :class="groups.rubber ? 'rotate-180' : ''"></i>
                            </button>
                            <ul x-show="groups.rubber" x-collapse class="mt-1 space-y-1 px-2" x-cloak>
                                <li>
                                    <a href="{{ route('insights.omv.index') }}" wire:navigate @class(['flex items-center rounded-md px-3 py-2 transition hover:bg-caldy-500/10', 'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights.omv*'), 'text-neutral-600 dark:text-neutral-300' => !request()->routeIs('insights.omv*')])>{{ __('Pemantauan open mill') }}</a>
                                </li>
                                <li>
                                    <a href="{{ route('insights.ctc.index') }}" wire:navigate @class(['flex items-center rounded-md px-3 py-2 transition hover:bg-caldy-500/10', 'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights.ctc*'), 'text-neutral-600 dark:text-neutral-300' => !request()->routeIs('insights.ctc*')])>{{ __('Kendali tebal calendar') }}</a>
                                </li>
                                <li>
                                    <a href="{{ route('insights.rdc.index') }}" wire:navigate @class(['flex items-center rounded-md px-3 py-2 transition hover:bg-caldy-500/10', 'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights.rdc*'), 'text-neutral-600 dark:text-neutral-300' => !request()->routeIs('insights.rdc*')])>{{ __('Sistem data rheometer') }}</a>
                                </li>
                            </ul>
                        </li>

                        <li>
                            <button
                                type="button"
                                class="group flex w-full items-center justify-between rounded-md px-3 py-2 font-medium text-neutral-700 transition hover:bg-caldy-500/10 dark:text-neutral-200"
                                @click="groups.assembly = !groups.assembly"
                                :aria-expanded="groups.assembly"
                            >
                                <span class="inline-flex items-center">
                                    <i class="icon-briefcase mr-3 text-base"></i>
                                    {{ __('Sistem Area Assembly') }}
                                </span>
                                <i class="icon-chevron-down text-xs transition-transform" :class="groups.assembly ? 'rotate-180' : ''"></i>
                            </button>
                            <ul x-show="groups.assembly" x-collapse class="mt-1 space-y-1 px-2" x-cloak>
                                <li>
                                    <a href="{{ route('insights.dwp.index') }}" wire:navigate @class(['flex items-center rounded-md px-3 py-2 transition hover:bg-caldy-500/10', 'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights.dwp*'), 'text-neutral-600 dark:text-neutral-300' => !request()->routeIs('insights.dwp*')])>{{ __('Pemantauan deep well press') }}</a>
                                </li>
                                <li>
                                    <a href="{{ route('insights.bpm.index') }}" wire:navigate @class(['flex items-center rounded-md px-3 py-2 transition hover:bg-caldy-500/10', 'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights.bpm*'), 'text-neutral-600 dark:text-neutral-300' => !request()->routeIs('insights.bpm*')])>{{ __('Pemantauan Emergency Bpm') }}</a>
                                </li>
                            </ul>
                        </li>

                        <li>
                            <button
                                type="button"
                                class="group flex w-full items-center justify-between rounded-md px-3 py-2 font-medium text-neutral-700 transition hover:bg-caldy-500/10 dark:text-neutral-200"
                                @click="groups.ip = !groups.ip"
                                :aria-expanded="groups.ip"
                            >
                                <span class="inline-flex items-center">
                                    <i class="icon-home mr-3 text-base"></i>
                                    {{ __('Sistem Area IP') }}
                                </span>
                                <i class="icon-chevron-down text-xs transition-transform" :class="groups.ip ? 'rotate-180' : ''"></i>
                            </button>
                            <ul x-show="groups.ip" x-collapse class="mt-1 space-y-1 px-2" x-cloak>
                                <li>
                                    <a href="{{ route('insights.clm.index') }}" wire:navigate @class(['flex items-center rounded-md px-3 py-2 transition hover:bg-caldy-500/10', 'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights.clm*'), 'text-neutral-600 dark:text-neutral-300' => !request()->routeIs('insights.clm*')])>{{ __('Gedung IP') }}</a>
                                </li>
                                <li>
                                    <a href="{{ route('insights.stc.index') }}" wire:navigate @class(['flex items-center rounded-md px-3 py-2 transition hover:bg-caldy-500/10', 'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights.stc*'), 'text-neutral-600 dark:text-neutral-300' => !request()->routeIs('insights.stc*')])>{{ __('Kendali chamber IP') }}</a>
                                </li>
                                <li>
                                    <a href="{{ route('insights.ibms.index') }}" wire:navigate @class(['flex items-center rounded-md px-3 py-2 transition hover:bg-caldy-500/10', 'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights.ibms*'), 'text-neutral-600 dark:text-neutral-300' => !request()->routeIs('insights.ibms*')])>{{ __('Pemantauan IP Blending') }}</a>
                                </li>
                            </ul>
                        </li>

                        <li>
                            <button
                                type="button"
                                class="group flex w-full items-center justify-between rounded-md px-3 py-2 font-medium text-neutral-700 transition hover:bg-caldy-500/10 dark:text-neutral-200"
                                @click="groups.okc = !groups.okc"
                                :aria-expanded="groups.okc"
                            >
                                <span class="inline-flex items-center">
                                    <i class="icon-inbox mr-3 text-base"></i>
                                    {{ __('Sistem Area OKC') }}
                                </span>
                                <i class="icon-chevron-down text-xs transition-transform" :class="groups.okc ? 'rotate-180' : ''"></i>
                            </button>
                            <ul x-show="groups.okc" x-collapse class="mt-1 space-y-1 px-2" x-cloak>
                                <li>
                                    <a href="{{ route('insights.ldc.index') }}" wire:navigate @class(['flex items-center rounded-md px-3 py-2 transition hover:bg-caldy-500/10', 'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights.ldc*'), 'text-neutral-600 dark:text-neutral-300' => !request()->routeIs('insights.ldc*')])>{{ __('Sistem data kulit') }}</a>
                                </li>
                            </ul>
                        </li>

                        <li>
                            <button
                                type="button"
                                class="group flex w-full items-center justify-between rounded-md px-3 py-2 font-medium text-neutral-700 transition hover:bg-caldy-500/10 dark:text-neutral-200"
                                @click="groups.stockfit = !groups.stockfit"
                                :aria-expanded="groups.stockfit"
                            >
                                <span class="inline-flex items-center">
                                    <i class="icon-droplet mr-3 text-base"></i>
                                    {{ __('Sistem Area Stockfit') }}
                                </span>
                                <i class="icon-chevron-down text-xs transition-transform" :class="groups.stockfit ? 'rotate-180' : ''"></i>
                            </button>
                            <ul x-show="groups.stockfit" x-collapse class="mt-1 space-y-1 px-2" x-cloak>
                                <li>
                                    <a href="{{ route('insights.pds.data.index') }}" wire:navigate @class(['flex items-center rounded-md px-3 py-2 transition hover:bg-caldy-500/10', 'bg-caldy-500/10 text-caldy-700 dark:text-caldy-300' => request()->routeIs('insights.pds*'), 'text-neutral-600 dark:text-neutral-300' => !request()->routeIs('insights.pds*')])>{{ __('Pemantauan PH Dossing') }}</a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </aside>

            <main class="pt-16 sm:ml-72">
                {{ $slot }}
            </main>
        </div>

        @stack('scripts')
    </body>
</html>
