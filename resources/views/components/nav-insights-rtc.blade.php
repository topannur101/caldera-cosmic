<header class="bg-white dark:bg-neutral-800 shadow">
    <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div>  
            <h2 class="flex items-center gap-x-4 font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                <x-link href="{{ route('insights') }}" class="inline-block px-3 py-6" wire:navigate>
                    <i class="icon-arrow-left"></i>
                </x-link>
                <div>
                    <span class="hidden sm:inline">{{ __('Kendali tebal calendar') }}</span>
                    <span class="sm:hidden inline">{{ __('RTC') }}</span>
                </div>
            </h2>
        </div>
        <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.rtc.index') }}" :active="request()->routeIs('insights.rtc.index')" wire:navigate>
                <i class="icon-heart-pulse text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Data') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.rtc.slideshows') }}" :active="request()->routeIs('insights.rtc.slideshows')" wire:navigate>
                <i class="icon-tv-minimal text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Tayangan slide') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.rtc.manage.index') }}" :active="request()->routeIs('insights.rtc.manage.index')" wire:navigate>
                <i class="icon-ellipsis text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Kelola') }}</span>
            </x-nav-link>
        </div>
    </div>
</header>
