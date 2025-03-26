<x-app-layout>

    <x-slot name="title">{{ __('Inventaris') }}</x-slot>
    
    @if (Auth::user()->id ?? false)
        <x-slot name="header">
            <x-nav-invlegacy></x-nav-invlegacy>
        </x-slot>
    @endif

    <div class="relative">
        @if (Auth::user()->id ?? false)
            <div class="max-w-xl lg:max-w-2xl mx-auto px-4 py-16">
                <h2 class="text-4xl font-bold dark:text-white">{{ __('Selamat datang di Inventaris') }}</h2>
                <p class="mt-4 mb-12 text-lg text-neutral-500">{{ __('Cari dan buat sirkulasi barang.') }}</p>
                <p class="mb-4 text-lg font-normal text-neutral-500 dark:text-neutral-400">
                    {{ __('Mulai dengan mengklik menu navigasi di pojok kanan atas.') }}</p>

                <ul class="space-y-4 text-left text-neutral-500 dark:text-neutral-400">
                    <li class="flex items-center space-x-3 rtl:space-x-reverse">
                        <i class="fa fa-search fa-fw me-2"></i>
                        <span><span
                                class="font-semibold text-neutral-900 dark:text-white">{{ __('Cari') }}</span>{{ ' ' . __('untuk menjelajah barang dan melakukan sirkulasi barang.') }}</span>
                    </li>
                    <li class="flex items-center space-x-3 rtl:space-x-reverse">
                        <i class="fa fa-arrow-right-arrow-left fa-fw me-2"></i>
                        <span><span
                                class="font-semibold text-neutral-900 dark:text-white">{{ __('Sirkulasi') }}</span>{{ ' ' . __('untuk melihat sirkulasi barang yang telah dibuat beserta statusnya.') }}</span>
                    </li>
                    <li class="flex items-center space-x-3 rtl:space-x-reverse">
                        <i class="fa fa-ellipsis-h fa-fw me-2"></i>
                        <span><span class="font-semibold text-neutral-900 dark:text-white">{{ __('Kelola') }}</span>
                            {{ ' ' . __('untuk mengelola barang beserta propertinya dan lainnya.') }}</span>
                    </li>
                </ul>
            </div>
        @else
            <!-- Section 2 -->
            <section class="px-2 pt-32 md:px-0">
                <div class="container items-center max-w-6xl px-8 mx-auto xl:px-5">
                    <div class="flex flex-wrap sm:-mx-3">
                        <div class="w-full md:w-1/2 md:px-3">
                            <div
                                class="w-full pb-6 space-y-6 sm:max-w-md lg:max-w-lg md:space-y-4 lg:space-y-8 xl:space-y-9 sm:pr-5 lg:pr-0 md:pb-0">
                                <h1
                                    class="text-4xl font-bold tracking-tight text-neutral-900 dark:text-neutral-300 sm:text-5xl md:text-4xl lg:text-5xl xl:text-6xl">
                                        <span class="block xl:inline">{{ __('Kelola inventaris dengan mudah') }}</span>
                                </h1>
                                <p class="mx-auto text-base text-neutral-500 sm:max-w-md lg:text-xl md:max-w-3xl">
                                    {{ __('Semua informasi barang seperti kuantitas, dan pencatatan keluar masuk, ada dalam satu tempat.') }}
                                </p>
                                <div class="relative flex flex-col sm:flex-row sm:space-x-4">
                                    <a href="{{ route('login', ['redirect' => url()->current()]) }}" wire:navigate
                                        class="flex items-center w-full px-6 py-3 mb-3 text-lg text-white bg-caldy-600 rounded-md sm:mb-0 hover:bg-caldy-700 sm:w-auto">
                                        {{ __('Masuk') }}
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-1" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <line x1="5" y1="12" x2="19" y2="12">
                                            </line>
                                            <polyline points="12 5 19 12 12 19"></polyline>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                            <div class="pr-0 md:pr-8"><hr class="border-neutral-300 dark:border-neutral-800 my-10" /></div>
                            <div class="text-neutral-500 text-sm mb-10">
                                <div class="mb-4">
                                    <div class="mb-1">{{ __('Telah diterapkan di:') }}</div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <x-pill color="neutral-lighter">MM</x-pill>
                                    <x-pill color="neutral-lighter">Maintenance</x-pill>
                                    <x-pill color="neutral-lighter">CE</x-pill>
                                    {{-- <x-link href="#">Terapkan di departemenmu</x-link> --}}
                                </div>
                             </div>
                        </div>
                        <div class="w-full md:w-1/2">
                            <div class="w-full h-auto overflow-hidden rounded-md shadow-xl sm:rounded-xl">
                                <img src="/inventory.jpg" class="dark:invert">
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <style>
            /* entire container, keeps perspective */
            .card-container {
                -webkit-perspective: 1000px;
                perspective: 1000px;
                -webkit-transform-style: preserve-3d;
                transform-style: preserve-3d;
            }

            /* flip class added with javascript on click */
            .card-container .front {
                /* For IE10 you have to animate each face separately. */
                /* Isnt great on low end devices because */
                /* they can animate at different times */
                /* IE9 does not support CSS animations */
                -webkit-transform: rotateX(0deg);
                transform: rotateX(0deg);
            }

            .card-container .back {
                -webkit-transform: rotateX(180deg);
                transform: rotateX(180deg);
            }

            .card .front,
            .card .back {
                position: absolute;
                top: 0;
                left: 0;
                -webkit-backface-visibility: hidden;
                backface-visibility: hidden;
                -webkit-transition: transform 1s ease;
                transition: transform 1s ease;
                -webkit-transform-style: preserve-3d;
                transform-style: preserve-3d;
            }

            @keyframes expand {
                from {
                    letter-spacing: 0;
                }

                to {
                    letter-spacing: 6pt;
                }
            }

            .front>div {
                animation-name: expand;
                animation-duration: 5s;
                animation-iteration-count: 1;
                animation-fill-mode: forwards;
            }

            @keyframes flipfront {
                from {
                    -webkit-transform: rotateX(0deg);
                    transform: rotateX(0deg);
                }

                to {
                    -webkit-transform: rotateX(180deg);
                    transform: rotateX(180deg);
                }
            }

            @keyframes flipback {
                from {
                    -webkit-transform: rotateX(-180deg);
                    transform: rotateX(-180deg);
                }

                to {
                    -webkit-transform: rotateX(0deg);
                    transform: rotateX(0deg);
                }
            }

            .card .front {
                /* z-index so front card stays above back */
                z-index: 2;
                animation-name: flipfront;
                animation-duration: 1s;
                animation-iteration-count: 1;
                animation-fill-mode: forwards;
                animation-delay: 1s;

            }

            .card .back {
                /* back, initially hidden */
                animation-name: flipback;
                animation-duration: 1s;
                animation-iteration-count: 1;
                animation-fill-mode: forwards;
                animation-delay: 1s;

            }
        </style>
    </div>
</x-app-layout>
