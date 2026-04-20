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
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.ce.inventory.chemicals.index') }}" :active="request()->is('insight/ce/chemicals*')" wire:navigate>
                <i class="icon-box text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Barang') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.ce.inventory.circs.index') }}" :active="request()->is('insight/ce/circs')" wire:navigate>
                <i class="icon-folder-input text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Sirkulasi') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.ce.manage.index') }}" :active="request()->is('insight/ce/manage*')" wire:navigate>
                <i class="icon-ellipsis text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Kelola') }}</span>
            </x-nav-link>
        </div>
   </div>
</header>