<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {


};

?>

<x-slot name="title">{{ __('Leather Data Collection') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-ldc></x-nav-insights-ldc>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div class="flex flex-col gap-x-4 md:gap-x-4 sm:flex-row">
        <div>
            <ul class="w-full sm:w-44 md:w-56 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <li class="w-full border-b border-gray-200 rounded-t-lg dark:border-gray-600">
                    <div class="flex items-center ps-3">
                        <input id="list-radio-license" type="radio" value="" name="list-radio" class="w-4 h-4 text-caldy-600 bg-gray-100 border-gray-300 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-gray-700 dark:focus:ring-offset-gray-700 focus:ring-2 dark:bg-gray-600 dark:border-gray-500">
                        <label for="list-radio-license" class="w-full py-3 ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">{{ __('Grup baru') }}</label>
                    </div>
                </li>
                <li class="w-full border-b border-gray-200 rounded-t-lg dark:border-gray-600">
                    <div class="flex items-center ps-3">
                        <input id="list-radio-id" type="radio" value="" name="list-radio" class="w-4 h-4 text-caldy-600 bg-gray-100 border-gray-300 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-gray-700 dark:focus:ring-offset-gray-700 focus:ring-2 dark:bg-gray-600 dark:border-gray-500">
                        <label for="list-radio-id" class="w-full py-3 ms-2 text-sm font-medium text-gray-900 dark:text-gray-300"><div>Line 1</div><div>Line 2</div></label>
                    </div>
                </li>
                <li class="w-full border-b border-gray-200 rounded-t-lg dark:border-gray-600">
                    <div class="flex items-center ps-3">
                        <input id="list-radio-military" type="radio" value="" name="list-radio" class="w-4 h-4 text-caldy-600 bg-gray-100 border-gray-300 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-gray-700 dark:focus:ring-offset-gray-700 focus:ring-2 dark:bg-gray-600 dark:border-gray-500">
                        <label for="list-radio-military" class="w-full py-3 ms-2 text-sm font-medium text-gray-900 dark:text-gray-300"><div>Line 1</div><div>Line 2</div></label>
                    </div>
                </li>
                <li class="w-full border-b border-gray-200 rounded-t-lg dark:border-gray-600">
                    <div class="flex items-center ps-3">
                        <input id="list-radio-passport" type="radio" value="" name="list-radio" class="w-4 h-4 text-caldy-600 bg-gray-100 border-gray-300 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-gray-700 dark:focus:ring-offset-gray-700 focus:ring-2 dark:bg-gray-600 dark:border-gray-500">
                        <label for="list-radio-passport" class="w-full py-3 ms-2 text-sm font-medium text-gray-900 dark:text-gray-300"><div>Line 1</div><div>Line 2</div></label>
                    </div>
                </li>
            </ul> 
        </div>
        <div>
            <ul class="w-full sm:w-44 md:w-56 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <li class="w-full border-b border-gray-200 rounded-t-lg dark:border-gray-600">
                    <div class="flex items-center ps-3">
                        <input id="list-radio-license" type="radio" value="" name="hides" class="w-4 h-4 text-caldy-600 bg-gray-100 border-gray-300 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-gray-700 dark:focus:ring-offset-gray-700 focus:ring-2 dark:bg-gray-600 dark:border-gray-500">
                        <label for="hides-license" class="w-full py-3 ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">{{ __('Kulit baru') }}</label>
                    </div>
                </li>
                <li class="w-full border-b border-gray-200 rounded-t-lg dark:border-gray-600">
                    <div class="flex items-center ps-3">
                        <input id="hides-id" type="radio" value="" name="hides" class="w-4 h-4 text-caldy-600 bg-gray-100 border-gray-300 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-gray-700 dark:focus:ring-offset-gray-700 focus:ring-2 dark:bg-gray-600 dark:border-gray-500">
                        <label for="hides-id" class="w-full py-3 ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">XA12345</label>
                    </div>
                </li>
                <li class="w-full border-b border-gray-200 rounded-t-lg dark:border-gray-600">
                    <div class="flex items-center ps-3">
                        <input id="hides-military" type="radio" value="" name="hides" class="w-4 h-4 text-caldy-600 bg-gray-100 border-gray-300 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-gray-700 dark:focus:ring-offset-gray-700 focus:ring-2 dark:bg-gray-600 dark:border-gray-500">
                        <label for="hides-military" class="w-full py-3 ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">XA12346</label>
                    </div>
                </li>
                <li class="w-full border-b border-gray-200 rounded-t-lg dark:border-gray-600">
                    <div class="flex items-center ps-3">
                        <input id="hides-passport" type="radio" value="" name="hides" class="w-4 h-4 text-caldy-600 bg-gray-100 border-gray-300 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-gray-700 dark:focus:ring-offset-gray-700 focus:ring-2 dark:bg-gray-600 dark:border-gray-500">
                        <label for="hides-passport" class="w-full py-3 ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">XA12347</label>
                    </div>
                </li>
            </ul> 
        </div>
        <div class="w-full">
            <form wire:submit="save" class="bg-white shadow rounded-lg p-6">
                <div class="mb-6">
                    <div class="grid grid-cols-3 gap-x-3">
                        <div class="col-span-2">
                            <label for="recipe-name"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Name') }}</label>
                            <x-text-input id="recipe-name" wire:model="name" type="text" :disabled="Gate::denies('manage', InsRtcRecipe::class)" />
                            @error('name')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        <div>
                            <label for="recipe-og_rs"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('OG/RS') }}</label>
                            <x-text-input id="recipe-og_rs" wire:model="og_rs" type="text" :disabled="Gate::denies('manage', InsRtcRecipe::class)" />
                            @error('og_rs')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-x-3">
                        <div class="mt-6">
                            <label for="recipe-std_min"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Min') }}</label>
                            <x-text-input id="recipe-std_min" wire:model="std_min" type="number" step=".01" :disabled="Gate::denies('manage', InsRtcRecipe::class)" />
                            @error('std_min')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        <div class="mt-6">
                            <label for="recipe-std_max"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Maks') }}</label>
                            <x-text-input id="recipe-std_max" wire:model="std_max" type="number" step=".01" :disabled="Gate::denies('manage', InsRtcRecipe::class)" />
                            @error('std_max')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        <div class="mt-6">
                            <label for="recipe-std_mid"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Std Mid') }}</label>
                            <x-text-input id="recipe-std_mid" wire:model="std_mid" type="number" step=".01" :disabled="Gate::denies('manage', InsRtcRecipe::class)" />
                            @error('std_mid')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-x-3">
                        <div class="mt-6">
                            <label for="recipe-pfc_min"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('PFC Min') }}</label>
                            <x-text-input id="recipe-pfc_min" wire:model="pfc_min" type="number" step=".01" :disabled="Gate::denies('manage', InsRtcRecipe::class)" />
                            @error('pfc_min')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        <div class="mt-6">
                            <label for="recipe-pfc_max"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('PFC Max') }}</label>
                            <x-text-input id="recipe-pfc_max" wire:model="pfc_max" type="number" step=".01" :disabled="Gate::denies('manage', InsRtcRecipe::class)" />
                            @error('pfc_max')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                        <div class="mt-6">
                            <label for="recipe-scale"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Scale') }}</label>
                            <x-text-input id="recipe-scale" wire:model="scale" type="number" step=".01" :disabled="Gate::denies('manage', InsRtcRecipe::class)" />
                            @error('scale')
                                <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                            @enderror
                        </div>
                    </div>
                </div>
                @can('manage', InsRtcRecipe::class)
                <div class="flex justify-between items-end">
                    <div>
                        <x-text-button type="button" class="uppercase text-xs text-red-500" wire:click="delete"
                            wire:confirm="{{ __('Tindakan ini tidak dapat diurungkan. Lanjutkan?') }}">
                            {{ __('Hapus') }}
                        </x-text-button>
                    </div>
                    <x-primary-button type="submit">
                        {{ __('Simpan') }}
                    </x-primary-button>
                </div>
                @endcan
            </form>
        </div>
    </div>
</div>
