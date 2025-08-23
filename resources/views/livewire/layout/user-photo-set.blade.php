<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Validator;

new class extends Component {
    use WithFileUploads;
    public $mode;

    public $id;
    public $url;
    public $temp;

    public function updatedTemp()
    {
        $validator = Validator::make(
            ["temp" => $this->temp],
            ["temp" => "nullable|mimetypes:image/jpeg,image/png,image/gif|max:1024"],
            ["mimetypes" => __("Berkas harus jpg, png, atau gif"), "max" => __("Berkas maksimal 1 MB")],
        );

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = $errors->first("temp");
            $this->js('toast("' . $error . '", { type: "danger" })');
        } else {
            $this->url = $this->temp ? $this->temp->temporaryUrl() : "";
            $temp = $this->temp ? $this->temp->getFilename() : "";
            $this->dispatch("user-photo-updated", $temp);
        }
    }

    public function exception($e, $stopPropagation)
    {
        if ($e instanceof League\Flysystem\UnableToRetrieveMetadata) {
            $this->js('toast("' . __("Berkas tidak sah") . '", { type: "danger" })');
        } else {
            $this->js('toast("' . __("Terjadi kesalahan") . '", { type: "danger" })');
        }
        $stopPropagation();
    }

    public function removeTemp()
    {
        $this->dispatch("user-photo-updated", "");
        $this->temp = "";
        $this->url = "";
    }
};

?>

<div x-data="{ dropping: false }">
    <div class="relative rounded-full w-48 h-48 shadow bg-neutral-200 dark:bg-neutral-700 overflow-hidden" x-on:dragover.prevent="dropping = true">
        <div wire:key="ph" class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="block h-48 w-auto fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                viewBox="0 0 1000 1000"
                xmlns:v="https://vecta.io/nano"
            >
                <path
                    d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"
                ></path>
            </svg>
        </div>
        @if ($url)
            <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ $url }}" />
        @endif

        <div
            wire:loading.class="hidden"
            class="absolute w-full h-full top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white/80 dark:bg-neutral-800/80 p-3"
            x-cloak
            x-show="dropping"
        >
            <div class="flex justify-around items-center w-full h-full border-dashed border-2 border-neutral-500 text-neutral-500 dark:text-neutral-400 rounded-full">
                <div class="text-center">
                    <div class="text-4xl mb-3">
                        <i class="icon-upload"></i>
                    </div>
                </div>
            </div>
        </div>
        <input
            wire:model="temp"
            x-ref="temp"
            type="file"
            accept="image/*"
            class="absolute inset-0 z-50 m-0 p-0 w-full h-full outline-none opacity-0"
            x-cloak
            x-show="dropping"
            x-on:dragover.prevent="dropping = true"
            x-on:dragleave.prevent="dropping = false"
            x-on:drop="dropping = false"
        />
        <div
            wire:loading.class.remove="hidden"
            wire:target="temp"
            class="hidden absolute w-full h-full top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white dark:bg-neutral-800 opacity-80"
        >
            <x-spinner />
        </div>
    </div>
    <div>
        <div class="p-4 text-sm text-center text-neutral-600 dark:text-neutral-400">
            <div wire:key="discard">
                @if ($url)
                    <div class="mb-3">
                        <x-text-button type="button" wire:click="removeTemp">
                            <i class="icon-minus-circle mr-2"></i>
                            {{ __("Buang foto") }}
                        </x-text-button>
                    </div>
                @endif
            </div>
            <div class="mb-4">
                <x-text-button type="button" x-on:click="$refs.temp.click()">
                    <i class="icon-upload mr-2"></i>
                    {{ __("Unggah foto") }}
                </x-text-button>
            </div>
        </div>
    </div>
</div>
