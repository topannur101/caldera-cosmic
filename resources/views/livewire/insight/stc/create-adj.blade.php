<?php

use Livewire\Volt\Component;

use App\Models\InsStcMachine;

new class extends Component {

    public function with(): array
    {
        return [
            'machines' => InsStcMachine::orderBy('line')->get()
        ];
    }

};
?>

<div>
    <h1 class="grow text-2xl text-neutral-900 dark:text-neutral-100 px-8">{{ __('Penyetelan') }}</h1>
    <div wire:key="modals">
        <x-modal name="save-only">
            <div class="p-6">
                Simpan saja
                Angka SV prediksi hanya akan di simpan sebagai catatan dan tidak akan di kirimkan ke HMI.

                Input form: Keterangan
            </div>
        </x-modal>
        <x-modal name="send">
            <div class="p-6">
                Kirim ke HMI
                Angka SV prediksi akan di kirimkan ke HMI dan kamu harus menekan tombol Terapkan di HMI agar SV dapat berubah.

                Input form: Keterangan
            </div>
        </x-modal>
    </div>
    <div class="w-full my-8">
        <div class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-6">
            <div class="grid grid-cols-1 gap-y-6">
                <div>
                    <div class="grid grid-cols-2 gap-x-3">
                        <div>
                            <label for="adj-machine_id"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                            <x-select class="w-full" id="adj-machine_id" wire:model="machine_id">
                                <option value=""></option>
                                @foreach ($machines as $machine)
                                    <option value="{{ $machine->id }}">{{ $machine->line }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div>
                            <label for="adj-position"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                            <x-select class="w-full" id="adj-position" wire:model="position">
                                <option value=""></option>
                                    <option value="upper">{{ __('Atas') }}</option>
                                    <option value="lower">{{ __('Bawah') }}</option>
                            </x-select>
                        </div>
                    </div>
                    @error('machine_id')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                    @error('position')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div>
                    <label class="flex gap-x-2 px-3 mb-2 uppercase text-xs text-neutral-500">
                        <div>{{ __('Pembacaan mesin') }}</div>
                        <div>•</div>
                        <div>5 menit yang lalu</div>
                    </label>
                    <div class="grid grid-cols-9 text-center gap-x-3">
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">S</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">1</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">2</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">3</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">4</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">5</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">6</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">7</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">8</div>

                        <div>SV</div>    
                        <div>01</div>
                        <div>02</div>
                        <div>03</div>
                        <div>04</div>
                        <div>05</div>
                        <div>06</div>
                        <div>07</div>
                        <div>08</div>

                        <div>PV</div>
                        <div>01</div>
                        <div>02</div>
                        <div>03</div>
                        <div>04</div>
                        <div>05</div>
                        <div>06</div>
                        <div>07</div>
                        <div>08</div>
                    </div>               
                </div>
                <div>
                    <label class="flex gap-x-2 px-3 mb-2 uppercase text-xs text-neutral-500">
                        <div>{{ __('Pembacaan alat') }}</div>
                        <div>•</div>
                        <div>3 jam yang lalu</div>
                    </label>
                    <div class="grid grid-cols-9 text-center gap-x-3">
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">S</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">1</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">2</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">3</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">4</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">5</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">6</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">7</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">8</div>

                        <div>HB</div>    
                        <div>01</div>
                        <div>02</div>
                        <div>03</div>
                        <div>04</div>
                        <div>05</div>
                        <div>06</div>
                        <div>07</div>
                        <div>08</div>
{{-- 
                        <div>SV</div>
                        <div>01</div>
                        <div>02</div>
                        <div>03</div>
                        <div>04</div>
                        <div>05</div>
                        <div>06</div>
                        <div>07</div>
                        <div>08</div> --}}
                    </div>               
                </div>
                <div>
                    <label for="adj-formula_id"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('SV Prediksi') }}</label>
                    <x-select class="w-full" id="adj-formula_id" wire:model="formula_id">
                        <option value=""></option>
                        <option value="4">{{ __('Formula versi 4') }}</option>
                    </x-select>
                    @error('formula_id')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div>
                    <div class="grid grid-cols-9 text-center gap-x-3">
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">S</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">1</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">2</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">3</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">4</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">5</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">6</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">7</div>
                        <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">8</div>
    
                        <div>SVP</div>    
                        <div>01</div>
                        <div>02</div>
                        <div>03</div>
                        <div>04</div>
                        <div>05</div>
                        <div>06</div>
                        <div>07</div>
                        <div>08</div>
                    </div>               
                </div>
            </div>
            <div class="flex gap-x-2 mt-6">
                <div></div>
                <div class="grow"></div>
                <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'save-only')">{{ __('Simpan saja') }}</x-secondary-button>
                <x-primary-button type="button" x-on:click="$dispatch('open-modal', 'send')">{{ __('Kirim') }}</x-primary-button>
            </div>
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
        </div>
    </div>
</div>