<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Validator;

new #[Layout('layouts.app')] 
class extends Component {

    use WithFileUploads;

    public $file;
    
    public function updatedFile()
    {
        $this->validate([
            'file' => 'file|mimes:xls,xlsx|max:1024'
        ]);

        $this->extractData();
    }

    private function extractData()
    {
        
    }


};

?>

<x-slot name="title">{{ __('IP Stabilization Control') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-stc></x-nav-insights-stc>
</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
        <h1 class="grow text-2xl text-neutral-900 dark:text-neutral-100 px-8">
            {{ __('Pembukuan') }}</h1>

    <div class="overflow-auto w-full my-8">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-6">
                <div>
                    <label for="stc-device_code"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode alat') }}</label>
                    <x-text-input id="stc-device_code" wire:model="device_code" type="text"
                        :disabled="Gate::denies('manage', InsStcLog::class)" />
                    @error('device_code')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div>
                    <label for="stc-machine_code"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode mesin') }}</label>
                    <x-text-input id="stc-machine_code" wire:model="machine_code" type="text"
                        :disabled="Gate::denies('manage', InsStcLog::class)" />
                    @error('machine_code')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
            </div>
            <div class="flex justify-center py-8 mt-6">

                <input wire:model="file" type="file"
                class="absolute inset-0 m-0 p-0 w-full h-full outline-none opacity-0" x-cloak x-ref="file"
                x-show="dropping" x-on:dragleave.prevent="dropping = false" x-on:drop="dropping = false" />
                <x-secondary-button type="button" x-on:click="$refs.file.click()"><i
                        class="fa fa-upload mr-2"></i>{{ __('Unggah') }}</x-secondary-button>
            </div>
        </div>
    </div>
</div>
