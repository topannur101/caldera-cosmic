<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

use App\Models\LatihanKelas;

new  #[Layout('layouts.app')] 
class extends Component {

    public string $nama = '';
    public int $lantai;

    public function simpan()
    {
        LatihanKelas::create([
            'nama' => $this->nama,
            'lantai' => $this->lantai
        ]);              
    }

}; ?>


<div class="text-white">
    <form wire:submit.prevent="simpan" class="mx-auto max-w-md  p-6">
        <div class="mb-6">Buat kelas baru</div>
        <div>Nama kelas: {{ $nama }}</div>
        <input type="text" class="bg-neutral-500" wire:model.live="nama" />
        <div>Lantai: {{ $lantai }}</div>
        <select class="bg-neutral-500" wire:model.live="lantai">
            <option value=""></option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
        </select>
        <div class="mt-6">
            <button type="submit" class="bg-neutral-500"><i class="fa fa-floppy-disk mr-2"></i>Simpan</button>
        </div>
    </form>
</div>


