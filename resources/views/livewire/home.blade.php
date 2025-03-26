<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Session;
use Carbon\Carbon;
use App\Models\User;

new #[Layout('layouts.app')] 
class extends Component {

    public function with(): array {
        $greetings = [__('Udah makan belum?'), __('Gimana kabarnya?'), __('Apa kabar?'), __('Selamat datang!'), __('Eh ketemu lagi!'), __('Ada yang bisa dibantu?'), __('Hai,') . ' ' . (Auth::user()->name ?? __('Tamu')) . '!', __('Gimana gimana?')];
        $qago = Carbon::now()->subMinutes(30)->getTimestamp();
        $sessions = Session::where('last_activity', '>', $qago)->get();
        $user_ids = $sessions->pluck('user_id');

        return [
            'greeting' => $greetings[array_rand($greetings)],
            'time' => Carbon::now()->locale(app()->getLocale())->isoFormat('dddd, D MMMM YYYY, HH:mm:ss'),
            'users' => User::whereIn('id', $user_ids)->get(),
            'guests' => Session::whereNull('user_id')->get()
        ];
    }

}

?>

<div wire:poll.9s class="relative">
        <svg wire:ignore class="absolute top-0 left-0 min-h-screen" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid slice">
            <defs>
                <radialGradient id="Gradient1" cx="50%" cy="50%" fx="0.441602%" fy="50%" r=".5">
                    <animate attributeName="fx" dur="68s" values="0%;3%;0%" repeatCount="indefinite"></animate>
                    <stop offset="0%" stop-color="rgba(255, 0, 255, 0.1)"></stop>
                    <stop offset="100%" stop-color="rgba(255, 0, 255, 0)"></stop>
                </radialGradient>
                <radialGradient id="Gradient2" cx="50%" cy="50%" fx="2.68147%" fy="50%" r=".5">
                    <animate attributeName="fx" dur="47s" values="0%;3%;0%" repeatCount="indefinite"></animate>
                    <stop offset="0%" stop-color="rgba(255, 255, 0, 0.1)"></stop>
                    <stop offset="100%" stop-color="rgba(255, 255, 0, 0)"></stop>
                </radialGradient>
                <radialGradient id="Gradient3" cx="50%" cy="50%" fx="0.836536%" fy="50%" r=".5">
                    <animate attributeName="fx" dur="43s" values="0%;3%;0%" repeatCount="indefinite"></animate>
                    <stop offset="0%" stop-color="rgba(0, 255, 255, 0.1)"></stop>
                    <stop offset="100%" stop-color="rgba(0, 255, 255, 0)"></stop>
                </radialGradient>
                <radialGradient id="Gradient4" cx="50%" cy="50%" fx="4.56417%" fy="50%" r=".5">
                    <animate attributeName="fx" dur="46s" values="0%;5%;0%" repeatCount="indefinite"></animate>
                    <stop offset="0%" stop-color="rgba(0, 255, 0, 0.1)"></stop>
                    <stop offset="100%" stop-color="rgba(0, 255, 0, 0)"></stop>
                </radialGradient>
                <radialGradient id="Gradient5" cx="50%" cy="50%" fx="2.65405%" fy="50%" r=".5">
                    <animate attributeName="fx" dur="49s" values="0%;5%;0%" repeatCount="indefinite"></animate>
                    <stop offset="0%" stop-color="rgba(0, 0, 255, 0.1)"></stop>
                    <stop offset="100%" stop-color="rgba(0, 0, 255, 0)"></stop>
                </radialGradient>
                <radialGradient id="Gradient6" cx="50%" cy="50%" fx="0.981338%" fy="50%" r=".5">
                    <animate attributeName="fx" dur="51s" values="0%;5%;0%" repeatCount="indefinite"></animate>
                    <stop offset="0%" stop-color="rgba(255, 0, 0, 0.1)"></stop>
                    <stop offset="100%" stop-color="rgba(255, 0, 0, 0)"></stop>
                </radialGradient>
            </defs>
            <rect x="13.744%" y="1.18473%" width="100%" height="100%" fill="url(#Gradient1)" transform="rotate(334.41 50 50)">
                <animate attributeName="x" dur="40s" values="25%;0%;25%" repeatCount="indefinite"></animate>
                <animate attributeName="y" dur="42s" values="0%;25%;0%" repeatCount="indefinite"></animate>
                <animateTransform attributeName="transform" type="rotate" from="0 50 50" to="360 50 50" dur="14s" repeatCount="indefinite"></animateTransform>
            </rect>
            <rect x="-2.17916%" y="35.4267%" width="100%" height="100%" fill="url(#Gradient2)" transform="rotate(255.072 50 50)">
                <animate attributeName="x" dur="46s" values="-25%;0%;-25%" repeatCount="indefinite"></animate>
                <animate attributeName="y" dur="48s" values="0%;50%;0%" repeatCount="indefinite"></animate>
                <animateTransform attributeName="transform" type="rotate" from="0 50 50" to="360 50 50" dur="24s" repeatCount="indefinite"></animateTransform>
            </rect>
            <rect x="9.00483%" y="14.5733%" width="100%" height="100%" fill="url(#Gradient3)" transform="rotate(139.903 50 50)">
                <animate attributeName="x" dur="50s" values="0%;25%;0%" repeatCount="indefinite"></animate>
                <animate attributeName="y" dur="24s" values="0%;25%;0%" repeatCount="indefinite"></animate>
                <animateTransform attributeName="transform" type="rotate" from="360 50 50" to="0 50 50" dur="18s" repeatCount="indefinite"></animateTransform>
            </rect>
        </svg>
    
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
            <x-home-users :$time :$users :$guests centered="true" ></x-home-users>
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
                                @if (app()->getLocale() === 'ko')
                                    <span class="block text-caldy-600 xl:inline">{{ __('home.hero2') }}</span><span
                                        class="block xl:inline">{{ __('home.hero1') }}</span>
                                @else
                                    <span class="block xl:inline">{{ __('home.hero1') }}</span><span
                                        class="block text-caldy-600 xl:inline">{{ __('home.hero2') }}</span>
                                @endif

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
                            </div>
                        </div>
                        <div class="pr-0 md:pr-8"><hr class="border-neutral-300 dark:border-neutral-800 my-10" /></div>
                        <x-home-users :$time :$users :$guests></x-home-users>
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
