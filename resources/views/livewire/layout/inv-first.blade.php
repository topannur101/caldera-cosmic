<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Rule;

use App\Models\User;
use App\Models\InvArea;
use App\Models\InvItem;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $code;
    public $areas;

    #[Rule('required')]
    public $inv_area_id;

    public function placeholder()
    {
        return view('livewire.layout.modal-placeholder');
    }

    public function mount()
    {
        $user = User::find(Auth::user()->id);
        $this->areas = InvArea::whereIn('id', $user->invAreaIdsItemCreate())->get();
    }

    public function first()
    {
        $this->code = strtoupper(trim($this->code));
        $this->validate();

        $item = $this->code ? $item = InvItem::where('inv_area_id', $this->inv_area_id)->where('code', $this->code)->first() : '';

        if ($item)
        {
            return redirect(route('invlegacy.items.show', [ 'id' => $item->id ]));
        } else {
            return redirect(route('invlegacy.items.create', [ 'inv_area_id' => $this->inv_area_id, 'code' => $this->code ]));
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
            <x-select wire:model="inv_area_id" class="w-full mt-4">
                <option value=""></option>
                @foreach ($areas as $area)
                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                @endforeach
            </x-select>
            <div wire:key="error-inv_area_id">
                @error('inv_area_id')
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
