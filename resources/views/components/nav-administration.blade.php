<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
               <div class="inline-block py-6">
                @if( request()->is('sh/replace*') )
                    {{ __('Replace') }}
                @elseif( request()->is('sh/manage*') )
                    {{ __('Kelola') }}
                @else
                    <span class="hidden sm:inline">{{ __('Administrasi') }}</span><span class="sm:hidden inline">{{ __('Admin') }}</span>
                @endif
               </div>
           </h2>
       </div>
       {{-- <div class="space-x-8 -my-px ml-10 flex">
           <x-nav-link href="{{ route('administration.manage.index') }}" :active="request()->is('sh/manage*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-ellipsis-h text-sm"></i>
           </x-nav-link>
       </div> --}}
       <div class="space-x-6 -my-px ml-10 flex">
        <x-nav-link class="text-sm px-6 uppercase" href="{{ route('administration.account.index') }}" :active="request()->routeIs('administration.account.index')" wire:navigate>
            <i class="fa fa-circle-user text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Akun') }}</span>
        </x-nav-link>
        <x-nav-link class="text-sm px-6 uppercase" href="{{ route('administration.authorization.index') }}" :active="request()->routeIs('administration.authorizations.index')" wire:navigate>
            <i class="fa fa-user-lock text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Wewenang') }}</span>
        </x-nav-link>
        <x-nav-link class="text-sm px-6 uppercase"  href="{{ route('administration.manage.index') }}" :active="request()->is('administration/manage*')" wire:navigate>
            <i class="fa fa-ellipsis-h text-sm"></i><span class="ms-3 hidden lg:inline">{{ __('Kelola') }}</span>
        </x-nav-link>
    </div>
   </div>
</header>