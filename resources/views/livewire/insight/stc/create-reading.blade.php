<?php

use Livewire\Volt\Component;

new class extends Component {

   public array $machines = [];

   public array $d_sum = [

      'started_at'        => '',
      'ended_at'          => '',
      'speed'             => '',
      'sequence'          => '',
      'position'          => '',
      'sv_values'         => [],
      'formula_id'        => '',
      'sv_used'           => '',
      'is_applied'        => '',
      'target_values'     => [],
      'hb_values'         => [],
      'svp_values'        => [],
      
      // relationship
      'user' => [
          'photo'         => '',
          'name'          => '',
          'emp_id'        => ''
      ],
      'ins_stc_machine'   => [
          'line'          => ''
      ],
      'ins_stc_device'    => [
          'code'          => '',
          'name'          => '',
      ],
      'ins_stc_d_logs'    => [],
      
      // calculated
      'duration'              => '',
      'latency'               => '',
      'adjustment_friendly'   => '',
      'integrity_friendly'    => '',
  ];

}

?>

<div class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
   <div class="grid grid-cols-1 sm:grid-cols-3 divide-x divide-neutral-200 dark:text-white dark:divide-neutral-700">
      <div class="p-6">
         <h1 class="grow text-xl text-neutral-900 dark:text-neutral-100 mb-6">{{ __('Mesin') }}</h1>
         <div class="grid grid-cols-2 gap-x-3 mb-6">
            <div>
               <label for="d-log-sequence"
                     class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Urutan') }}</label>
               <x-select class="w-full" id="d-log-sequence" wire:model="sequence">
                     <option value=""></option>
                     <option value="1">1</option>
                     <option value="2">2</option>
               </x-select>
            </div>
            <div>
               <label for="d-log-speed"
                     class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kecepatan') }}</label>
               <x-text-input-suffix suffix="RPM" id="d-log-speed" wire:model="speed" type="number"
                     step=".01" autocomplete="off" />
            </div>
         </div>
         <div class="grid grid-cols-2 gap-x-3 mb-6">
            <div>
               <label for="d-log-machine_id"
                     class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
               <x-select class="w-full" id="d-log-machine_id" wire:model.live="machine_id">
                     <option value="0"></option>
                     @foreach ($machines as $machine)
                        <option value="{{ $machine->id }}">{{ $machine->line }}</option>
                     @endforeach
               </x-select>
            </div>
            <div>
               <label for="d-log-position"
                     class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
               <x-select class="w-full" id="d-log-position" wire:model.live="position">
                     <option value=""></option>
                     <option value="upper">{{ __('Atas') }}</option>
                     <option value="lower">{{ __('Bawah') }}</option>
               </x-select>
            </div>
         </div>
         <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('SV') }}</label>
         <div class="grid grid-cols-8">
            <x-text-input-t class="text-center" placeholder="0" wire:model="test.tc10_low" />
            <x-text-input-t class="text-center" placeholder="0" wire:model="test.tc10_low" />
            <x-text-input-t class="text-center" placeholder="0" wire:model="test.tc10_low" />
            <x-text-input-t class="text-center" placeholder="0" wire:model="test.tc10_low" />
            <x-text-input-t class="text-center" placeholder="0" wire:model="test.tc10_low" />
            <x-text-input-t class="text-center" placeholder="0" wire:model="test.tc10_low" />
            <x-text-input-t class="text-center" placeholder="0" wire:model="test.tc10_low" />
            <x-text-input-t class="text-center" placeholder="0" wire:model="test.tc10_low" />
         </div>
      </div>
      <div class="p-6">
         <div class="flex justify-between">
            <h1 class="grow text-xl text-neutral-900 dark:text-neutral-100 mb-6">{{ __('Alat ukur') }}</h1>
            <div>
               <x-secondary-button type="button">{{ __('Unggah') }}</x-secondary-button>
            </div>
         </div>
         <div class="mb-6">
            <label for="d-log-device_code"
               class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
            <x-text-input id="d-log-device_code" wire:model="device_code" type="text"
               placeholder="Scan atau ketik..." />
         </div>
         <div class="grid grid-cols-2 gap-x-3 mb-6">
            <div>
               <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Latensi') }}</label>
               <x-text-input-t placeholder="{{ __('Menunggu...') }}" disabled></x-text-input-t>
            </div>
            <div>
               <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Durasi') }}</label>
               <x-text-input-t placeholder="{{ __('Menunggu...') }}" disabled></x-text-input-t>
            </div>
         </div>
         <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('HB') }}</label>
         <div class="grid grid-cols-8">
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
         </div>
      </div>
      <div class="p-6">
         <h1 class="grow text-xl text-neutral-900 dark:text-neutral-100 mb-6">{{ __('Prediksi') }}</h1>
         <div class="mb-6">
            <label for="adj-formula_id"
               class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Formula') }}</label>
            <x-select class="w-full" id="adj-formula_id" wire:model.live="formula_id" disabled>
               <option value="0"></option>
               <option value="411">{{ __('v4.1.1 - Diff aggresive') }}</option>
               <option value="412">{{ __('v4.1.2 - Diff delicate') }}</option>
               <option value="421">{{ __('v4.2.1 - Ratio') }}</option>
            </x-select>
            @error('formula_id')
               <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
            @enderror
         </div>
         <div class="mb-6">
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Referensi SV') }}</label>
            <x-text-input-t placeholder="{{ __('Dari SV manual') }}" disabled></x-text-input-t>
         </div>
         <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('SVP') }}</label>
         <div class="grid grid-cols-8">
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
            <x-text-input-t class="text-center" placeholder="0" disabled />
         </div>
      </div>
   </div>
</div>