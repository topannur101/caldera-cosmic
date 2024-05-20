<?php

use Livewire\Volt\Component;
use Illuminate\Validation\Rule;

new class extends Component {
   
    public string $lang;
    public string $route;
    public bool $small;

    public function mount()
    {
        $this->lang = session()->get('lang') ?? 'en';
    }

    public function updated($property)
    {
        if ($property == 'lang') {
            try {
               $validated = $this->validate([
                  'lang' => ['required', Rule::in(['id', 'en', 'vi', 'ko'])],
               ]);
         } catch (ValidationException $e) {
               $this->reset('lang');
               throw $e;
         }

         App::setLocale($validated['lang']);
         session()->put('lang', $validated['lang']);
        }
        $this->redirectIntended(default: $this->route, navigate: true);
   }
}; ?>

<div>
    <x-select wire:model.live="lang" class="{{ $small ? 'text-xs py-1' : ''}}">
        <option value="id">Bahasa Indonesia</option>
        <option value="en">English (US)</option>
        <option value="vi">Tiếng Việt (AI)</option>
        <option value="ko">한국 (AI)</option>
    </x-select>
</div>
