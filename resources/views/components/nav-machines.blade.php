<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
               <div class="inline-block py-6">
                    {{ __('Mesin ') }}
               </div>
           </h2>
       </div>
       <div class="space-x-6 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('inventory.items.index') }}" :active="request()->is('inventory/items*')" wire:navigate>
                <i class="fa fa-book-open text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Direktori') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('inventory.circs.index') }}" :active="request()->is('inventory/circs*')" wire:navigate>
                <i class="fa fa-screwdriver-wrench text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Perawatan ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('inventory.data.index') }}" :active="request()->is('inventory/manage*')" wire:navigate>
                <i class="fa fa-bars-progress text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Waktu henti') }}</span>
            </x-nav-link>
        </div>
       {{-- <div class="space-x-8 -my-px ml-10 flex">
           <x-nav-link href="{{ route('inventory.items.index') }}" :active="request()->is('inventory/items*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-search text-sm"></i>
           </x-nav-link>
           <x-nav-link href="{{ route('inventory.circs.index') }}" :active="request()->is('inventory/circs*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-arrow-right-arrow-left text-sm"></i>
           </x-nav-link>
           <x-nav-link href="{{ route('inventory.data.index') }}" :active="request()->is('inventory/admin*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-ellipsis-h text-sm"></i>
           </x-nav-link>
       </div> --}}
   </div>
</header>