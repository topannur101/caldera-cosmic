<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {

   use WithFileUploads;

   public int $machine_id = 0;

   public $view;

   public $sh_mods = [];
   public $ins_rdc_tags = [];
   public $file;
   public $machines = [];

}

?>

<div>
   <div x-data="{ dropping: false, machine_id: @entangle('machine_id') }" class="p-6">
      <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
               {{ __('Sisipkan hasil uji') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
      </div>
      <div class="grid grid-cols-5 gap-8 mt-8">
         <div class="col-span-2">
         <div class="flex flex-col">
               <dt class="px-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Informasi batch') }}</dt>
               <dd>
                     <div class="mt-6">
                        <label for="test-code_alt"
                           class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode alternatif') }}</label>
                        <x-text-input id="test-code_alt" wire:model="code_alt" type="text" />
                        @error('code_alt')
                           <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                     </div>
                     <div class="mt-6">
                        <label for="test-model"
                           class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Model') }}</label>
                        <x-text-input id="test-model" list="test-models" wire:model="model" type="text" />
                        @error('model')
                           <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                        <datalist id="test-models">
                           @foreach ($sh_mods as $sh_mod)
                                 <option value="{{ $sh_mod->name }}">
                           @endforeach
                        </datalist>
                     </div>
                     <div class="mt-6">
                        <label for="test-color"
                           class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Warna') }}</label>
                        <x-text-input id="test-color" wire:model="color" type="text" />
                        @error('color')
                           <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                     </div>
                     <div class="mt-6">
                        <label for="test-mcs"
                           class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('MCS') }}</label>
                        <x-text-input id="test-mcs" wire:model="mcs" type="text" />
                        @error('mcs')
                           <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                     </div>
               </dd>
            </div>
         </div>
         <div class="col-span-3">
            <div class="flex-flex-col">
               <dt class="px-3 text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Hasil pengujian') }}</dt>
               <dd>
               <div class="relative" x-on:dragover.prevent="machine_id ? dropping = true : dropping = false">
                  <div wire:loading.class="hidden"
                        class="absolute w-full h-full top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white/80 dark:bg-neutral-800/80 py-3"
                        x-cloak x-show="dropping">
                        <div
                           class="flex justify-around items-center w-full h-full border-dashed border-2 border-neutral-500  text-neutral-500 dark:text-neutral-400 rounded-lg">
                           <div class="text-center">
                              <div class="text-4xl mb-3">
                                    <i class="fa fa-upload"></i>
                              </div>
                           </div>
                        </div>
                  </div>
                  <input wire:model="file" type="file"
                           class="absolute inset-0 m-0 p-0 w-full h-full outline-none opacity-0" x-cloak x-ref="file"
                           x-show="dropping" x-on:dragleave.prevent="dropping = false" x-on:drop="dropping = false" />
                  <div class="mt-6">
                  <label for="test-machine_id"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mesin uji') }}</label>

                  <x-select class="w-full" id="test-machine_id" x-model="machine_id" :disabled="$file">
                           <option value=""></option>
                           @foreach($machines as $machine)
                              <option value="{{ $machine->id }}">{{ $machine->number . ' - ' . $machine->name }}</option>
                           @endforeach
                        </x-select>
                        @error('machine_id')
                           <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                        @error('file')
                           <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                  </div>
               </div>
                     <div class="relative">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3">
                           <div class="mt-6">
                                 <label for="test-eval"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Hasil') }}</label>
                                 <x-select class="w-full" id="test-eval" wire:model="eval">
                                    <option value=""></option>
                                    <option value="pass">{{ __('PASS') }}</option>
                                    <option value="fail">{{ __('FAIL') }}</option>
                                 </x-select>
                                 @error('eval')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                 @enderror
                           </div>
                           <div class="mt-6">
                                 <label for="test-s_max"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S maks') }}</label>
                                 <x-text-input id="test-s_max" wire:model="s_max" type="number" step=".01" />
                                 @error('s_max')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                 @enderror
                           </div>
                           <div class="mt-6">
                                 <label for="test-s_min"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S Min') }}</label>
                                 <x-text-input id="test-s_min" wire:model="s_min" type="number" step=".01" />
                                 @error('s_min')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                 @enderror
                           </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3">
                           <div class="mt-6">
                                 <label for="test-tc10"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC10') }}</label>
                                 <x-text-input id="test-tc10" wire:model="tc10" type="number" step=".01" />
                                 @error('tc10')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                 @enderror
                           </div>
                           <div class="mt-6">
                                 <label for="test-tc50"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC50') }}</label>
                                 <x-text-input id="test-tc50" wire:model="tc50" type="number" step=".01" />
                                 @error('tc50')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                 @enderror
                           </div>
                           <div class="mt-6">
                                 <label for="test-tc90"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC90') }}</label>
                                 <x-text-input id="test-tc90" wire:model="tc90" type="number" step=".01" />
                                 @error('tc90')
                                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                                 @enderror
                           </div>
                        </div>
                     </div>
               </dd> 
               <div class="mt-6">
                     <label for="test-tag"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tag') }}</label>
                     <x-text-input id="test-tag" list="test-tags" wire:model="tag" type="text" />
                     @error('tag')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                     @enderror
                     <datalist id="test-tags">
                        @foreach ($ins_rdc_tags as $ins_rdc_tag)
                           <option value="{{ $ins_rdc_tag->name }}">
                        @endforeach
                     </datalist>
               </div> 
            </div>
         </div>
      </div>
   </div>
   <div class="p-6 flex justify-between items-center">
      <x-dropdown align="left" width="48">
            <x-slot name="trigger">
               <x-text-button><i class="fa fa-fw fa-ellipsis-v"></i></x-text-button>
            </x-slot>
            <x-slot name="content">
               <x-dropdown-link href="#" wire:click.prevent="customReset">
                  {{ __('Reset') }}
               </x-dropdown-link>
               <hr class="border-neutral-300 dark:border-neutral-600 {{ true ? '' : 'hidden' }}" />
               <x-dropdown-link href="#" wire:click.prevent="removeFromQueue"
                  class="{{ true ? '' : 'hidden' }}">
                  {{ __('Hapus dari antrian') }}
               </x-dropdown-link>
            </x-slot>
      </x-dropdown>
      <div class="flex flex-row gap-x-3">
            @if($view != 'form')
            <x-secondary-button type="button" wire:click="$set('view', 'form'); $set('file', '')" x-show="machine_id">{{ __('Isi manual') }}</x-secondary-button>
            @endif
            @if($view == 'review' || $view == 'form')
            <x-primary-button type="button" wire:click="insertTest">
               {{ __('Sisipkan') }}
            </x-primary-button>
            @endif
            @if($view == 'upload')
            <x-primary-button type="button" x-on:click="$refs.file.click()" x-show="machine_id" ><i
               class="fa fa-upload mr-2"></i>{{ __('Unggah') }}</x-primary-button>
            @endif
      </div>
   </div>
</div>