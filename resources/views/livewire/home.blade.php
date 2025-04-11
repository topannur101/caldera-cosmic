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
    
    @if (Auth::user()->id ?? false)
        <div class="py-32 px-4 lg:px-8 relative overflow-hidden">
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
            <x-aurora />
        </div>
    @else
        <!-- Section 2 -->
        <section class="px-2 py-32 md:px-0 relative overflow-hidden">
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
                                {{ __('Manfaatkan kekuatan data real-time untuk membantu menyelesaikan tugas dan membuat keputusan.') }}
                            </p>
                            <!-- <div class="relative flex flex-col sm:flex-row sm:space-x-4">
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
                            </div> -->
                        </div>
                        <div class="pr-0 md:pr-8"><hr class="border-neutral-300 dark:border-neutral-800 my-10" /></div>
                        <x-home-users :$time :$users :$guests></x-home-users>
                    </div>
                    <div class="w-full md:w-1/2">
                        <div class="w-full h-auto aspect-video sm:aspect-auto overflow-hidden rounded-md shadow-xl sm:rounded-xl">
                            <img src="/home.jpg" class="dark:invert">
                        </div>
                    </div>
                </div>
            </div>
            <x-aurora />
        </section>
    @endif
    
    <footer class="max-w-6xl mx-auto">
        <div class="w-full max-w-screen-xl mx-auto p-4 md:py-8">
            <div class="sm:flex sm:items-center sm:justify-between">
                <a href="/" class="flex items-center mb-4 sm:mb-0 space-x-3 rtl:space-x-reverse">
                    <img src="/favicon-32x32.png" class="h-8" alt="Flowbite Logo" />
                    <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">Caldera</span>
                </a>
                <ul class="flex flex-wrap items-center mb-6 text-sm font-medium text-neutral-500 sm:mb-0 dark:text-neutral-400">
                    <li>
                        <a href="{{ route('contact') }}" class="hover:underline me-4 md:me-6">{{ __('Kontak') }}</a>
                    </li>
                    <!-- <li>
                        <a href="#" class="hover:underline me-4 md:me-6">{{ __('Pengumuman') }}</a>
                    </li> -->
                </ul>
            </div>
            <hr class="my-6 border-neutral-200 sm:mx-auto dark:border-neutral-700 lg:my-8" />
            <span class="block text-sm text-neutral-500 sm:text-center dark:text-neutral-400">{{ __('Oleh dept. MM untuk PT. TKG Taekwang Indonesia') }}</span>
        </div>
    </footer>

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
