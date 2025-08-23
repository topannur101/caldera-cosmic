<header class="bg-white dark:bg-neutral-800 shadow">
    <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div>
            <h2 class="flex items-center gap-x-4 font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                <x-link href="{{ route('insights') }}" class="inline-block px-3 py-6" wire:navigate>
                    <i class="icon-arrow-left"></i>
                </x-link>
                <div>
                    <span class="hidden sm:inline">{{ __("Sistem data kulit") }}</span>
                    <span class="sm:hidden inline">{{ __("LDC") }}</span>
                </div>
            </h2>
        </div>
        <div class="space-x-8 -my-px ml-10 flex">
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.ldc.create.index') }}" :active="request()->routeIs('insights.ldc.create.index')" wire:navigate>
                <i class="icon-pencil text-sm"></i>
                <span class="ms-3 hidden lg:inline">{{ __("Buat") }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.ldc.data.index') }}" :active="request()->routeIs('insights.ldc.data.index')" wire:navigate>
                <i class="icon-heart-pulse text-sm"></i>
                <span class="ms-3 hidden lg:inline">{{ __("Data") }}</span>
            </x-nav-link>
            <x-nav-link class="text-sm px-1 uppercase" href="{{ route('insights.ldc.manage.index') }}" :active="request()->routeIs('insights.ldc.manage.index')" wire:navigate>
                <i class="icon-ellipsis text-sm"></i>
                <span class="ms-3 hidden lg:inline">{{ __("Kelola") }}</span>
            </x-nav-link>
        </div>
    </div>
</header>
