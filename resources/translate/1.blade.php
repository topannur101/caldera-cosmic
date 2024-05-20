<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Js;
use App\Models\Pref;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] class extends Component {
    
    public string $bg = '';
    public string $accent = '';
    public string $mblur = '';

    public function mount()
    {
        $accountPref = Pref::where('user_id', Auth::user()->id)
            ->where('name', 'account')
            ->first();
        $data           = $accountPref ? json_decode($accountPref->data, true) : [];
        $this->bg       = isset($data['bg'])        ? $data['bg']               : 'auto';
        $this->accent   = isset($data['accent'])    ? $data['accent']           : 'purple';
        $this->mblur    = isset($data['mblur'])     ? (bool) $data['mblur']     : false;
    }
 
    public function updated($property)
    {
        if ($property == 'bg') {
            $validated = $this->validate([
                'bg' => ['required', Rule::in(['auto', 'dark', 'light'])]
            ]);
            $pref = Pref::firstOrCreate(
                ['user_id' => Auth::user()->id, 'name' => 'account'],
                ['data' => json_encode([])]
            );
            $existingData = json_decode($pref->data, true);
            $existingData['bg'] = $validated['bg'];
            $pref->update(['data' => json_encode($existingData)]);

            session(['bg' => $this->bg]);
            $this->js("const body = document.body;
            const classes = ['auto', 'dark', 'light'];
            classes.forEach((cls) => { body.classList.remove(cls); });
            body.classList.add('" . $this->bg . "');");
        }
        if ($property == 'accent') {
            $validated = $this->validate([
                'accent' => ['required', Rule::in(['purple', 'green', 'pink', 'blue', 'teal', 'orange', 'grey', 'brown', 'yellow'])]
            ]);
            $pref = Pref::firstOrCreate(
                ['user_id' => Auth::user()->id, 'name' => 'account'],
                ['data' => json_encode([])]
            );
            $existingData = json_decode($pref->data, true);
            $existingData['accent'] = $validated['accent'];
            $pref->update(['data' => json_encode($existingData)]);

            session(['accent' => $this->accent]);
            $this->js("const body = document.body;
            const classes = ['purple', 'green', 'pink', 'blue', 'teal', 'orange', 'grey', 'brown', 'yellow'];
            classes.forEach((cls) => { body.classList.remove(cls); });
            body.classList.add('" . $this->accent . "');");
        }
        if ($property == 'mblur') {
            $mblur = (bool) $this->mblur;
            $pref = Pref::firstOrCreate(
                ['user_id' => Auth::user()->id, 'name' => 'account'],
                ['data' => json_encode([])]
            );
            $existingData = json_decode($pref->data, true);
            $existingData['mblur'] = $mblur;
            $pref->update(['data' => json_encode($existingData)]);

            session(['mblur' => $this->mblur]);
        }

    }
};

?>
<x-slot name="title">{{ __('Tema') }}</x-slot>
<x-slot name="header">
    <header class="bg-white dark:bg-neutral-800 shadow">
        <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div>
                <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                    <x-link wire:navigate href="{{ route('account') }}" class="inline-block py-6"><i
                            class="fa fa-arrow-left"></i></x-link><span class="ml-4">{{ __('Tema') }}</span>
                </h2>
            </div>
        </div>
    </header>
</x-slot>
<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <div class="bg-white dark:bg-neutral-800 shadow p-6 sm:rounded-lg mb-6">
            <h2 class="text-lg font-medium mb-3">
                {{ __('Latar') }}
            </h2>
            <fieldset class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-4">
                <div>
                    <input type="radio" name="bg" id="bg-auto" wire:model.live="bg"
                        class="peer hidden [&:checked_+_label_svg]:block" value="auto" />
                    <label for="bg-auto"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <p><i class="text-neutral-500 fa fa-circle-half-stroke"></i></p>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Patuhi sistem') }}</p>
                    </label>
                </div>
                <div>
                    <input type="radio" name="bg" id="bg-light" wire:model.live="bg"
                        class="peer hidden [&:checked_+_label_svg]:block" value="light" />
                    <label for="bg-light"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <p><i class="text-neutral-500 fa fa-sun"></i></p>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Cerah') }}</p>
                    </label>
                </div>
                <div>
                    <input type="radio" name="bg" id="bg-dark" value="dark" wire:model.live="bg"
                        class="peer hidden [&:checked_+_label_svg]:block" />
                    <label for="bg-dark"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <p><i class="text-neutral-500 fa fa-moon"></i></p>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Gelap') }}</p>
                    </label>
                </div>
            </fieldset>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow p-6 sm:rounded-lg mb-6">
            <h2 class="text-lg font-medium mb-3">
                {{ __('Aksen') }}
            </h2>
            <fieldset class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-4">
                <div>
                    <input type="radio" name="accent" value="purple" wire:model.live="accent" id="accent-purple"
                        class="peer hidden [&:checked_+_label_svg]:block" />
                    <label for="accent-purple"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <div style="color: rgb(127, 99, 204);" class="flex gap-1"><i class="fa fa-square"></i>
                            </div>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Ungu Caldera') }}</p>
                    </label>
                </div>
                <div>
                    <input type="radio" name="accent" value="green" wire:model.live="accent" id="accent-green"
                        class="peer hidden [&:checked_+_label_svg]:block" />
                    <label for="accent-green"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <div style="color: rgb(90, 160, 85);" class="flex gap-1"><i class="fa fa-square"></i>
                            </div>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Hijau alam') }}</p>
                    </label>
                </div>
                <div>
                    <input type="radio" name="accent" value="pink" wire:model.live="accent" id="accent-pink"
                        class="peer hidden [&:checked_+_label_svg]:block" />
                    <label for="accent-pink"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <div style="color: rgb(255, 105, 134);" class="flex gap-1"><i class="fa fa-square"></i>
                            </div>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Pink lembut') }}</p>
                    </label>
                </div>
                <div>
                    <input type="radio" name="accent" value="blue" wire:model.live="accent" id="accent-blue"
                        class="peer hidden [&:checked_+_label_svg]:block" />
                    <label for="accent-blue"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <div style="color: rgb(59, 138, 208);" class="flex gap-1"><i class="fa fa-square"></i>
                            </div>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Biru damai') }}</p>
                    </label>
                </div>
                <div>
                    <input type="radio" name="accent" value="teal" wire:model.live="accent" id="accent-teal"
                        class="peer hidden [&:checked_+_label_svg]:block" />
                    <label for="accent-teal"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <div style="color: rgb(22, 146, 146);" class="flex gap-1"><i class="fa fa-square"></i>
                            </div>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Hijau tenang') }}</p>
                    </label>
                </div>
                <div>
                    <input type="radio" name="accent" value="orange" wire:model.live="accent" id="accent-orange"
                        class="peer hidden [&:checked_+_label_svg]:block" />
                    <label for="accent-orange"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <div style="color: rgb(255, 121, 16);" class="flex gap-1"><i class="fa fa-square"></i>
                            </div>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Jingga sore') }}</p>
                    </label>
                </div>
                <div>
                    <input type="radio" name="accent" value="grey" wire:model.live="accent" id="accent-grey"
                        class="peer hidden [&:checked_+_label_svg]:block" />
                    <label for="accent-grey"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <div style="color: rgb(122, 122, 122);" class="flex gap-1"><i class="fa fa-square"></i>
                            </div>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Abu suram') }}</p>
                    </label>
                </div>
                <div>
                    <input type="radio" name="accent" value="brown" wire:model.live="accent" id="accent-brown"
                        class="peer hidden [&:checked_+_label_svg]:block" />
                    <label for="accent-brown"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <div style="color: rgb(181, 99, 0);" class="flex gap-1"><i class="fa fa-square"></i>
                            </div>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Cokelat pekat') }}</p>
                    </label>
                </div>
                <div>
                    <input type="radio" name="accent" value="yellow" wire:model.live="accent" id="accent-yellow"
                        class="peer hidden [&:checked_+_label_svg]:block" />
                    <label for="accent-yellow"
                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                        <div class="flex items-center justify-between text-2xl">
                            <div style="color: rgb(255, 193, 36);" class="flex gap-1"><i class="fa fa-square"></i>
                            </div>
                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="mt-1">{{ __('Kuning ceria') }}</p>
                    </label>
                </div>
            </fieldset>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow p-6 sm:rounded-lg mb-6">
            <h2 class="text-lg font-medium mb-3">
                {{ __('Lainnya') }}
            </h2>
            <fieldset>
                <x-toggle name="mblur" wire:model.live="mblur" :checked="$mblur ? true : false" >{{ __('Efek blur pada latar dialog') }}<x-text-button type="button"
                        class="ml-2" x-data="" x-on:click="$dispatch('open-modal', 'mblur-help')"><i
                            class="far fa-question-circle"></i></x-text-button>
                </x-toggle>
                <x-modal name="mblur-help">
                    <div class="p-6">
                        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Efek blur pada latar dialog') }}
                        </h2>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">
                            <p class="mt-3">
                                {{ __('Latar pada dialog melayang seperti ini akan diberi efek blur jika dinyalakan. Pastikan perangkatmu memiliki grafis yang mendukung.') }}
                            </p>
                            <p class="mt-3">
                                {{ __('Matikan pengaturan ini jika performa menurun.') }}
                            </p>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                {{ __('Paham') }}
                            </x-secondary-button>
                        </div>
                    </div>
                </x-modal>
            </fieldset>
        </div>
    </div>
</div>
