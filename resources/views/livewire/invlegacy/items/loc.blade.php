<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

use App\Models\InvLoc;
use App\Models\InvItem;

new class extends Component {

    public $inv_area_id;

    public $isForm = false;

    public $id;
    public $loc;
    public $qlocs = [];

    public function rules()
    {

        return [
            'loc' => ['nullable', 'alpha_dash', 'max:20'],
        ];
    }

    public function messages() 
    {
        return [
            'loc.alpha_dash' => __('Hanya huruf, angka, dan strip'),
            'loc.max' => __('Maksimal 20 karakter'),
        ];
    }

    #[On('updated')]
    public function mount()
    {
        $item = InvItem::find($this->id);
        if($item) {
            $this->loc = $item->loc();
        }
    }

    public function updatedLoc()
    {
        $qloc = '%'.$this->loc.'%';
        $qlocs = InvLoc::where('inv_area_id', $this->inv_area_id)
        ->where('name', 'LIKE', $qloc)
        ->orderBy('name')
        ->take(100)
        ->get()
        ->pluck('name');
        $this->qlocs = $qlocs->toArray();

        if ($this->isForm) {
            $this->dispatch('loc-applied', loc: $this->loc);
        }
    }

    public function apply()
    {
        if ($this->isForm) {
            $this->dispatch('loc-applied', loc: $this->loc);
        } else {
            $this->validate();
            $item = InvItem::find($this->id);
            if($item) {
                $item->updateLoc($this->loc);
                $this->js('window.dispatchEvent(escKey)'); 
                $this->js('notyf.success("'.__('Lokasi diperbarui').'")');
                $this->dispatch('updated');
            }
        }
    }

}

?>

<div @if(!$isForm) class="p-6" @endif>
    @if(!$isForm)
    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-3">
        {{ __('Edit lokasi') }}
    </h2>
    @endif
    <x-text-input-icon x-ref="input" wire:model.live="loc" icon="fa fa-fw fa-map-marker-alt" id="loc" list="qlocs"
    type="text" placeholder="{{ __('Lokasi') }}" />
    <datalist id="qlocs">
        @if(count($qlocs))
            @foreach($qlocs as $qloc)
                <option wire:key="{{ 'qloc'.$loop->index }}" value="{{ $qloc }}">
            @endforeach
        @endif
    </datalist>
    @error('loc')
        <x-input-error messages="{{ $message }}" class="mt-2" />
    @enderror
    @if(!$isForm)
    <div class="flex">
        <x-primary-button type="button" wire:click="apply" class="ml-auto mt-4">{{__('Perbarui')}}</x-primary-button>
    </div>
    @endif
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="apply"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target="apply" class="hidden"></x-spinner>
</div>
