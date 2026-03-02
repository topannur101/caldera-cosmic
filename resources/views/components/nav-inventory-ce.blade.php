<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
               <div class="inline-block py-6">
                    {{ __('Inventaris CE') }}
               </div>
           </h2>
       </div>
       <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('inventory-ce.chemicals.index') }}" :active="request()->is('inventory-ce/chemicals*')" wire:navigate>
                <i class="icon-box text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Kimia ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('inventory-ce.circs.incoming') }}" :active="request()->is('inventory-ce/circs/incoming*')" wire:navigate>
                <i class="icon-folder-input text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Barang masuk ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('inventory-ce.circs.outgoing') }}" :active="request()->is('inventory-ce/circs/outgoing*')" wire:navigate>
                <i class="icon-folder-output text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Barang keluar ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('inventory-ce.monitoring.index') }}" :active="request()->is('inventory-ce/monitoring*')" wire:navigate>
                <i class="icon-monitor text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Mixing Process Monitoring ') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('inventory-ce.manage.index') }}" :active="request()->is('inventory-ce/manage*')" wire:navigate>
                <i class="icon-ellipsis text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Kelola') }}</span>
            </x-nav-link>
        </div>
   </div>
</header>