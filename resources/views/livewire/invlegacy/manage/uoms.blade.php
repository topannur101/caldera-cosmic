<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

use App\Models\InvUom;

new #[Layout('layouts.app')] 
class extends Component {

   #[On('updated')]
   public function with(): array {
      return [
         'uoms' => InvUom::all()
   ];
   }

}

?>

<x-slot name="title">{{ __('UOM') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
   <header class="bg-white dark:bg-neutral-800 shadow">
      <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div>  
              <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                  <x-link href="{{ route('invlegacy.manage.index', ['view' => 'administration']) }}" class="inline-block py-6" wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('UOM') }}</span></span>
              </h2>
          </div>
      </div>
  </header>
</x-slot>

<div id="content" class="py-12 max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   <div>
      <div class="flex justify-between px-6 sm:px-0">
          <div>
              {{ $uoms->count() . ' ' . __('UOM terdaftar') }}
          </div>
          <x-text-button type="button" class="my-auto" x-data=""
          x-on:click.prevent="$dispatch('open-modal', 'create-uom')"><i class="far fa-question-circle"></i></x-text-button>    
      </div>
      <x-modal name="create-uom">
          <div class="p-6">
              <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                  {{ __('UOM baru') }}
              </h2>
              <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                  {{__('Setiap UOM baru pada saat barang ditambahkan atau diedit, akan otomatis tersimpan dan ditampilkan di halaman ini.')}}
              </p>
              <div class="mt-6 flex justify-end">
                  <x-primary-button type="button" x-on:click="$dispatch('close')">
                      {{ __('Paham') }}
                  </x-primary-button>
              </div>
          </div>
      </x-modal>
      <div class="w-full mt-5">
          <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg">            
              <table wire:key="uoms-table" class="table">
                  <tr class="uppercase text-xs">
                      <th>
                          {{ __('Nama') }}
                      </th>
                  </tr>
                  @foreach($uoms as $uom)
                  <tr wire:key="uom-tr-{{ $uom->id . $loop->index }}" tabindex="0" x-on:click="$dispatch('open-modal', 'edit-uom-{{ $uom->id }}')">
                      <td>
                          {{ $uom->name }}
                      </td> 
                  </tr>
                  <x-modal :name="'edit-uom-'.$uom->id">
                      <livewire:layout.inv-uoms-edit wire:key="uom-lw-{{ $uom->id . $loop->index }}" :uom="$uom" lazy />                    
                  </x-modal> 
                  @endforeach
              </table>
              <div wire:key="uoms-none">
                  @if(!$uoms->count())
                      <div class="text-center py-12">
                          {{ __('Tak ada UOM terdaftar') }}
                      </div>
                  @endif
              </div>
          </div>
      </div>    
  </div>
</div>
