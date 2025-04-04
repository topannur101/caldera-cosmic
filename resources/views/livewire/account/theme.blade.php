<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\Pref;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] class extends Component {
    
    public string $media_bg     = '';

    public string $pref_bg      = '';
    public string $pref_accent  = '';
    public string $pref_mblur   = '';
    public string $pref_pattern = '';

    public string $preset       = '';

    #[Url]
    public string $mode         = 'basic-color';

    public function mount()
    {
        $accountPref = Pref::where('user_id', Auth::user()->id)
            ->where('name', 'account')
            ->first();

        $data               = $accountPref ? json_decode($accountPref->data, true) : [];
        $this->pref_bg      = isset($data['bg'])        ? $data['bg']               : 'auto';
        $this->pref_accent  = isset($data['accent'])    ? $data['accent']           : 'purple';
        $this->pref_mblur   = isset($data['mblur'])     ? (bool) $data['mblur']     : false;
        $this->pref_pattern = isset($data['pattern'])   ? $data['pattern']          : '';

        if ($this->pref_pattern) {
            $this->mode = 'patterned';
            $this->preset = $this->pref_pattern;
        }

        $this->js('$wire.media_bg = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";');
    }
 
    public function updated($property)
    {
        $this->commitPref($property);
    }

    private function commitPreset($preset)
    {
        switch($preset) {
            case 'anggun':
                $this->pref_bg      = 'light';
                $this->pref_accent  = 'pink';
                $this->pref_pattern = 'anggun';
                break;

            case 'manis':
                $this->pref_bg      = 'light';
                $this->pref_accent  = 'orange';
                $this->pref_pattern = 'manis';
                break;

            case 'syahdu':
                $this->pref_bg      = 'light';
                $this->pref_accent  = 'green';
                $this->pref_pattern = 'syahdu';
                break;

            case 'lembut':
                $this->pref_bg      = 'light';
                $this->pref_accent  = 'brown';
                $this->pref_pattern = 'lembut';
                break;

            case 'bising':
                $this->pref_bg      = 'light';
                $this->pref_accent  = 'grey';
                $this->pref_pattern = 'bising';
                break;

            case 'langit':
                $this->pref_bg      = 'light';
                $this->pref_accent  = 'blue';
                $this->pref_pattern = 'langit';
                break;

            case 'garang':
                $this->pref_bg      = 'dark';
                $this->pref_accent  = 'yellow';
                $this->pref_pattern = 'garang';
                break;

            case 'bobotoh':
                $this->pref_bg      = 'dark';
                $this->pref_accent  = 'blue';
                $this->pref_pattern = 'bobotoh';
                break;

            case 'melankolis':
                $this->pref_bg      = 'dark';
                $this->pref_accent  = 'teal';
                $this->pref_pattern = 'melankolis';
                break;

            case 'kusut':
                $this->pref_bg      = 'dark';
                $this->pref_accent  = 'green';
                $this->pref_pattern = 'kusut';
                break;

            case 'asimilasi':
                $this->pref_bg      = 'dark';
                $this->pref_accent  = 'pink';
                $this->pref_pattern = 'asimilasi';
                break;

            case 'spektrum':
                $this->pref_bg      = 'dark';
                $this->pref_accent  = 'purple';
                $this->pref_pattern = 'spektrum';
                break;
        }

        $this->commitPref('pref_bg');
        $this->commitPref('pref_accent');
        $this->commitPref('pref_pattern');
    }

    private function commitPref($property)
    {
        switch ($property) {

            case 'mode':
                if ($this->mode == 'basic-color') {
                    $this->reset(['pref_pattern', 'preset']);
                    $this->commitPref('pref_pattern');
                };

                break;

            case 'pref_bg':
                $validated = $this->validate([
                    'pref_bg' => ['required', Rule::in(['auto', 'dark', 'light'])]
                ]);
    
                $pref = Pref::firstOrCreate(
                    ['user_id' => Auth::user()->id, 'name' => 'account'],
                    ['data' => json_encode([])]
                );
    
                $prefData = json_decode($pref->data, true);
                $prefData['bg'] = $validated['pref_bg'];
    
                $pref->update(['data' => json_encode($prefData)]);
    
                $bg = $this->pref_bg == 'auto' 
                    ? ($this->media_bg == 'dark' ? 'dark' : 'light') 
                    : ($this->pref_bg == 'dark' ? 'dark' : 'light');
    
                session(['bg' => $bg]);
    
                $this->js("localStorage.setItem('theme', '{$bg}');");
                $this->js("calderaSetTheme();");

                break;
            
            case 'pref_accent':
                $validated = $this->validate([
                    'pref_accent' => [
                        'required', 
                        Rule::in([
                            'purple', 'green', 'pink', 
                            'blue', 'teal', 'orange', 
                            'grey', 'brown', 'yellow'
                        ])
                    ]
                ]);
    
                $pref = Pref::firstOrCreate(
                    ['user_id' => Auth::user()->id, 'name' => 'account'],
                    ['data' => json_encode([])]
                );
    
                $prefData = json_decode($pref->data, true);
                $prefData['accent'] = $validated['pref_accent'];
    
                $pref->update(['data' => json_encode($prefData)]);
    
                session(['accent' => $this->pref_accent]);
    
                $this->js("const body = document.body;
                const classes = ['purple', 'green', 'pink', 'blue', 'teal', 'orange', 'grey', 'brown', 'yellow'];
                classes.forEach((cls) => { body.classList.remove(cls); });
                body.classList.add('" . $this->pref_accent . "');");
                break;

            case 'pref_mblur':
                $mblur = (bool) $this->pref_mblur;

                $pref = Pref::firstOrCreate(
                    ['user_id' => Auth::user()->id, 'name' => 'account'],
                    ['data' => json_encode([])]
                );
                
                $prefData = json_decode($pref->data, true);
                $prefData['mblur'] = $mblur;

                $pref->update(['data' => json_encode($prefData)]);

                session(['mblur' => $this->pref_mblur]);
                break;

            case 'pref_pattern':
                $validated = $this->validate([
                    'pref_pattern' => [
                        'nullable', 
                        Rule::in([
                            'anggun', 'manis', 'syahdu',
                            'lembut', 'bising', 'langit',
                            'garang', 'bobotoh', 'melankolis',
                            'kusut', 'asimilasi', 'spektrum'
                        ])
                    ]
                ]);
    
                $pref = Pref::firstOrCreate(
                    ['user_id' => Auth::user()->id, 'name' => 'account'],
                    ['data' => json_encode([])]
                );
    
                $prefData = json_decode($pref->data, true);
                $prefData['pattern'] = $validated['pref_pattern'];
    
                $pref->update(['data' => json_encode($prefData)]);
    
                session(['pattern' => $this->pref_pattern]);

                $this->js("const body = document.body;
                const classes = ['anggun', 'manis', 'syahdu', 'lembut', 'bising', 'langit', 'garang', 'bobotoh', 'melankolis', 'kusut', 'asimilasi', 'spektrum'];
                classes.forEach((cls) => { body.classList.remove(cls); });");

                if ($this->pref_pattern) {
                    $this->js("const body = document.body;
                    body.classList.add('" . $this->pref_pattern . "');");                 
                }
                break;

            case 'preset':
                $validated = $this->validate([
                    'preset' => [
                        'nullable', 
                        Rule::in([
                            'anggun', 'manis', 'syahdu',
                            'lembut', 'bising', 'langit',
                            'garang', 'bobotoh', 'melankolis',
                            'kusut', 'asimilasi', 'spektrum'
                        ])
                    ]
                ]);

                $this->commitPreset($this->preset);
                break;
    
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
    <div class="flex justify-center mb-6">
        <div class="btn-group">
            <x-radio-button wire:model.live="mode" value="basic-color" name="mode" id="mode-basic-color">
                <div class="my-auto">{{ __('Warna dasar') }}</div>
            </x-radio-button>
            <x-radio-button wire:model.live="mode" value="patterned" name="mode" id="mode-patterned">
                <div class="my-auto">{{ __('Dengan corak') }}</div>
            </x-radio-button>
        </div>
    </div>
    <div>
        @switch($mode)
            @case('basic-color')
                <div class="bg-white dark:bg-neutral-800 shadow p-6 sm:rounded-lg mb-6">
                    <h2 class="text-lg font-medium mb-3">
                        {{ __('Latar') }}
                    </h2>
                    <fieldset class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-4">
                        <div>
                            <input type="radio" id="bg-auto" wire:model.live="pref_bg"
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
                            <input type="radio" id="bg-light" wire:model.live="pref_bg"
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
                            <input type="radio" id="bg-dark" wire:model.live="pref_bg"
                                class="peer hidden [&:checked_+_label_svg]:block" value="dark" />
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
                            <input type="radio" value="purple" wire:model.live="pref_accent" id="accent-purple"
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
                            <input type="radio" value="green" wire:model.live="pref_accent" id="accent-green"
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
                            <input type="radio" value="pink" wire:model.live="pref_accent" id="accent-pink"
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
                            <input type="radio" value="blue" wire:model.live="pref_accent" id="accent-blue"
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
                            <input type="radio" value="teal" wire:model.live="pref_accent" id="accent-teal"
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
                            <input type="radio" value="orange" wire:model.live="pref_accent" id="accent-orange"
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
                            <input type="radio" value="grey" wire:model.live="pref_accent" id="accent-grey"
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
                            <input type="radio" value="brown" wire:model.live="pref_accent" id="accent-brown"
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
                            <input type="radio" value="yellow" wire:model.live="pref_accent" id="accent-yellow"
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
                @break

            @case('patterned')
            <div class="p-6">
                <h2 class="text-lg font-medium mb-3">
                    <i class="text-neutral-500 fa fa-sun mr-2"></i>{{ __('Corak cerah') }}
                </h2>
                <fieldset class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-4">
                    <div>
                        <input type="radio" value="anggun" wire:model.live="preset" id="preset-anggun"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-anggun" style="background-image:url('/storage/preset/anggun-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div class="flex gap-1 text-pink-600"><i class="fa fa-square"></i>
                                </div>
                                <svg class="hidden h-6 w-6 text-pink-600" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-neutral-800">Anggun</p>
                        </label>
                    </div>
                    <div>
                        <input type="radio" value="manis" wire:model.live="preset" id="preset-manis"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-manis" style="background-image:url('/storage/preset/manis-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div class="flex gap-1 text-orange-600"><i class="fa fa-square"></i>
                                </div>
                                <svg class="hidden h-6 w-6 text-orange-600" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-neutral-800">Manis</p>
                        </label>
                    </div>
                    <div>
                        <input type="radio" value="syahdu" wire:model.live="preset" id="preset-syahdu"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-syahdu" style="background-image:url('/storage/preset/syahdu-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div class="flex gap-1 text-green-600"><i class="fa fa-square"></i>
                                </div>
                                <svg class="hidden h-6 w-6 text-green-600" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-neutral-800">Syahdu</p>
                        </label>
                    </div>
                    <div>
                        <input type="radio" value="lembut" wire:model.live="preset" id="preset-lembut"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-lembut" style="background-image:url('/storage/preset/lembut-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div style="color: rgb(181, 99, 0);" class="flex gap-1"><i class="fa fa-square"></i>
                                </div>
                                <svg style="color: rgb(181, 99, 0);" class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-neutral-800">Lembut</p>
                        </label>
                    </div>
                    <div>
                        <input type="radio" value="bising" wire:model.live="preset" id="preset-bising"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-bising" style="background-image:url('/storage/preset/bising-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div style="color: rgb(122, 122, 122);" class="flex gap-1"><i class="fa fa-square"></i>
                                </div>
                                <svg style="color: rgb(122, 122, 122);" class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-neutral-800">Bising</p>
                        </label>
                    </div>
                    <div>
                        <input type="radio" value="langit" wire:model.live="preset" id="preset-langit"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-langit" style="background-image:url('/storage/preset/langit-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div class="flex gap-1 text-blue-600"><i class="fa fa-square"></i>
                                </div>
                                <svg class="hidden h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-neutral-800">Langit</p>
                        </label>
                    </div>
                </fieldset>
            </div>     
            <div class="p-6 mb-6">
                <h2 class="text-lg font-medium mb-3">
                    <i class="text-neutral-500 fa fa-moon mr-2"></i>{{ __('Corak gelap') }}
                </h2>
                <fieldset class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-4">
                    <div>
                        <input type="radio" value="garang" wire:model.live="preset" id="preset-garang"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-garang" style="background-image:url('/storage/preset/garang-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div class="flex gap-1 text-yellow-600"><i class="fa fa-square"></i>
                                </div>
                                <svg class="hidden h-6 w-6 text-yellow-600" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-white">Garang</p>
                        </label>
                    </div>
                    <div>
                        <input type="radio" value="bobotoh" wire:model.live="preset" id="preset-bobotoh"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-bobotoh" style="background-image:url('/storage/preset/bobotoh-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div class="flex gap-1 text-blue-600"><i class="fa fa-square"></i>
                                </div>
                                <svg class="hidden h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-white">Bobotoh</p>
                        </label>
                    </div>
                    <div>
                        <input type="radio" value="melankolis" wire:model.live="preset" id="preset-melankolis"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-melankolis" style="background-image:url('/storage/preset/melankolis-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div class="flex gap-1 text-teal-600"><i class="fa fa-square"></i>
                                </div>
                                <svg class="hidden h-6 w-6 text-teal-600" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-white">Melankolis</p>
                        </label>
                    </div>
                    <div>
                        <input type="radio" value="kusut" wire:model.live="preset" id="preset-kusut"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-kusut" style="background-image:url('/storage/preset/kusut-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div style="color: rgb(90, 160, 85);" class="flex gap-1"><i class="fa fa-square"></i>
                                </div>
                                <svg style="color: rgb(90, 160, 85);" class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-white">Kusut</p>
                        </label>
                    </div>
                    <div>
                        <input type="radio" value="asimilasi" wire:model.live="preset" id="preset-asimilasi"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-asimilasi" style="background-image:url('/storage/preset/asimilasi-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div style="color: rgb(255, 105, 134);" class="flex gap-1"><i class="fa fa-square"></i>
                                </div>
                                <svg style="color: rgb(255, 105, 134);" class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-white">Asimilasi</p>
                        </label>
                    </div>
                    <div>
                        <input type="radio" value="spektrum" wire:model.live="preset" id="preset-spektrum"
                            class="peer hidden [&:checked_+_label_svg]:block" />
                        <label for="preset-spektrum" style="background-image:url('/storage/preset/spektrum-thumbnail.jpg');background-size:cover"
                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                            <div class="flex items-center justify-between text-2xl">
                                <div style="color: rgb(127, 99, 204);" class="flex gap-1"><i class="fa fa-square"></i>
                                </div>
                                <svg style="color: rgb(127, 99, 204);" class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="mt-1 text-white">Spektrum</p>
                        </label>
                    </div>
                </fieldset>
            </div>    
            @break
        @endswitch

        <div class="bg-white dark:bg-neutral-800 shadow p-6 sm:rounded-lg mb-6">
            <h2 class="text-lg font-medium mb-3">
                {{ __('Lainnya') }}
            </h2>
            <fieldset>
                <x-toggle wire:model.live="pref_mblur" :checked="$pref_mblur ? true : false" >{{ __('Efek blur pada latar dialog') }}<x-text-button type="button"
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
                            <x-primary-button type="button" x-on:click="$dispatch('close')">
                                {{ __('Paham') }}
                            </x-primary-button>
                        </div>
                    </div>
                </x-modal>
            </fieldset>
        </div>
    </div>
</div>
