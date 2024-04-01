<?php

use Carbon\Carbon;
use App\Models\Session;
use App\Models\User;
use function Livewire\Volt\{layout, state, mount};

layout('layouts.app');
state(['greeting', 'time', 'users']);

mount(function () {
    $greetings = [__('Udah makan belum?'), __('Gimana kabarnya?'), __('Apa kabar?'), __('Selamat datang!'), __('Eh ketemu lagi!'), __('Ada yang bisa dibantu?'), __('Hai,') . ' ' . (Auth::user()->name ?? __('Tamu')) . '!', __('Gimana gimana?')];

    $qago = Carbon::now()->subMinutes(30)->getTimestamp();
    $sessions = Session::where('last_activity', '>', $qago)->get();
    $user_ids = $sessions->pluck('user_id');

    $this->greeting = $greetings[array_rand($greetings)];
    $this->time = Carbon::now()->format('Y-m-d H:i');
    $this->users = User::whereIn('id', $user_ids)->get();
});

?>

<div>
    @if (Auth::user()->id ?? false)
        <div class="max-w-4xl mx-auto py-8 px-4 lg:px-8">
            <div class="container relative max-w-2xl mx-auto text-center tracking-widest text-neutral-500 ">
                <div class="card-container w-full my-auto">
                    <div class="card relative h-40">
                        <div class="front w-full h-full">
                            <div class="p-5 text-xl sm:text-3xl">
                                {{ $greeting }}
                            </div>
                        </div>
                        <div class="back w-full h-full">
                            <div class="p-5 text-3xl sm:text-5xl">
                                Caldera
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-neutral-500">
                <div class="text-center mb-20">
                    <div class="mb-2">{{ __('Waktu server:') . ' ' . $time }}</div>
                    <div>{{ $users->count() . ' ' . __('pengguna daring') }}</div>
                </div>
                <div class="flex flex-wrap justify-center gap-3">
                    @foreach ($users as $user)
                        <div class="inline-block bg-white dark:bg-neutral-800 rounded-full p-2">
                            <div class="flex w-28 h-full truncate items-center gap-2">
                                <div>
                                    <div
                                        class="w-6 h-6 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                        @if ($user->photo)
                                            <img class="w-full h-full object-cover dark:brightness-75"
                                                src="{{ '/storage/users/' . $user->photo }}" />
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                                viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                                <path
                                                    d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                                            </svg>
                                        @endif
                                    </div>
                                </div>
                                <div class="truncate">{{ $user->name }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <!-- Section 2 -->
        <section class="px-2 py-32 md:px-0">
            <div class="container items-center max-w-6xl px-8 mx-auto xl:px-5">
                <div class="flex flex-wrap items-center sm:-mx-3">
                    <div class="w-full md:w-1/2 md:px-3">
                        <div
                            class="w-full pb-6 space-y-6 sm:max-w-md lg:max-w-lg md:space-y-4 lg:space-y-8 xl:space-y-9 sm:pr-5 lg:pr-0 md:pb-0">
                            <h1
                                class="text-4xl font-extrabold tracking-tight text-neutral-900 dark:text-neutral-300 sm:text-5xl md:text-4xl lg:text-5xl xl:text-6xl">
                                <span class="block xl:inline">{{ __('Raih cakrawala dalam waktu nyata') }}</span>
                                <span class="block text-caldy-600 xl:inline">{{ __('dengan Caldera') }}</span>
                            </h1>
                            <p class="mx-auto text-base text-neutral-500 sm:max-w-md lg:text-xl md:max-w-3xl">
                                {{ __('Manfaatkan kekuatan data real-time untuk membantu menyelesaikan tugasmu atau membuat keputusan dari ikhtisar.') }}
                            </p>
                            <div class="relative flex flex-col sm:flex-row sm:space-x-4">
                                <a href="{{ route('login') }}" wire:navigate
                                    class="flex items-center w-full px-6 py-3 mb-3 text-lg text-white bg-caldy-600 rounded-md sm:mb-0 hover:bg-caldy-700 sm:w-auto">
                                    {{ __('Masuk') }}
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-1" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                                {{-- <a href="#_"
                                class="flex items-center px-6 py-3 text-gray-500 bg-gray-100 rounded-md hover:bg-gray-200 hover:text-gray-600">
                                {{ __('Pelajari lebih lanjut')}}
                            </a> --}}
                            </div>
                        </div>
                    </div>
                    <div class="w-full md:w-1/2">
                        <div class="w-full h-auto overflow-hidden rounded-md shadow-xl sm:rounded-xl">
                            <img src="/home.jpg" class="dark:invert">
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
