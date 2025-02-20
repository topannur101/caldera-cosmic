<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
               <x-link href="{{ route('inventory.items.index') }}" class="inline-block py-6"
                   wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="hidden sm:inline ml-4"><span>{{ $slot }}</span></span>
           </h2>
       </div>
       <div class="space-x-6 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('inventory.items.index') }}" :active="request()->is('inventory/items*')" wire:navigate>
                <i class="fa fa-cube text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Barang ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('inventory.circs.index') }}" :active="request()->is('inventory/circs*')" wire:navigate>
                <i class="fa fa-arrow-right-arrow-left text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Sirkulasi ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('inventory.reqs.index') }}" :active="request()->is('inventory/reqs*')" wire:navigate>
                <i class="fa fa-cart-shopping text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Pengajuan') }}</span>
            </x-nav-link>
        </div>
   </div>
</header>