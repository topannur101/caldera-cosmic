<?php

use Livewire\Volt\Component;

new class extends Component {

    public array $cmachines = [];

    public string $active_tab = 'manage';

};

?>

<div class="py-8 text-center text-sm">
    <x-slide-over name="quotas">
        <div class="p-6 overflow-auto">
            <div class="flex justify-between items-start mb-6">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Penjatahan') }}
                </h2>
                <x-text-button type="button" x-on:click="window.dispatchEvent(escKey)">
                    <i class="icon-x"></i>
                </x-text-button>
            </div>
            {{-- Tab Navigation --}}
            <div x-data="{
                    tabSelected: @entangle('active_tab'),
                    tabButtonClicked(tabButton){
                        this.tabSelected = tabButton.dataset.tab;
                    }
                }" class="relative w-full">                
                <div class="relative inline-grid items-center justify-center w-full h-10 grid-cols-2 p-1 text-neutral-500 bg-neutral-200 dark:bg-neutral-900 rounded-full select-none">
                    <button data-tab="manage" @click="tabButtonClicked($el);" type="button" 
                            :class="tabSelected === 'manage' ? 'text-neutral-900 dark:text-neutral-100' : ''"
                            class="relative z-10 inline-flex items-center justify-center w-full h-8 px-3 text-sm font-medium transition-all rounded-full cursor-pointer whitespace-nowrap">
                        {{ __('Kelola') }}
                    </button>
                    <button data-tab="pin" @click="tabButtonClicked($el);" type="button" 
                            :class="tabSelected === 'pin' ? 'text-neutral-900 dark:text-neutral-100' : ''"
                            class="relative z-10 inline-flex items-center justify-center w-full h-8 px-3 text-sm font-medium transition-all rounded-full cursor-pointer whitespace-nowrap">
                        {{ __('Pin') }}
                    </button>
                    
                    {{-- Marker positioned with CSS based on active tab --}}
                    <div class="absolute left-0 h-full p-1 duration-300 ease-out transition-transform" 
                        :class="tabSelected === 'pin' ? 'translate-x-full' : 'translate-x-0'"
                        style="width: calc(50%);">
                        <div class="w-full h-full bg-white dark:bg-neutral-700 rounded-full shadow-sm"></div>
                    </div>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto pt-4" 
                x-data="{ tabSelected: @entangle('active_tab') }">
                <div class="h-full">
                    {{-- Quantity Chart --}}
                    <div x-show="tabSelected === 'manage'" x-cloak>
                        Editor view
                    </div>

                    {{-- Amount Chart --}}
                    <div x-show="tabSelected === 'pin'" x-cloak>
                        Pin view
                    </div>
                </div>
            </div>
        </div>
    </x-slide-over>
    <x-text-button class="mb-2" x-on:click="$dispatch('open-slide-over', 'quotas')">
        <div class="uppercase text-xs text-neutral-500">{{ __('Jatah') }} <i class="icon-settings-2 ml-1"></i></div>
    </x-text-button>
    <div class="flex min-h-20">
        <div class="my-auto px-6 text-center w-full">
            {{ __('Tak ada mesin yang dipilih') }}
        </div>
    </div>
</div>