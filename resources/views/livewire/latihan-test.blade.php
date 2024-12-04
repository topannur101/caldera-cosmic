<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new  #[Layout('layouts.app')] 
class extends Component {

    public int $angka = 0;
    public string $nama = 'Test';

    public function tambahin()
    {
        $this->angka = $this->angka + 1;
    }

    public function kurangin()
    {
        $this->angka = $this->angka - 1;

    }

    public function yakinGanti()
    {

    }

}; ?>


<div>

    <div class="flex p-6 gap-3">
        <x-secondary-button type="button" wire:click="kurangin">- Kurang</x-secondary-button>
        <span>{{ $angka }}</span>
        <x-secondary-button type="button" wire:click="tambahin">+ Tambah</x-secondary-button>
    </div>

    <div>
        {{ $nama }}
        <x-secondary-button type="button" wire:click="yakinGanti">Ganti nama</x-secondary-button>
    </div>
    <div class="mx-auto w-32">
        <x-text-input wire:model="nama"></x-text-input>
    </div>
    <span class="bg-red-500">
        {{ __('Yang mau diterjemahkab')}}
    </span>
</div>
