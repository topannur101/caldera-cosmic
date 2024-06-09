<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

use App\Models\InvArea;

new #[Layout('layouts.app')] 
class extends Component {

   #[On('updated')]
   public function with(): array {
      return [
         'areas' => InvArea::all()
   ];
   }

}

?>

<x-slot name="title">{{ __('Area') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
   <header class="bg-white dark:bg-neutral-800 shadow">
      <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div>  
              <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                  <x-link href="{{ route('inventory.admin.index', ['view' => 'global']) }}" class="inline-block py-6" wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('Area') }}</span></span>
              </h2>
          </div>
      </div>
  </header>
</x-slot>

<div id="content" class="py-12 max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   <div>
      <div class="flex justify-between px-6 sm:px-0">
          <div>
              {{ $areas->count() . ' ' . __('area terdaftar') }}
          </div>
          <x-secondary-button type="button" class="my-auto" x-data="" x-on:click="$dispatch('open-modal', 'create-area')">{{ __('Buat') }}</x-secondary-button>
      
      </div>
      <x-modal name="create-area">
          <livewire:layout.inv-areas-create wire:key="areas-create" lazy />
      </x-modal>
      <div class="w-full mt-5">
          <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg">            
              <table wire:key="areas-table" class="table">
                  <tr class="uppercase text-xs">
                      <th>
                          {{ __('Nama') }}
                      </th>
                  </tr>
                  @foreach($areas as $area)
                  <tr wire:key="area-tr-{{ $area->id . $loop->index }}" tabindex="0" x-on:click="$dispatch('open-modal', 'edit-area-{{ $area->id }}')">
                      <td>
                          {{ $area->name }}
                      </td> 
                  </tr>
                  <x-modal :name="'edit-area-'.$area->id">
                      <livewire:layout.inv-areas-edit wire:key="area-lw-{{ $area->id . $loop->index }}" :area="$area" lazy />                    
                  </x-modal> 
                  @endforeach
              </table>
              <div wire:key="areas-none">
                  @if(!$areas->count())
                      <div class="text-center py-12">
                          {{ __('Tak ada area terdaftar') }}
                      </div>
                  @endif
              </div>
          </div>
      </div>    
  </div>
</div>
