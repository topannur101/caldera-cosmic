<header class="bg-white dark:bg-neutral-800 shadow">
    <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div>  
            <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                <x-link href="{{ route('insight') }}" class="inline-block py-6" wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('Leather Data Collection') }}</span><span class="sm:hidden inline">{{ __('LDC') }}</span></span>
            </h2>
        </div>
        <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link href="{{ route('insight.ldc.index') }}" :active="request()->routeIs('insight.ldc.index')" wire:navigate>
                <i class="fa mx-2 fa-fw fa-pen text-sm"></i>
            </x-nav-link>
            <x-nav-link href="{{ route('insight.ldc.hides') }}" :active="request()->routeIs('insight.ldc.hides')" wire:navigate>
                <i class="fa mx-2 fa-fw fa-table text-sm"></i>
            </x-nav-link>
        </div>
    </div>
</header>