<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
               <div class="inline-block py-6">
                    {{ __('Tugas') }}
               </div>
           </h2>
       </div>
       <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('tasks.dashboard.index') }}" :active="request()->is('tasks/dashboard*')" wire:navigate>
                <i class="icon-gauge text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Dasbor') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('tasks.projects.index') }}" :active="request()->is('tasks/projects*')" wire:navigate>
                <i class="icon-drafting-compass text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Proyek') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('tasks.items.index') }}" :active="request()->is('tasks/items*')" wire:navigate>
                <i class="icon-ticket text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Tugas') }}</span>
            </x-nav-link>
        </div>
   </div>
</header>