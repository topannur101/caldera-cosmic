<header class="bg-white dark:bg-neutral-800 shadow">
    <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div>  
            <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                <x-link href="{{ route('insights') }}" class="inline-block py-6" wire:navigate><i class="icon-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('Sistem data rheometer') }}</span><span class="sm:hidden inline">{{ __('RDC') }}</span></span>
            </h2>
        </div>
        <div class="sm:space-x-6 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('insights.rdc.queue.index') }}" :active="request()->routeIs('insights.rdc.queue.index')" wire:navigate>
                <i class="icon-inbox text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Antrian') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('insights.rdc.data.index') }}" :active="request()->routeIs('insights.rdc.data.index')" wire:navigate>
                <i class="icon-heart-pulse text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Data') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-6 uppercase" href="{{ route('insights.rdc.manage.index') }}" :active="request()->routeIs('insights.rdc.manage.index')" wire:navigate>
                <i class="icon-ellipsis text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Kelola') }}</span>
            </x-nav-link>
        </div>
    </div>
</header>