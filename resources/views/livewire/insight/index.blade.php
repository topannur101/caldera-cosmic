<x-app-layout>
    <x-slot name="title">{{ __('Wawasan') }}</x-slot>
    <x-slot name="header">
        <header class="bg-white dark:bg-neutral-800 shadow">
            <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div>
                    <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                        <div class="inline-block py-6">{{ __('Wawasan') }}</div>
                    </h2>
                </div>
            </div>
        </header>
    </x-slot>
    <div id="content" class="py-12 max-w-4xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">

        <div class="grid gap-6">
            <div>
                <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                    {{ __('Area Midsole') }}</h1>
                <div class="grid gap-2 grid-cols-1 lg:grid-cols-2">
                    <x-card-link href="{{ route('insight.stc.index') }}" wire:navigate>
                        <div class="flex">
                            <div>
                                <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                                    <div class="m-auto">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                            viewBox="0 0 24.00 24.00" fill="none">
                                            <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                            <g id="SVGRepo_iconCarrier">
                                                <path
                                                    d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                                    stroke="#000000" stroke-width="0.84" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </g>
                                        </svg>
                                    </div>
                                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                                        src="/ins-stc-i.jpg" />
                                </div>
                            </div>
                            <div class="flex grow py-4 px-2 sm:p-6">
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ __('IP Stabilization Control')  }}
                                    </div>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __('Sistem kendali temperatur dan kecepatan pada mesin chamber IP.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-card-link>
                    <x-card-link href="#" x-data x-on:click.prevent="alert('Sedang dalam tahap pengembangan')">
                        <div class="flex">
                            <div>
                                <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                                    <div class="m-auto">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                            viewBox="0 0 24.00 24.00" fill="none">
                                            <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                            <g id="SVGRepo_iconCarrier">
                                                <path
                                                    d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                                    stroke="#000000" stroke-width="0.84" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </g>
                                        </svg>
                                    </div>
                                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                                        src="/ins-erd-i.jpg" />
                                </div>
                            </div>
                            <div class="flex grow py-4 px-2 sm:p-6">
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ __('Pendataan IP ER')  }}
                                    </div>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __('Sistem pendataan hasil evaluasi dari mesin IP ER.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-card-link>
                </div>
            </div>
            <div>
                <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                    {{ __('Area Outsole') }}</h1>
                <div class="grid gap-2 grid-cols-1 lg:grid-cols-2">
                    {{-- <div class="bg-neutral-800 rounded-lg p-4 relative overflow-hidden" x-data="{ expanded: false }">
                        <!-- Large background icon on the right side -->
                        <div class="absolute -right-6 -top-3 text-neutral-600 opacity-20">
                            <img class="w-40 h-40" src="/ink-omv.svg" />
                        </div>
                        <div class="relative z-10">
                          <div class="flex items-center mb-2">
                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                            <h3 class="text-xl font-semibold text-white ml-2">Open Mill Validator</h3>
                          </div>
                          <p class="text-neutral-400 text-sm mb-4">Sistem validasi proses open mill untuk mengevaluasi kepatuhan resep.</p>
                          <div class="flex justify-between items-center">
                            <button 
                              @click="expanded = !expanded"
                              class="flex items-center text-neutral-400 text-sm hover:text-white transition-colors"
                            >
                              <i x-show="!expanded" class="fas fa-chevron-down mr-1"></i>
                              <i x-show="expanded" class="fas fa-chevron-up mr-1"></i>
                              <span x-text="expanded ? 'Sembunyikan Status' : 'Tampilkan Status'"></span>
                            </button>
                            <button class="bg-caldy-500 hover:bg-caldy-600 text-white py-1 px-3 rounded flex items-center text-sm transition-colors">
                              {{ __('Kunjungi') }}
                              <i class="fas fa-arrow-right ml-1"></i>
                            </button>
                          </div>
                          <div x-show="expanded" class="mt-3 text-sm text-neutral-300 border-t border-neutral-700 pt-3">
                            Status: Online
                            <br />
                            Pembaruan terakhir: 5 menit yang lalu
                          </div>
                        </div>
                      </div> --}}
                    <x-card-link href="{{ route('insight.omv.index') }}" wire:navigate>
                        <div class="flex">
                            <div>
                                <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                                    <div class="m-auto">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                            viewBox="0 0 24.00 24.00" fill="none">
                                            <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                            <g id="SVGRepo_iconCarrier">
                                                <path
                                                    d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                                    stroke="#000000" stroke-width="0.84" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </g>
                                        </svg>
                                    </div>
                                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                                        src="/ins-omv-i.jpg" />
                                </div>
                            </div>
                            <div class="flex grow py-4 px-2 sm:p-6">
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ __('Open Mill Validator') }}
                                    </div>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __('Sistem validasi proses pada mesin open roll mixing.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-card-link>
                    <x-card-link href="{{ route('insight.rtc.index') }}" wire:navigate>
                        <div class="flex">
                            <div>
                                <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                                    <div class="m-auto">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                            viewBox="0 0 24.00 24.00" fill="none">
                                            <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                            <g id="SVGRepo_iconCarrier">
                                                <path
                                                    d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                                    stroke="#000000" stroke-width="0.84" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </g>
                                        </svg>
                                    </div>
                                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                                        src="/ins-rtc-i.jpg" />
                                </div>
                            </div>
                            <div class="flex grow py-4 px-2 sm:p-6">
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ __('Rubber Thickness Control') }}
                                    </div>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __('Sistem kendali ketebalan rubber pada proses pembuatan calendar.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-card-link>
                    <x-card-link href="{{ route('insight.rdc.index') }}" wire:navigate>
                        <div class="flex">
                            <div>
                                <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                                    <div class="m-auto">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                            viewBox="0 0 24.00 24.00" fill="none">
                                            <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                            <g id="SVGRepo_iconCarrier">
                                                <path
                                                    d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                                    stroke="#000000" stroke-width="0.84" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </g>
                                        </svg>
                                    </div>
                                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                                        src="/ins-rdc-i.jpg" />
                                </div>
                            </div>
                            <div class="flex grow py-4 px-2 sm:p-6">
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ __('Pendataan Rheometer')  }}
                                    </div>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __('Sistem pendataan hasil uji rheometer untuk mengukur sifat polimer karet.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-card-link>
                    <x-card-link href="#" x-data x-on:click.prevent="alert('Sedang dalam tahap pengembangan')">
                        <div class="flex">
                            <div>
                                <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                                    <div class="m-auto">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                            viewBox="0 0 24.00 24.00" fill="none">
                                            <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                            <g id="SVGRepo_iconCarrier">
                                                <path
                                                    d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                                    stroke="#000000" stroke-width="0.84" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </g>
                                        </svg>
                                    </div>
                                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                                        src="/ins-rad-i.jpg" />
                                </div>
                            </div>
                            <div class="flex grow py-4 px-2 sm:p-6">
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ __('Pendataan Aging')  }}
                                    </div>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __('Sistem pendataan aging pada proses pembuatan calendar.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-card-link>
                </div>
            </div>
            <div>
                <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                    {{ __('Area OKC') }}</h1>
                <div class="grid gap-2 grid-cols-1 lg:grid-cols-2">
                    <x-card-link href="{{ route('insight.ldc.index') }}" wire:navigate>
                        <div class="flex">
                            <div>
                                <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                                    <div class="m-auto">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                            viewBox="0 0 24.00 24.00" fill="none">
                                            <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                            <g id="SVGRepo_iconCarrier">
                                                <path
                                                    d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                                    stroke="#000000" stroke-width="0.84" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </g>
                                        </svg>
                                    </div>
                                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                                        src="/ins-ldc-i.jpg" />
                                </div>
                            </div>
                            <div class="flex grow py-4 px-2 sm:p-6">
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ __('Pendataan kulit') }}
                                    </div>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __('Sistem pencatatan hasil pemeriksaan kulit pada mesin NT/NEK.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-card-link>
                    <x-card-link href="https://taekwang-id.comelz.cloud/">
                        <div class="flex">
                            <div>
                                <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                                    <div class="m-auto">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                            viewBox="0 0 24.00 24.00" fill="none">
                                            <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                            <g id="SVGRepo_iconCarrier">
                                                <path
                                                    d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                                    stroke="#000000" stroke-width="0.84" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </g>
                                        </svg>
                                    </div>
                                    <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                                        src="/aurelia.jpg" />
                                </div>
                            </div>
                            <div class="flex grow py-4 px-2 sm:p-6">
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ __('Aurelia') }}<span class="text-xs ms-2"><i class="fa fa-arrow-up-right-from-square"></i></span>
                                    </div>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __('Sistem informasi metrik kinerja dan konsumsi mesin CZ dan NEK.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-card-link>
                </div>
            </div>
        </div>       
    </div>
</x-app-layout>
