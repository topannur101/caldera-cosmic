@props(['view'])

<header class="bg-white dark:bg-neutral-800 shadow">
   <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
       <div>
           <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
               <x-link href="{{ route('inventory.admin.index', ['view' => $view ]) }}" class="inline-block py-6"
                   wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span>{{ __('Administration') }}</span></span>
           </h2>
       </div>
   </div>
</header>