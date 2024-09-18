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
       <div class="space-x-8 -my-px ml-10 flex">
           <x-nav-link href="{{ route('sh.manage.index') }}" :active="request()->is('sh/manage*')" wire:navigate>
               <i class="fa mx-2 fa-fw fa-ellipsis-h text-sm"></i>
           </x-nav-link>
       </div>
   </div>
</header>