<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

use App\Models\InvCurr;
use Illuminate\Support\Facades\Gate;

new #[Layout('layouts.app')] class extends Component {
    
    public int $id = 0;

    public array $curr = [
        'id'        => 0,
        'name'      => '',
        'rate'      => 1,
        'is_active' => false
    ];

    public string $curr_main = '';

    public function mount()
    {
        $this->curr_main = InvCurr::find(1)?->name ?? '';
    }

    public function rules()
    {
        return [
            'curr.rate'     => ['required', 'gt:0', 'lt:1000000'],
            'curr.is_active' => ['required', 'boolean']
        ];
    }

    #[On('curr-edit')]
    public function loadCurr(int $id)
    {
        $curr = InvCurr::find($id);
        if ($curr) {
            $this->curr['id']           = $curr->id;
            $this->curr['name']         = $curr->name;
            $this->curr['rate']         = $curr->rate;
            $this->curr['is_active']    = (bool) $curr->is_active;
            $this->resetValidation();

        } else {
            $this->handleNotFound();
        }
    }


    public function save()
    {
        Gate::authorize('superuser');
        $this->curr['name'] = strtoupper(trim($this->curr['name']));
        $this->validate();

        $curr = InvCurr::find($this->curr['id']);
        if ($curr->id == 1) {
            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Mata uang utama tidak dapat diedit') . '", { type: "danger" })');
            $this->dispatch('updated');

        } elseif ($curr) {
            $curr->rate         = $this->curr['rate'];
            $curr->is_active    = $this->curr['is_active'];
            $curr->update();

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Mata uang diperbarui') . '", { type: "success" })');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
            $this->customReset();
        }
    }


    public function customReset()
    {
        $this->reset(['id', 'curr']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }
};

?>
<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Mata uang') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <div class="grid grid-cols-1 gap-y-6 mt-6">
            <div>
                <label for="curr-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                <x-text-input-t class="px-3" id="curr-name" wire:model="curr.name" type="text" disabled />
                @error('curr.name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <div class="flex items-baseline">
                    <label for="curr-rate" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nilai tukar') }}</label>
                    @if($curr_main)
                        <div 
                            x-data="{
                                tooltipVisible: false,
                                tooltipText: 'Terhadap {{ $curr_main }}',
                                tooltipArrow: true,
                                tooltipPosition: 'top',
                            }"
                            x-init="$refs.content.addEventListener('mouseenter', () => { tooltipVisible = true; }); $refs.content.addEventListener('mouseleave', () => { tooltipVisible = false; });"
                            class="relative">                        
                            <div x-ref="tooltip" x-show="tooltipVisible" :class="{ 'top-0 left-1/2 -translate-x-1/2 -mt-0.5 -translate-y-full' : tooltipPosition == 'top', 'top-1/2 -translate-y-1/2 -ml-0.5 left-0 -translate-x-full' : tooltipPosition == 'left', 'bottom-0 left-1/2 -translate-x-1/2 -mb-0.5 translate-y-full' : tooltipPosition == 'bottom', 'top-1/2 -translate-y-1/2 -mr-0.5 right-0 translate-x-full' : tooltipPosition == 'right' }" class="absolute w-auto text-sm" x-cloak>
                                <div x-show="tooltipVisible" x-transition class="relative px-2 py-1 text-white bg-black rounded bg-opacity-90">
                                    <p x-text="tooltipText" class="flex-shrink-0 block text-xs whitespace-nowrap"></p>
                                    <div x-ref="tooltipArrow" x-show="tooltipArrow" :class="{ 'bottom-0 -translate-x-1/2 left-1/2 w-2.5 translate-y-full mb-px' : tooltipPosition == 'top', 'right-0 -translate-y-1/2 top-1/2 h-2.5 -mt-px translate-x-full' : tooltipPosition == 'left', 'top-0 -translate-x-1/2 left-1/2 w-2.5 -translate-y-full' : tooltipPosition == 'bottom', 'left-0 -translate-y-1/2 top-1/2 h-2.5 -mt-px -translate-x-full' : tooltipPosition == 'right' }" class="absolute inline-flex items-center justify-center overflow-hidden">
                                        <div :class="{ 'origin-top-left -rotate-45' : tooltipPosition == 'top', 'origin-top-left rotate-45' : tooltipPosition == 'left', 'origin-bottom-left rotate-45' : tooltipPosition == 'bottom', 'origin-top-right -rotate-45' : tooltipPosition == 'right' }" class="w-1.5 h-1.5 transform bg-black bg-opacity-90"></div>
                                    </div>
                                </div>
                            </div>                   
                            <div x-ref="content" class="text-sm cursor-pointer text-neutral-500"><i class="fa far fa-question-circle"></i>     </div>
                        </div>
                    @endif
                </div>
                <x-text-input id="curr-rate" wire:model="curr.rate" type="number" step="0.01" />
                @error('curr.rate')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <x-toggle id="curr-is_active" wire:model="curr.is_active">{{ __('Aktif') }}</x-toggle>
            </div>
        </div>  
        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
