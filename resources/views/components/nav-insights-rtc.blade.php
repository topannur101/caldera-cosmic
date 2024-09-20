<header class="bg-white dark:bg-neutral-800 shadow">
    <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div>  
            <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                <x-link href="{{ route('insight') }}" class="inline-block py-6" wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('Rubber Thickness Control') }}</span><span class="sm:hidden inline">{{ __('RTC') }}</span></span>
            </h2>
        </div>
        <div class="space-x-3 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-3 uppercase" href="{{ route('insight.rtc.index') }}" :active="request()->routeIs('insight.rtc.index')" wire:navigate>
                <i class="fa fa-heart-pulse text-sm"></i><span class="ms-3 hidden md:inline">{{ __('Data') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-3 uppercase" href="{{ route('insight.rtc.slideshows') }}" :active="request()->routeIs('insight.rtc.slideshows')" wire:navigate>
                <i class="fa fa-tv text-sm"></i><span class="ms-3 hidden md:inline">{{ __('Tayangan slide') }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-3 uppercase" href="{{ route('insight.rtc.manage.index') }}" :active="request()->routeIs('insight.rtc.manage.index')" wire:navigate>
                <i class="fa fa-ellipsis-h text-sm"></i><span class="ms-3 hidden md:inline">{{ __('Kelola') }}</span>
            </x-nav-link>
        </div>
    </div>
</header>
