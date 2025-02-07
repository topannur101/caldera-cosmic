<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use App\Models\Pref;

new class extends Component
{
    public string $lang = '';

    public function mount()
    {
        $accountPref = Pref::where('user_id', Auth::user()->id)->where('name', 'account')->first();
        $data = $accountPref ? json_decode($accountPref->data, true) : [];
        $this->lang = isset($data['lang']) ? $data['lang'] : 'id';
    }

    public function updateLang(): void
    {
        try {
            $validated = $this->validate([
                'lang' => ['required', Rule::in(['id', 'en', 'vi', 'ko'])]
            ]);
            $pref = Pref::firstOrCreate(
                ['user_id' => Auth::user()->id, 'name' => 'account'],
                ['data' => json_encode([])]
            );
            $existingData = json_decode($pref->data, true);
            $existingData['lang'] = $validated['lang'];

            App::setLocale($validated['lang']);
            session()->put('lang', $validated['lang']);

            $pref->update(['data' => json_encode($existingData)]);

            $this->js('$dispatch("close")');
            $this->redirectIntended(default: route('account', absolute: false), navigate: false);

        } catch (\Throwable $th) {
            $this->js('toast("' . __('Terjadi kesalahan pada server') . '", { type: "danger" })');
            $this->reset('lang');
        }
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Bahasa') }}
        </h2>
    </header>

    <form wire:submit="updateLang" class="mt-6 text-neutral-600 dark:text-neutral-400">
        <div class="mb-6">
            <x-radio wire:model="lang" id="lang-id" name="lang" value="id">Bahasa Indonesia</x-radio>
            <x-radio wire:model="lang" id="lang-en" name="lang" value="en">English (US)</x-radio>
            <x-radio wire:model="lang" id="lang-vi" name="lang" value="vi">Tiếng Việt</x-radio>
            <x-radio wire:model="lang" id="lang-ko" name="lang" value="ko">한국</x-radio>
        </div>
        <div class="flex items-center justify-end gap-4">
            <x-primary-button type="submit">{{ __('Simpan') }}</x-primary-button>
{{-- 
            <x-action-message class="me-3" on="password-updated">
                {{ __('Tersimpan.') }}
            </x-action-message> --}}
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</section>
