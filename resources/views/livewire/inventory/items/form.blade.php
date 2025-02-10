<?php

use Livewire\Volt\Component;
use App\Models\InvItem;
use App\Models\InvArea;

new class extends Component
{
   public int $id = 0;

   public bool $is_edit = false;

   public array $item = [
      'id'              => '',
      'photo'           => '',
      'area_id'         => 0,
      'area_name'       => '',
      'is_active'       => true,
      'name'            => '',
      'desc'            => '',
      'code'            => '',
      'loc_id'          => 0,
      'loc_name'        => '',
      'tags'            => [],   
      'tags_list'       => '',
      'updated_at'      => '',
      'last_withdrawal' => '',
   ];

   public array $areas = [];

   public function mount()
   {
      

      $item = InvItem::find($this->id);
      if($item) {
         $this->item['id'] = $item->id;
         
      } else {
         $this->areas = InvArea::all()->toArray();

      }
   }

};

?>

<div>
   @if($is_edit)
      <div class="px-4 sm:px-0">
         <div class="flex items-center justify-between gap-x-4 p-4 mb-8 text-sm text-neutral-800 border border-neutral-300 rounded-lg bg-neutral-50 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-600" role="alert">
               <div class="flex items-center">
                  <i class="fa fa-pen me-3"></i>
                  <span class="sr-only">Info</span>
                  <div>
                     <span class="font-medium">{{ __('Mode edit') . ': ' }}</span> {{ __('Klik bidang yang hendak di edit, klik simpan bila sudah selesai.') }}
                  </div>
               </div>
               <div>
                  <x-primary-button type="button"><i class="fa fa-save me-2"></i>{{ __('Simpan') }}</x-primary-button>
               </div>
         </div>
      </div>
   @endif
    <div class="block sm:flex gap-x-6">
        <div wire:key="photo">
            <div class="sticky top-5 left-0">
                <livewire:inventory.items.photo />
                <div class="grid grid-cols-1 divide-y divide-neutral-200 dark:divide-neutral-800 px-4 my-6 text-sm">
                  @if($is_edit)
                     <div class="flex items-center gap-x-3 py-3">
                        <i class="text-neutral-500 fa fa-fw fa-tent me-2"></i>
                        <x-select wire:model="item.area_id" class="w-full">
                           <option value="">{{ __('Pilih area') }}</option>
                           @foreach($areas as $area)
                              <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
                           @endforeach
                        </x-select>
                     </div>
                     <div x-data="{ is_active: @entangle('item.is_active') }" class="flex items-center gap-x-3 py-3">
                        <i x-show="is_active" class="text-neutral-500 fa fa-fw fa-check-circle me-2"></i>
                        <i x-show="!is_active" class="text-neutral-500 fa fa-fw fa-ban me-2"></i>
                        <x-toggle id="item_is_active" x-model="is_active"><span x-show="is_active">{{ __('Aktif') }}</span><span x-show="!is_active">{{ __('Nonaktif') }}</span></x-toggle>
                     </div>
                  @else
                     <div class="py-3"><i class="text-neutral-500 fa fa-fw fa-tent me-2"></i>{{ $item['area_name']}}</div>
                     <div class="py-3"><i class="text-neutral-500 fa fa-fw fa-check-circle me-2"></i>{{ $item['is_active'] ? __('Aktif') : __('Nonaktif')}}</div>
                     <div class="py-3"><i class="text-neutral-500 fa fa-fw fa-pen me-2"></i>{{ __('Edit barang') }}</div>
                     <div class="py-3">{{ __('Terakhir diperbarui') . ': ' . $item['updated_at'] }}</div>
                     <div class="py-3">{{ __('Pengambilan terakhir') . ': ' . $item['last_withdrawal'] }}</div>
                  @endif
                </div>
            </div>
        </div>
        <div class="grow">
            <div class="bg-white dark:bg-neutral-800 shadow rounded-none sm:rounded-lg px-6 divide-y divide-neutral-200 dark:divide-neutral-700">
                <div class="py-6">
                  @if($is_edit)
                     <x-text-input type="text" class="text-2xl mb-3" wire:model="item.name" placeholder="{{ __('Nama barang') }}" />
                     <x-text-input type="text" wire:model="item.desc" placeholder="{{ __('Deskripsi barang') }}" />
                  @else
                     <h1 class="text-2xl font-medium text-neutral-900 dark:text-neutral-100 mb-3">{{ $item['name'] }}</h1>
                     <p>{{ $item['desc'] }}</p>
                  @endif
                </div>
                @if($is_edit)
                  <div class="py-6 grid grid-cols-1 gap-y-3">
                     <x-text-input type="text" wire:model="item.code" placeholder="{{ __('Kode barang') }}" />
                     <x-text-input-icon  icon="fa fa-fw fa-map-marker-alt" id="item_loc" type="text" placeholder="{{ __('Lokasi') }}" />     
                     <x-text-input-icon  icon="fa fa-fw fa-tag" id="item_loc" type="text" placeholder="{{ __('Tag') }}" />              
                  </div>
                @else
                  <div class="py-6 flex flex-col lg:flex-row gap-x-6 gap-y-3 text-neutral-500 text-sm">                    
                     <div>{{ $item['code'] ?: __('TAK ADA KODE') }}</div>
                     <div><i class="fa fa-fw fa-map-marker-alt me-2"></i>{{ $item['loc_name'] ?: __('Tak ada lokasi') }}</div>
                     <div><i class="fa fa-fw fa-tag me-2"></i>{{ $item['tags_list'] ?: __('Tak ada tag') }}</div>
                  </div>
                @endif

            </div>
            @if($is_edit)
            <div class="mt-6 p-4 mb-8 italic tracking-wider text-sm text-center text-neutral-500 border border-dashed rounded-lg border-neutral-300 dark:border-neutral-600" role="alert">
               {{ __('Stok dan Sirkulasi') }}
            </div>

            @else
               <livewire:inventory.items.stocks />

            @endif
        </div>
    </div>
</div>
