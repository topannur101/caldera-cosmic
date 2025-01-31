<?php

use Livewire\Volt\Component;

new #[Layout('layouts.app')] 
class extends Component {

    public $photo;
    public $url;
    public $isForm;
  
};

?>

<div x-data="{ dropping: false }" >
    <div class="relative rounded-none sm:w-48 h-48 md:w-72 md:h-72 lg:w-80 lg:h-80 bg-neutral-200 dark:bg-neutral-700 sm:rounded-md overflow-hidden"
        x-on:dragover.prevent="dropping = true">
        <div wire:key="ph" class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
            <svg xmlns="http://www.w3.org/2000/svg"
                class="block h-32 w-auto fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                viewBox="0 0 38.777 39.793">
                <path
                    d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" />
            </svg>
        </div>
        @if($url)
        <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ $url }}" />
        @endif
        <div wire:loading.class="hidden" class="absolute w-full h-full top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white/80 dark:bg-neutral-800/80  p-3"
            x-cloak x-show="dropping">
            <div class="flex justify-around items-center w-full h-full border-dashed border-2 border-neutral-500  text-neutral-500 dark:text-neutral-400 rounded">
                <div class="text-center">
                    <div class="text-4xl mb-3">
                        <i class="fa fa-upload"></i>
                    </div>
                    <div>
                        {{ __('Jatuhkan untuk mengunggah') }}
                    </div>
                </div>
            </div>
        </div>
        <input wire:model="photo" x-ref="invItemPhoto" type="file" accept="image/*" class="absolute inset-0 z-50 m-0 p-0 w-full h-full outline-none opacity-0"
            x-cloak x-show="dropping"
            x-on:dragover.prevent="dropping = true"
            x-on:dragleave.prevent="dropping = false"
            x-on:drop="dropping = false" />
        <div wire:loading.class.remove="hidden" wire:target="photo" class="hidden absolute w-full h-full top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white dark:bg-neutral-800 opacity-80">
            <x-spinner />
        </div>
    </div>
    <div wire:key="tools">
        @if($isForm)
        <div class="p-4 text-sm text-neutral-600 dark:text-neutral-400">
            <div wire:key="discard">
                @if($url)
                <div class="mb-4">
                    <x-text-button type="button" wire:click="removePhoto"><i class="fa fa-fw fa-times mr-3"></i>{{ __('Buang foto') }}</x-text-button>
                </div>
                @endif
            </div>
            <div class="mb-4">
                <x-text-button type="button" x-on:click="$refs.invItemPhoto.click()"><i class="fa fa-fw fa-upload mr-3"></i>{{ __('Unggah foto') }}</x-text-button>
            </div>
            <div>
                <x-text-button type="button"><i class="fa fa-fw fa-file-import mr-3"></i>{{ __('Tarik dari ttconsumable') }}</x-text-button>
            </div>
        </div>
        @endif
    </div>
</div>