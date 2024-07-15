<header class="bg-white dark:bg-neutral-800 shadow">
    <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div>  
            <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                <x-link href="{{ route('insight') }}" class="inline-block py-6" wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('Open Mill Validator') }}</span><span class="sm:hidden inline">{{ __('OMV') }}</span></span>
            </h2>
        </div>
        <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link href="{{ route('insight.omv.index') }}" :active="request()->routeIs('insight.omv.index')" wire:navigate>
                <i class="fa mx-2 fa-fw fa-pen text-sm"></i>
            </x-nav-link>
            <x-nav-link href="{{ route('insight.omv.summary') }}" :active="request()->routeIs('insight.omv.summary')" wire:navigate>
                <i class="fa mx-2 fa-fw fa-heart-pulse text-sm"></i>
            </x-nav-link>
            <x-nav-link href="{{ route('insight.omv.manage.index') }}" :active="request()->routeIs('insight.omv.manage.index')" wire:navigate>
                <i class="fa mx-2 fa-fw fa-ellipsis-h text-sm"></i>
            </x-nav-link>
        </div>
    </div>
</header>