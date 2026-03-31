<header class="bg-white dark:bg-neutral-800 shadow">
    <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div>  
            <h2 class="flex items-center gap-x-4 font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                <x-link href="{{ route('insights') }}" class="inline-block px-3 py-6" wire:navigate>
                    <i class="icon-arrow-left"></i>
                </x-link>
                <div>
                    <span class="hidden sm:inline">{{ __('Chemical Mixing Monitoring') }}</span>
                    <span class="sm:hidden inline">{{ __('CE Mixing') }}</span>
                </div>
            </h2>
        </div>
        <div class="space-x-8 -my-px ml-10 flex">
            <!-- data -->
            <x-nav-link class="text-sm px-1 uppercase" href="/insights/ce/mixing/create" :active="request()->routeIs('insights.ce.mixing.create')" wire:navigate>
                <i class="icon-plus text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Create') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.ce.mixing.new') }}" :active="request()->routeIs('insights.ce.mixing.new')" wire:navigate>
                <i class="icon-play text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('New') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.ce.mixing.index') }}" :active="request()->routeIs('insights.ce.mixing.index')" wire:navigate>
                <i class="icon-database text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Data') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.ce.mixing.manage.index') }}" :active="request()->routeIs('insights.ce.mixing.manage.index')" wire:navigate>
                <i class="icon-settings text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Kelola') }}</span>
            </x-nav-link>
        </div>
    </div>
</header>