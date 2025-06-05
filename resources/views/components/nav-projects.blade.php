<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
               <div class="inline-block py-6">
                    {{ __('Proyek') }}
               </div>
           </h2>
       </div>
       <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('projects.items.index') }}" :active="request()->is('projects/items*')" wire:navigate>
                <i class="icon-drafting-compass text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Indeks') }}</span>
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