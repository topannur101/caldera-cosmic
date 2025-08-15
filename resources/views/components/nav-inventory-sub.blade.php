<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
            <h2 class="flex items-center gap-x-4 font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                @if(request()->is('inventory/items/bulk-operation'))
                    <x-link href="{{ route('inventory.items.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @elseif(request()->is('inventory/items/bulk-operation*'))
                    <x-link href="{{ route('inventory.items.bulk-operation.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @elseif(request()->is('inventory/items/*'))
                    <x-link href="{{ route('inventory.items.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @elseif(request()->is('inventory/circs/bulk-operation'))
                    <x-link href="{{ route('inventory.circs.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @elseif(request()->is('inventory/circs/bulk-operation*'))
                    <x-link href="{{ route('inventory.circs.bulk-operation.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @elseif(request()->is('inventory/manage/*'))
                    <x-link href="{{ route('inventory.manage.index') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>
                @else          
                    <x-link href="{{ route('inventory') }}" class="inline-block px-3 py-6" wire:navigate>
                        <i class="icon-arrow-left"></i>
                    </x-link>
                    <div>
                        <span class="hidden sm:inline">{{ $slot }}</span>
                    </div>    
                @endif
            </h2>
       </div>
       <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('inventory.items.index') }}" :active="request()->is('inventory/items*')" wire:navigate>
                <i class="icon-box text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Barang ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('inventory.circs.index') }}" :active="request()->is('inventory/circs*')" wire:navigate>
                <i class="icon-arrow-right-left text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Sirkulasi ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('inventory.manage.index') }}" :active="request()->is('inventory/manage*')" wire:navigate>
                <i class="icon-ellipsis text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Kelola') }}</span>
            </x-nav-link>
        </div>
   </div>
</header>