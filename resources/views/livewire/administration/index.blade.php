<x-app-layout>

   <x-slot name="title">{{ __('Administrasi') }}</x-slot>

   <x-slot name="header">
     <x-nav-administration></x-nav-administration>
   </x-slot>

   <div class="max-w-xl lg:max-w-2xl mx-auto px-4 py-16">
       <h2 class="text-4xl font-extrabold dark:text-white">{{ __('Selamat datang') }}</h2>
       <p class="mt-4 mb-12 text-lg text-neutral-500">{{ __('Administrasi adalah tempat bagi superuser untuk mengelola akun pengguna Caldera atau mengelola informasi sepatu seperti model dan style. ') }}</p>
       {{-- <p class="mt-4 mb-12 text-lg text-neutral-500">{{ __('Administrasi saat ini hanya dapat mengelola model sepatu.') }}</p> --}}
       <p class="mb-4 text-lg font-normal text-neutral-500 dark:text-neutral-400">
           {{ __('Mulai dengan mengklik menu navigasi di pojok kanan atas.') }}</p>

       <ul class="space-y-4 text-left text-neutral-500 dark:text-neutral-400">
           <li class="flex items-center space-x-3 rtl:space-x-reverse">
               <i class="fa fa-ellipsis-h fa-fw me-2"></i>
               <span><span class="font-semibold text-neutral-900 dark:text-white">{{ __('Kelola') }}</span>
                   {{ ' ' . __('untuk mengelola model.') }}</span>
           </li>
       </ul>
   </div>
</x-app-layout>
