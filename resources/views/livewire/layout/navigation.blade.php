<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;
use App\Models\User;

new class extends Component {
    
    public array $user = [
        'id'        => 0,
        'name'      => '',
        'emp_id'    => '',
        'photo'     => '',
    ];

    public int $ackCount = 0;

    public int $unreadCount = 0;

    public function mount()
    {
        $this->ackCount = session('ackCount', 0);

        if (auth()->user()) {
            $this->user = [
                'id'        => auth()->id(),
                'name'      => auth()->user()->name,
                'emp_id'    => auth()->user()->emp_id,
                'photo'     => auth()->user()->photo,
            ];
        }
    }

    public function with(): array
    {
        $notifications = [];

        $user = auth()->user();
        if ($user) {
            $this->unreadCount  = min($user->unreadNotifications->count(), 99);
            $notifications      = $user->notifications()->orderBy('created_at', 'desc')->take(20)->get();
        }    

        return [
            'notifications' => $notifications
        ];
    }

    public function logout(Logout $logout): void
    {
        $logout();
        
        $this->redirect('/', navigate: false);
    }

    public function ackNotif()
    {
        session()->put('ackCount', $this->unreadCount);
        $this->ackCount = $this->unreadCount;        
    }

}; ?>

<nav x-data="{ open: false }" class="bg-white dark:bg-neutral-800 border-b border-neutral-100 dark:border-neutral-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8">
        <div class="flex h-16">
            <div class="flex grow">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('home') }}" wire:navigate>
                        <x-application-logo
                            class="block h-6 w-auto fill-current text-neutral-800 dark:text-neutral-200" />
                    </a>
                    <span class="inline ml-6 uppercase text-xs text-neutral-800 dark:text-neutral-200 tracking-widest md:hidden">Caldera</span>
                </div>

                <!-- Navigation Links -->
                <div id="cal-nav-main-links-alt" class="hidden text-xs uppercase">
                    <span class="inline-flex items-center ms-10 h-full border-b-2 border-transparent font-medium leading-5 text-neutral-500 dark:text-neutral-400 dark:hover:border-neutral-700">
                        {{ __('Pemantauan open mill') }}
                    </span>
                </div>
                <div id="cal-nav-main-links" class="hidden space-x-8 md:-my-px md:ms-10 md:flex text-xs uppercase">
                    <x-nav-link :href="route('insights')" :active="request()->is('insights*')" wire:navigate>
                        {{ __('Wawasan') }}
                    </x-nav-link>
                    <x-nav-link :href="route('inventory')" :active="request()->routeIs('inventory*')" wire:navigate>
                        {{ __('Inventaris') }}
                    </x-nav-link>
                    <x-nav-link :href="route('machines')" :active="request()->routeIs('machines*')" wire:navigate>
                        {{ __('Mesin ') }}
                    </x-nav-link>
                    <x-nav-link :href="route('projects')" :active="request()->routeIs('projects*')" wire:navigate>
                        {{ __('Proyek ') }}
                    </x-nav-link>
                    @auth
                    <x-nav-link :href="route('caldy')" :active="request()->routeIs('caldy*')" wire:navigate>
                        <i class="fa fa-splotch text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500"></i>
                    </x-nav-link>
                    @endauth
                </div>
            </div>

            @if($user['id'] && !request()->is('notifications'))
            <div class="my-auto text-neutral-700 dark:text-neutral-300">
                <x-dropdown align="right" width="72">
                    <x-slot name="trigger">
                        <button wire:click="ackNotif" x-on:click="document.getElementById('cal-notif-counter')?.remove()"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-neutral-500 dark:text-neutral-400 bg-white dark:bg-neutral-800 hover:text-neutral-700 dark:hover:text-neutral-300 focus:outline-none transition ease-in-out duration-150">
                            <i class="fa fa-bell"></i>
                            @if($unreadCount && ($ackCount !== $unreadCount))
                                <x-pill id="cal-notif-counter" color="red" class="ml-2">{{ $unreadCount }}</x-pill>
                            @endif
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="max-h-96 overflow-y-auto">
                            <div class="px-4 py-2 text-lg">{{ __('Notifikasi') }}</div>
                            @if(count($notifications) == 0)
                                <div @click="open = false" class="p-4 text-center text-sm leading-5">
                                    {{ __('Tidak ada notifikasi') }}
                                </div>
                            @endif
                            @foreach($notifications as $notification)
                                <x-notification :$notification />                          
                            @endforeach
  
                        </div>
                        <hr class="border-neutral-300 dark:border-neutral-600" />
                        <!-- Authentication -->
                        <x-dropdown-link class="text-center" :href="route('notifications')" wire:navigate>
                            {{ __('Lihat semua') }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
            @endif
            
            <!-- Settings Dropdown -->
            <div class="hidden md:flex md:items-center md:ms-6">
                @if ($user['id'])
                    <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                        @if ($user['photo'])
                            <img class="w-full h-full object-cover dark:brightness-75"
                                src="/storage/users/{{ $user['photo'] }}" />
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                <path
                                    d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                            </svg>
                        @endif
                    </div>
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-neutral-500 dark:text-neutral-400 bg-white dark:bg-neutral-800 hover:text-neutral-700 dark:hover:text-neutral-300 focus:outline-none transition ease-in-out duration-150">
                                <div>{{ $user['name'] }}</div>

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('account')" wire:navigate>
                                {{ __('Akun') }}
                            </x-dropdown-link>
                            
                            @if($user['id'] == 1)
                                <x-dropdown-link :href="route('admin')" :active="request()->routeIs('admin*')" wire:navigate>
                                    {{ __('Administrasi') }}
                                </x-dropdown-link>
                            @endif
                            <hr class="border-neutral-300 dark:border-neutral-600" />

                            <!-- Authentication -->
                            <button wire:click="logout" class="w-full text-start">
                                <x-dropdown-link>
                                    <i class="fa fa-power-off me-2"></i>{{ __('Keluar') }}
                                </x-dropdown-link>
                            </button>
                        </x-slot>
                    </x-dropdown>
                @else
                <div class="flex items-center gap-x-4 text-xs uppercase font-medium leading-5 text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300">
                    <livewire:layout.navigation-lang-set :route="url()->current()" small="true" />
                    <div>                        
                        <x-link :href="route('login')" wire:navigate>{{ __('Masuk') }}
                        </x-link>
                    </div>
                </div>

                @endif

            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center md:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-neutral-400 dark:text-neutral-500 hover:text-neutral-500 dark:hover:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-900 focus:outline-none focus:bg-neutral-100 dark:focus:bg-neutral-900 focus:text-neutral-500 dark:focus:text-neutral-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{ 'block': open, 'hidden': !open }" class="hidden md:hidden">
        <div class="py-6 space-y-1">
            <x-responsive-nav-link :href="route('insights')" :active="request()->routeIs('insights*')" wire:navigate>
                {{ __('Wawasan') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('inventory')" :active="request()->routeIs('inventory*')" wire:navigate>
                {{ __('Inventaris') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('machines')" :active="request()->routeIs('machines*')" wire:navigate>
                {{ __('Mesin') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('projects')" :active="request()->routeIs('projects*')" wire:navigate>
                {{ __('Proyek') }}
            </x-responsive-nav-link>
            @auth
            <x-responsive-nav-link :href="route('caldy')" :active="request()->routeIs('caldy*')" wire:navigate>
                <i class="fa fa-fw me-2 fa-splotch text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500"></i>{{ __('Caldy AI') }}
            </x-responsive-nav-link>
            @endauth
        </div>

        <!-- Responsive Settings Options -->
        <div class="py-6 border-t border-neutral-200 dark:border-neutral-600">
            @if ($user['id'])
                <div class="flex gap-x-2 items-center mx-3">
                    <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                        @if ($user['photo'])
                            <img class="w-full h-full object-cover dark:brightness-75"
                                src="/storage/users/{{ $user['photo'] }}" />
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                <path
                                    d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                            </svg>
                        @endif
                    </div>
                    <div class="px-4">
                        <div class="font-medium text-base text-neutral-800 dark:text-neutral-200">{{ $user['name'] }}</div>
                        <div class="font-medium text-sm text-neutral-500">{{ $user['emp_id'] }}</div>
                    </div>
                </div>
                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('account')" :active="request()->routeIs('account*')" wire:navigate>
                        <i class="fa fa-fw fa-user-pen me-2"></i>{{ __('Akun') }}
                    </x-responsive-nav-link>
                    
                    @if($user['id'] == 1)
                        <x-responsive-nav-link :href="route('admin')" :active="request()->routeIs('admin*')" wire:navigate>
                            <i class="fa fa-fw fa-cog me-2"></i>{{ __('Administrasi') }}
                        </x-responsive-nav-link>
                    @endif

                    <!-- Authentication -->
                    <button wire:click="logout" class="w-full text-start">
                        <x-responsive-nav-link>
                            <i class="fa fa-fw fa-power-off me-2"></i>{{ __('Keluar') }}
                        </x-responsive-nav-link>
                    </button>
                </div>
            @else
                <div class="mb-2 px-4"><livewire:layout.navigation-lang-set :route="url()->current()" /></div>
                <x-responsive-nav-link :href="route('login')" wire:navigate>
                    <i class="fa fa-right-to-bracket mr-2"></i>{{ __('Masuk') }}
                </x-responsive-nav-link>
            @endif
        </div>
    </div>
</nav>
