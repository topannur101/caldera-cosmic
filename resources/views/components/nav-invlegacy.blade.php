<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
               <div class="inline-block py-6">
                    {{ __('Inventaris') }}
               </div>
           </h2>
       </div>
       <div class="space-x-6 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('invlegacy.items.index') }}" :active="request()->is('invlegacy/items*')" wire:navigate>
                <i class="fa fa-cube text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Barang') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('invlegacy.circs.index') }}" :active="request()->is('invlegacy/circs*')" wire:navigate>
                <i class="fa fa-arrow-right-arrow-left text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Sirkulasi') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('invlegacy.manage.index') }}" :active="request()->is('invlegacy/manage*')" wire:navigate>
                <i class="fa fa-ellipsis-h text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Kelola') }}</span>
            </x-nav-link>
        </div>
       {{-- <div class="space-x-8 -my-px ml-10 flex">
           <x-nav-link href="{{ route('invlegacy.items.index') }}" :active="request()->is('invlegacy/items*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-search text-sm"></i>
           </x-nav-link>
           <x-nav-link href="{{ route('invlegacy.circs.index') }}" :active="request()->is('invlegacy/circs*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-arrow-right-arrow-left text-sm"></i>
           </x-nav-link>
           <x-nav-link href="{{ route('invlegacy.manage.index') }}" :active="request()->is('invlegacy/admin*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-ellipsis-h text-sm"></i>
           </x-nav-link>
       </div> --}}
   </div>
</header>