<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
            <h2 class="flex items-center gap-x-4 font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                @if(request()->is('projects/items/*'))
                    <x-link href="{{ route('projects.items.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @elseif(request()->is('projects/tasks/*'))
                    <x-link href="{{ route('projects.tasks.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @elseif(request()->is('projects/dashboard/*'))
                    <x-link href="{{ route('projects.dashboard.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @else          
                    <x-link href="{{ route('projects.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>    
                @endif
            </h2>
       </div>
       <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('projects.items.index') }}" :active="request()->is('projects/items*')" wire:navigate>
                <i class="icon-folder text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Indeks') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('projects.tasks.index') }}" :active="request()->is('projects/tasks*')" wire:navigate>
                <i class="icon-list-checks text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Tugas') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('projects.dashboard.index') }}" :active="request()->is('projects/dashboard*')" wire:navigate>
                <i class="icon-chart-line text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Dasbor') }}</span>
            </x-nav-link>
        </div>
   </div>
</header>