<x-app-layout>

   <x-slot name="title">{{ __('Admin') }}</x-slot>

   <x-slot name="header">
     <x-nav-admin></x-nav-admin>
   </x-slot>

   <div class="max-w-sm mx-auto text-center px-4 py-16">
    <div class="py-16">
        <div class="mb-8">
            <i class="fa fa-exclamation-triangle text-neutral-300 dark:text-neutral-800 text-7xl"></i>
        </div>
        <div class="text-neutral-500">
            {{ __('Kamu sedang mengakses halaman yang terbatas untuk umum.')}}
        </div>
    </div>
   </div>
</x-app-layout>
