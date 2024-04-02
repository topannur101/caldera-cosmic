<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
               <div class="inline-block py-6">{{ __('Inventaris') }}</div>
           </h2>
       </div>
       <div class="space-x-8 -my-px ml-10 flex">
           <x-nav-link href="{{ route('inventory.items.index') }}" :active="request()->routeIs('inventory/items*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-search text-sm"></i>
           </x-nav-link>
           <x-nav-link href="{{ route('inventory.circs.index') }}" :active="request()->routeIs('inventory/circs*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-arrow-right-arrow-left text-sm"></i>
           </x-nav-link>
           <x-nav-link href="{{ route('inventory.manage.index') }}" :active="request()->routeIs('inventory/manage*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-ellipsis-h text-sm"></i>
           </x-nav-link>
       </div>
   </div>
</header>