<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
               <div class="inline-block py-6">
                    {{ __('Proyek ') }}
               </div>
           </h2>
       </div>
       <div class="sm:space-x-6 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('projects.schedule.index') }}" :active="request()->is('projects/schedule*')" wire:navigate>
                <i class="icon-calendar text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Jadwal') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('projects.tasks.index') }}" :active="request()->is('projects/tasks*')" wire:navigate>
                <i class="icon-clipboard-list text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Tugas') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('projects.summary.index') }}" :active="request()->is('projects/summary*')" wire:navigate>
                <i class="icon-chart-pie text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Ringkasan') }}</span>
            </x-nav-link>
        </div>
   </div>
</header>