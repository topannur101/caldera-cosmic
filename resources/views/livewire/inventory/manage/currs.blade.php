<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

use App\Models\InvCurr;

new #[Layout('layouts.app')] 
class extends Component {

   #[On('updated')]
   public function with(): array {
      return [
         'currs' => InvCurr::all()
   ];
   }

}

?>

<x-slot name="title">{{ __('Mata uang') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
   <header class="bg-white dark:bg-neutral-800 shadow">
      <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div>  
              <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                  <x-link href="{{ route('inventory.manage.index', ['view' => 'administration']) }}" class="inline-block py-6" wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('Mata uang') }}</span></span>
              </h2>
          </div>
      </div>
  </header>
</x-slot>

<div id="content" class="py-12 max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   <div>
      <div class="flex justify-between px-6 sm:px-0">
          <div>
              {{ $currs->count() . ' ' . __('mata uang terdaftar') }}
          </div>
          <x-secondary-button type="button" class="my-auto" x-data=""
              x-on:click.prevent="$dispatch('open-modal', 'create-curr')">{{ __('Buat') }}</x-secondary-button>
  
      </div>
      <div class="w-full mt-5">
          <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg">
              <table wire:key="currs-table" class="table">
                  <tr class="uppercase text-xs">
                      <th>
                          {{ __('Nama') }}
                      </th>
                      <th>
                          {{ __('Nilai tukar') }}
                      </th>
                  </tr>
                  @foreach ($currs as $curr)
                      <tr wire:key="curr-tr-{{ $curr->id.$loop->index }}" tabindex="0" x-on:click="$dispatch('open-modal', 'edit-curr-{{ $curr->id }}')">
                          <td>
                              {{ $curr->name }}
                              @if ($curr->id == 1)
                                  <span><i class="fa fa-star text-sm ml-2"></i></span>
                              @endif
                          </td>
                          <td>
                              @if ($curr->id == 1)
                                  <span>1</span>
                              @else
                                  {{ $curr->rate }}
                              @endif
                          </td>
                      </tr>
                      <x-modal :name="'edit-curr-' . $curr->id">
                          <livewire:layout.inv-currs-edit wire:key="curr-lw-{{ $curr->id.$loop->index }}" :curr="$curr" lazy />
                      </x-modal>
                  @endforeach
              </table>
          </div>
      </div>
  </div>
  
</div>
