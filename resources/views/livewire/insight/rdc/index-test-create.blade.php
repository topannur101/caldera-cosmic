<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
   use WithFileUploads;

   public int $machine_id = 0;

   public array $batch = [
      'code_alt' => '',
      'model' => '',
      'color' => '',
      'mcs' => '',
   ];

   public array $test = [
      'eval' => '',
      's_max' => '',
      's_min' => '',
      'std_tc10' => '',
      'std_tc_50' => '',
      'std_tc_90' => '',
      'tc10' => '',
      'tc50' => '',
      'tc90' => '',
      'tag' => '',
   ];

   public $view;

   public $sh_mods = [];

   public $ins_rdc_tags = [];

   public $file;

   public $machines = [];

   public $update_batch;      

   public function rules()
   {
      return [
         'code_alt' => 'nullable|string|max:50',
         'model' => 'nullable|string|max:30',
         'color' => 'nullable|string|max:20',
         'mcs' => 'nullable|string|max:10',

         'eval' => 'required|in:pass,fail',
         's_max' => 'required|numeric|gt:0|lt:99',
         's_min' => 'required|numeric|gt:0|lt:99',

         'std_tc10' => 'required|numeric|gt:0|lt:999',
         'std_tc_50' => 'nullable|numeric|gt:0|lt:999',
         'std_tc_90' => 'required|numeric|gt:0|lt:999',

         'tc10' => 'required|numeric|gt:0|lt:999',
         'tc50' => 'nullable|numeric|gt:0|lt:999',
         'tc90' => 'required|numeric|gt:0|lt:999',

         'type' => 'required|exists:ins_rdc_types,name',
      ];
   }
}

?>



<div>
   <div x-data="{ dropping: false, machine_id: @entangle('machine_id') }">
      <div class="flex justify-between items-start p-6">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
               {{ __('Sisipkan hasil uji') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
      </div>
      <div class="grid grid-cols-6 gap-6 px-6 mt-6">
         <div class="col-span-4">
            <label for="test-machine_id" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mesin') }}</label>
            <x-select class="w-full" id="test-machine_id" x-model="machine_id" :disabled="$file">
               <option value=""></option>
               @foreach($machines as $machine)
                  <option value="{{ $machine->id }}">{{ $machine->number . ' - ' . $machine->name }}</option>
               @endforeach
            </x-select>
         </div>
         <div class="col-span-2">
            <x-secondary-button type="button" id="test-file" class="w-full h-full justify-center" x-on:click="$refs.file.click()" ><i
            class="fa fa-upload mr-2"></i>{{ __('Unggah') }}</x-secondary-button>
         </div>
      </div>
      <div class="grid grid-cols-6 mt-6">
         <div class="col-span-2 px-6 bg-caldy-500 bg-opacity-10 rounded-r-xl">
            <div class="mt-6">
               <x-pill class="uppercase">{{ __('Batch') }}</x-pill>     
            </div>   
            <div class="mt-6">
               <label for="test-code_alt"
                  class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode alt.') }}</label>
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
            <div class="px-3 my-6">
               <x-toggle name="update_batch" wire:model.live="update_batch" :checked="$update_batch ? true : false" >{{ __('Perbarui') }}</x-toggle>
            </div>
         </div>
         <div class="col-span-4 px-6">
            <div class="mt-6">
               <x-pill class="uppercase">{{ __('Standar') }}</x-pill>     
            </div>    
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3 mt-6">
               <div>
                  <label for="test-std_s_max"
                     class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S maks') }}</label>
                  <x-text-input id="test-std_s_max" wire:model="std_s_max" type="number" step=".01" />
                  @error('std_s_max')
                     <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                  @enderror
               </div>
               <div>
                  <label for="test-std_s_min"
                     class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S Min') }}</label>
                  <x-text-input id="test-std_s_min" wire:model="std_s_min" type="number" step=".01" />
                  @error('std_s_min')
                     <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                  @enderror
               </div>
               <div>
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
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3 mt-6">
               <div>
                     <label for="test-std_tc10"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC10') }}</label>
                     <x-text-input id="test-std_tc10" wire:model="std_tc10" type="number" step=".01" />
                     @error('std_tc10')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                     @enderror
               </div>
               <div>
                     <label for="test-std_tc50"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC50') }}</label>
                     <x-text-input id="test-std_tc50" wire:model="std_tc50" type="number" step=".01" />
                     @error('std_tc50')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                     @enderror
               </div>
               <div>
                     <label for="test-std_tc90"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC90') }}</label>
                     <x-text-input id="test-std_tc90" wire:model="std_tc90" type="number" step=".01" />
                     @error('std_tc90')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                     @enderror
               </div>
            </div>
            <div class="mt-6">
               <x-pill class="uppercase">{{ __('Hasil') }}</x-pill>     
            </div>       
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3 mt-6">
               <div>
                  <label for="test-s_max"
                     class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S maks') }}</label>
                  <x-text-input id="test-s_max" wire:model="s_max" type="number" step=".01" />
                  @error('s_max')
                     <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                  @enderror
               </div>
               <div>
                  <label for="test-s_min"
                     class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('S Min') }}</label>
                  <x-text-input id="test-s_min" wire:model="s_min" type="number" step=".01" />
                  @error('s_min')
                     <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                  @enderror
               </div>
               <div>
                  <label for="test-eval"
                     class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Evaluasi') }}</label>
                  <x-select class="w-full" id="test-eval" wire:model="eval">
                     <option value=""></option>
                     <option value="pass">{{ __('PASS') }}</option>
                     <option value="fail">{{ __('FAIL') }}</option>
                  </x-select>
                  @error('eval')
                     <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                  @enderror
               </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-3 mt-6">
               <div>
                     <label for="test-tc10"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC10') }}</label>
                     <x-text-input id="test-tc10" wire:model="tc10" type="number" step=".01" />
                     @error('tc10')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                     @enderror
               </div>
               <div>
                     <label for="test-tc50"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC50') }}</label>
                     <x-text-input id="test-tc50" wire:model="tc50" type="number" step=".01" />
                     @error('tc50')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                     @enderror
               </div>
               <div>
                     <label for="test-tc90"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('TC90') }}</label>
                     <x-text-input id="test-tc90" wire:model="tc90" type="number" step=".01" />
                     @error('tc90')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                     @enderror
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