<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;


new #[Layout('layouts.app')] 
class extends Component {

};
?>
<x-slot name="title">{{ __('Kontak') }}</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <h1 class="px-6 text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Bantuan') }}</h1>

    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto mt-8">
        <table class="text-neutral-600 dark:text-neutral-400 w-full table text-sm [&_th]:text-center [&_th]:px-2 [&_th]:py-3 [&_td]:p-2">
            <tr>
                <th>
                    {{ __('Nama') }}
                </th>
                <th>
                    {{ __('Spesialis sistem/peran')}}
                </th>
            </tr>
            <tr class="bg-white border-b dark:bg-neutral-800 dark:border-neutral-700 border-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-600">
                <td>
                    <div class="text-base font-semibold">Imam Pratama Setiady</div>
                    <div class="font-normal text-neutral-500">imam.pratama@taekwang.com</div>
                    <div class="font-normal text-neutral-500">0821-2133-3614</div>
                </td>
                <td class="px-6 py-4">
                    {{ __('Pemantauan open mill')}}, {{ __('Sistem data rheometer')}}, {{ __('Kendali tebal calendar')}}, {{ __('Aurelia')}}
                </td>
            </tr>
            <tr class="bg-white border-b dark:bg-neutral-800 dark:border-neutral-700 border-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-600">
                <td>
                    <div class="text-base font-semibold">Nurul Amalia (Lia)</div>
                    <div class="font-normal text-neutral-500">sa.nurul@taekwang.com</div>
                    <div class="font-normal text-neutral-500">0812-8080-6753</div>
                </td>
                <td class="px-6 py-4">
                    {{ __('Pemantauan open mill')}}, {{ __('Sistem data rheometer')}}, {{ __('Kendali chamber IP')}}
                </td>
            </tr>
            <tr class="bg-white border-b dark:bg-neutral-800 dark:border-neutral-700 border-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-600">
                <td>
                    <div class="text-base font-semibold">Bintang Rizky Lazuardy</div>
                    <div class="font-normal text-neutral-500">bintang.rizky@taekwang.com</div>
                    <div class="font-normal text-neutral-500">0821-2040-0669</div>
                </td>
                <td class="px-6 py-4">
                    {{ __('Sistem data rheometer')}}
                </td>
            </tr>
            <tr class="bg-white border-b dark:bg-neutral-800 dark:border-neutral-700 border-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-600">
                <td>
                    <div class="text-base font-semibold">Andi Permana</div>
                    <div class="font-normal text-neutral-500">andi.permana@taekwang.com</div>
                    <div class="font-normal text-neutral-500">0821-2052-0704</div>
                </td>
                <td class="px-6 py-4">
                    {{ __('Inventaris')}}, {{ __('Mesin')}}, {{ __('Proyek')}}, {{ __('Sistem data kulit')}}
                </td>
            </tr>
            <tr class="bg-white dark:bg-neutral-800 dark:border-neutral-700 border-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-600">
                <td>
                    <div class="text-base font-semibold">Andreas</div>
                    <div class="font-normal text-neutral-500">andreas@taekwang.com</div>
                </td>
                <td class="px-6 py-4">
                    MM HOD
                </td>
            </tr>
        </table>
    </div>

</div>
