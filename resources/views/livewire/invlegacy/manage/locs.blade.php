<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

use App\Models\InvArea;
use App\Models\InvLoc;

new #[Layout('layouts.app')] 
class extends Component {

   use WithPagination;
    
    #[Url] 
    public $area_id = '';

    #[Url] 
    public $q = '';

    public $perPage = 24;

    #[On('updated')]
    public function with(): array
    {
      $locs = InvLoc::where('inv_area_id', $this->area_id)
            ->where('name', 'LIKE', '%'.$this->q.'%')
            ->orderBy('name')
            ->paginate($this->perPage);

        return [
         'areas'  => InvArea::all(),
         'locs'   => $locs
    ];
    }

    public function updating($property)
    {
        if($property == 'area_id' || $property == 'q')
        {
            $this->reset('perPage');
        }

    }

    public function loadMore()
    {
        $this->perPage += 24;
    }

}

?>

<x-slot name="title">{{ __('Lokasi') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
   <header class="bg-white dark:bg-neutral-800 shadow">
      <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div>  
              <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                  <x-link href="{{ route('invlegacy.manage.index') }}" class="inline-block py-6" wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('Lokasi') }}</span></span>
              </h2>
          </div>
      </div>
  </header>
</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   <div>
      <div class="flex justify-between items-center px-6 sm:px-0">
          <div class="w-64">
              <x-select wire:model.live="area_id">
                  <option value=""></option>
                  @foreach($areas as $area)
                  <option value="{{ $area->id }}">{{ $area->name }}</option>
                  @endforeach
              </x-select>  
          </div>
          <div>
              @if($area_id)
              @cannot('manage', $locs[0])
              <x-text-button type="button" class="uppercase text-xs ml-2" x-data=""
                  x-on:click.prevent="$dispatch('open-modal', 'view-only')"><i class="fa fa-lock"></i><span class="ms-2 hidden sm:inline">{{ __('Lihat saja') }}</span></x-text-button>
              <x-modal name="view-only">
                  <div class="p-6">
                      <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                          {{ __('Akses terbatas') }}
                      </h2>
                      <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                          {{__('Kamu tidak memiliki wewenang untuk mengelola lokasi di area ini.')}}
                      </p>
                      <div class="mt-6 flex justify-end">
                          <x-primary-button type="button" x-on:click="$dispatch('close')">
                              {{ __('Paham') }}
                          </x-primary-button>
                      </div>
                  </div>
              </x-modal>
              @endcannot
              @endif
          </div>
      </div>
      <div class="w-full mt-5">
          <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg">          
              <div class="flex items-center justify-between px-6 py-3">
                  <div class="text-xs font-bold uppercase">{{ $locs->total() . ' ' . __('lokasi') }}</div>
                  <div class="w-40">
                      <x-text-input-search wire:model.live="q" id="inv-q" placeholder="{{ __('CARI') }}"></x-text-input-search>
                  </div>
              </div>  
              <hr class="border-neutral-200 dark:border-neutral-700" />
              <table wire:key="locs-table" class="table">
                  @foreach($locs as $loc)
                  <tr wire:key="loc-tr-{{ $loc->id . $loop->index }}" tabindex="0" x-on:click="$dispatch('open-modal', 'edit-loc-{{ $loc->id }}')">
                      <td>
                          {{ $loc->name }}
                      </td> 
                  </tr>
                  @can('manage', $loc)
                  <x-modal :name="'edit-loc-'.$loc->id">
                      <livewire:layouts.inv-locs-edit wire:key="loc-lw-{{ $loc->id . $loop->index }}" :loc="$loc" lazy />                    
                  </x-modal> 
                  @endcan
                  @endforeach
              </table>
              <div wire:key="locs-none">
                  @if(!$locs->count())
                      <div class="text-center py-12">
                          {{ __('Tak ada lokasi ditemukan') }}
                      </div>
                  @endif
              </div>
          </div>
      </div>  
      <div wire:key="observer" class="flex items-center relative h-16">
          @if(!$locs->isEmpty())
          @if($locs->hasMorePages())
              <div wire:key="more" x-data="{
                  observe(){
                      const observer = new IntersectionObserver((locs) => {
                          locs.forEach(loc => {
                              if(loc.isIntersecting) {
                                  @this.loadMore()
                              }
                          })
                      })
                      observer.observe(this.$el)
                  }
              }" x-init="observe"></div>
              <x-spinner class="sm" />
          @else
              <div class="mx-auto">{{__('Tidak ada lagi')}}</div>
          @endif
          @endif
      </div>
  </div>
</div>
