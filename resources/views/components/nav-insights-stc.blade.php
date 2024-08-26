<header class="bg-white dark:bg-neutral-800 shadow">
    <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div>  
            <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                <x-link href="{{ route('insight') }}" class="inline-block py-6" wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('IP Stabilization Control') }}</span><span class="sm:hidden inline">{{ __('IP STC') }}</span></span>
            </h2>
        </div>
        <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link href="{{ route('insight.stc.index') }}" :active="request()->routeIs('insight.stc.index')" wire:navigate>
                <i class="fa mx-2 fa-fw fa-pen text-sm"></i>
            </x-nav-link>
            <x-nav-link href="{{ route('insight.stc.summary') }}" :active="request()->routeIs('insight.stc.summary')" wire:navigate>
                <i class="fa mx-2 fa-fw fa-heart-pulse text-sm"></i>
            </x-nav-link>
            <x-nav-link href="{{ route('insight.stc.manage.index') }}" :active="request()->routeIs('insight.stc.manage.index')" wire:navigate>
                <i class="fa mx-2 fa-fw fa-ellipsis text-sm"></i>
            </x-nav-link>
        </div>
    </div>
</header>