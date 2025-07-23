<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
            <h2 class="flex items-center gap-x-4 font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                @if(request()->is('tasks/projects/*'))
                    <x-link href="{{ route('tasks.projects.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @elseif(request()->is('tasks/items/*'))
                    <x-link href="{{ route('tasks.items.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @elseif(request()->is('tasks/teams/*'))
                    <x-link href="{{ route('tasks.teams.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @else          
                    <x-link href="{{ route('tasks.dashboard.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>    
                @endif
            </h2>
       </div>
       <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('tasks.dashboard.index') }}" :active="request()->is('tasks/dashboard*')" wire:navigate>
                <i class="icon-gauge text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Dasbor') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('tasks.projects.index') }}" :active="request()->is('tasks/projects*')" wire:navigate>
                <i class="icon-drafting-compass text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Proyek') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('tasks.items.index') }}" :active="request()->is('tasks/items*')" wire:navigate>
                <i class="icon-ticket text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Tugas') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('tasks.manage.index') }}" :active="request()->is('tasks/manage*')" wire:navigate>
                <i class="icon-ellipsis text-sm"></i>
            </x-nav-link>
        </div>
   </div>
</header>