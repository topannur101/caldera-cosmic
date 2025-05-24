<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
            @if(request()->is('inventory/items/bulk-operation'))
                <x-link href="{{ route('inventory.items.index') }}" class="inline-block py-6"
                wire:navigate><i class="icon-arrow-left"></i></x-link><span class="hidden sm:inline ml-4"><span>{{ $slot }}</span></span>

            @elseif(request()->is('inventory/items/bulk-operation*'))
                <x-link href="{{ route('inventory.items.bulk-operation.index') }}" class="inline-block py-6"
                wire:navigate><i class="icon-arrow-left"></i></x-link><span class="hidden sm:inline ml-4"><span>{{ $slot }}</span></span>

            @elseif(request()->is('inventory/items/*'))
                <x-link href="{{ route('inventory.items.index') }}" class="inline-block py-6"
                   wire:navigate><i class="icon-arrow-left"></i></x-link><span class="hidden sm:inline ml-4"><span>{{ $slot }}</span></span>
            
            @elseif(request()->is('inventory/circs/bulk-operation'))
                <x-link href="{{ route('inventory.circs.index') }}" class="inline-block py-6"
                wire:navigate><i class="icon-arrow-left"></i></x-link><span class="hidden sm:inline ml-4"><span>{{ $slot }}</span></span>

            @elseif(request()->is('inventory/circs/bulk-operation*'))
                <x-link href="{{ route('inventory.circs.bulk-operation.index') }}" class="inline-block py-6"
                wire:navigate><i class="icon-arrow-left"></i></x-link><span class="hidden sm:inline ml-4"><span>{{ $slot }}</span></span>
                
            @else           
                <x-link href="{{ route('inventory') }}" class="inline-block py-6"
                wire:navigate><i class="icon-arrow-left"></i></x-link><span class="hidden sm:inline ml-4"><span>{{ $slot }}</span></span>    

            @endif
           </h2>
       </div>
       <div class="sm:space-x-6 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('inventory.items.index') }}" :active="request()->is('inventory/items*')" wire:navigate>
                <i class="icon-box text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Barang ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('inventory.circs.index') }}" :active="request()->is('inventory/circs*')" wire:navigate>
                <i class="icon-arrow-right-left text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Sirkulasi ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('inventory.orders.index') }}" :active="request()->is('inventory/orders*')" wire:navigate>
                <i class="icon-shopping-cart text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Pesanan') }}</span>
            </x-nav-link>
        </div>
   </div>
</header>