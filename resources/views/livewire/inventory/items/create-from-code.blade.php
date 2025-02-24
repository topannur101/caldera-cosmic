<?php

use Livewire\Volt\Component;
use App\Models\InvItem;

new class extends Component
{
    public string $code = '';

    public array $areas = [];

    public int $area_id = 0;

    public function placeholder()
    {
        return view('livewire.layout.modal-placeholder');
    }

    public function rules()
    {
        return [
            'code'      => ['alpha_dash', 'size:11'],
            'area_id'   => ['required', 'exists:inv_areas,id'],
        ];
    }

    public function first()
    {
        $this->code = strtoupper(trim($this->code));
        $this->validate();
        $this->js('$dispatch("close")');

        $item = $this->code ? $item = InvItem::where('inv_area_id', $this->area_id)->where('code', $this->code)->first() : '';

        if ($item)
        {
            $this->redirect(route('inventory.items.show', ['id' => $item->id]), navigate: true);
        } else {
            $this->redirect(route('inventory.items.create', [ 'area_id' => $this->area_id, 'code' => $this->code ]), navigate: true);
        }        

    }
}

?>

<div>
    <form wire:submit="first" class="p-6">
        <div class="flex justify-between items-center text-lg mb-6 font-medium text-neutral-900 dark:text-neutral-100">
            <h2>
                {{ __('Buat barang') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
        <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
            {{ __('Caldera akan mencari barang dengan area dan kode item yang kamu tentukan di bawah. Bila tidak ditemukan, kamu akan diarahkan ke halaman buat barang.') }}
        </p>
        <div class="mt-6">
            <x-text-input wire:model="code" class="mt-4" type="text" placeholder="{{ __('Kode item') }}" />
            <div wire:key="error-code">
                @error('code')
                    <x-input-error messages="{{ $message }}" class="mt-2" />
                @enderror
            </div>
            <x-select wire:model="area_id" class="w-full mt-4">
                <option value=""></option>
                @foreach ($areas as $area)
                    <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
                @endforeach
            </x-select>
            <div wire:key="error-area_id">
                @error('area_id')
                    <x-input-error messages="{{ $message }}" class="mt-2" />
                @enderror
            </div>
        </div>
        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit" class="ml-3">
                {{ __('Lanjut') }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
