<?php

use Livewire\Volt\Component;
use App\Models\InvItem;
use App\Models\InvArea;

new class extends Component
{
   public int $id = 0;

   public bool $is_editing = false;

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
      'loc_parent'      => '',
      'loc_bin'        => '',
      'tags'            => [],  
      'updated_at'      => '',
      'last_withdrawal' => '',
   ];

   public array $areas        = [];
   public array $tags         = [];
   public array $loc_parents  = [];
   public array $loc_bins     = [];

   public function mount()
   {
      

      $item = InvItem::find($this->id);
      if($item) {
         $this->item['id'] = $item->id;
         
      } else {
         $this->areas = InvArea::all()->toArray();
         $this->is_editing = true;

      }
   }

};

?>

<div>
   @if($is_editing)
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
                <livewire:inventory.items.photo :$is_editing />
                <div class="grid grid-cols-1 divide-y divide-neutral-200 dark:divide-neutral-800 px-4 my-6 text-sm">
                  @if($is_editing)
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
                <div class="grid gap-y-3 py-6">
                  @if($is_editing)
                     <div>
                        <label for="item-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                        <x-text-input id="item-name" wire:model="item.desc" type="text" />
                     </div>
                     <div>
                        <label for="item-desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Deskripsi') }}</label>
                        <x-text-input id="item-desc" wire:model="item.desc" type="text" />
                     </div>
                  @else
                     <h1 class="text-2xl font-medium text-neutral-900 dark:text-neutral-100">{{ $item['name'] }}</h1>
                     <p>{{ $item['desc'] }}</p>
                  @endif
                </div>
                @if($is_editing)
                  <div class="py-6 grid grid-cols-1 gap-y-3">
                     <div>
                        <label for="item-code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
                        <x-text-input id="item-code" wire:model="item.code" type="text" />
                     </div>
                     <div x-data="{ 
                           loc_parent: @entangle('item.loc_parent'), 
                           loc_bin: @entangle('item.loc_name'),
                           get loc_name() {
                              if (!this.loc_parent.trim() && !this.loc_bin.trim()) {
                                    return '';
                              }
                              return `${this.loc_parent} ${this.loc_bin}`.trim();
                           } 
                        }" class="px-3 mt-3">
                        <x-text-button type="button" x-on:click.prevent="$dispatch('open-modal', 'loc-selector')"><i class="fa fa-fw fa-map-marker-alt me-2"></i><span x-text="loc_name ? loc_name : '{{ __('Tak ada lokasi') }}'"></span></x-text-button>
                        <x-modal name="loc-selector" maxWidth="sm" focusable>
                           <div>
                              <form wire:submit.prevent="save" class="p-6">
                                 <div class="flex justify-between items-start">
                                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                       <i class="fa fa-fw fa-map-marker-alt me-3"></i>{{ __('Pilih lokasi') }}
                                    </h2>
                                    <x-text-button type="button" x-on:click="$dispatch('close')">
                                       <i class="fa fa-times"></i>
                                    </x-text-button>
                                 </div>
                                 <div class="grid grid-cols-1 gap-y-6 mt-6">        
                                    <div>
                                       <label for="loc-parent" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Induk') }}</label>
                                       <x-text-input id="loc-parent" type="text" x-model="loc_parent" />
                                       <datalist id="loc_parents">
                                          @foreach($loc_parents as $loc_parent)
                                             <option value="{{ $loc_parent }}"></option>
                                          @endforeach
                                       </datalist>
                                    </div>                           
                                    <div>
                                       <label for="loc-bin" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Bin') }}</label>
                                       <x-text-input id="loc-bin" type="text" x-model="loc_bin" />
                                       <datalist id="loc_bins">
                                          @foreach($loc_bins as $loc_bin)
                                             <option value="{{ $loc_bin }}"></option>
                                          @endforeach
                                       </datalist>
                                    </div>
                                 </div>  
                              </form>
                           </div>
                        </x-modal>   
                     </div>
                     <div x-data="{
                           tags: @entangle('item.tags'),
                           tag_input: '',
                           get tag_list() {
                              return this.tags.join(', ');
                           },
                           addTag() {
                              let tag = this.tag_input.trim().toLowerCase();
                              if (!tag) return;
                              
                              if (this.tags.length >= 5) {
                                    toast('{{ __('Hanya maksimal 5 tag diperbolehkan') }}', { type: 'danger' });
                                    return;
                              }
                              
                              if (this.tags.includes(tag)) {
                                    toast('{{ __('Tag sudah ada') }}', { type: 'danger' });
                              } else {
                                    this.tags.push(tag);
                              }
                              
                              this.tag_input = '';
                           },
                           removeTag(tag) {
                              this.tags = this.tags.filter(t => t !== tag);
                           }
                        }" class="px-3 mt-3">
                        <x-text-button type="button" x-on:click.prevent="$dispatch('open-modal', 'tag-selector')">
                           <i class="fa fa-fw fa-tag me-2"></i><span x-text="tag_list ? tag_list : '{{ __('Tak ada tag') }}'"></span>
                        </x-text-button>
                        
                        <x-modal name="tag-selector" maxWidth="sm" focusable>
                           <div>
                              <form wire:submit.prevent="save" class="p-6">
                                 <div class="flex justify-between items-start">
                                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                       <i class="fa fa-fw fa-tag me-3"></i>{{ __('Pilih tag') }}
                                    </h2>
                                    <x-text-button type="button" x-on:click="$dispatch('close')">
                                       <i class="fa fa-times"></i>
                                    </x-text-button>
                                 </div>
                                 <div class="grid grid-cols-1 gap-y-6 mt-6">
                                    <div class="flex flex-wrap gap-2 text-sm">
                                       <div x-show="tags.length === 0" class="text-neutral-500 italic">{{ __('Tak ada tag') }}</div>
                                       <template x-for="tag in tags" :key="tag">
                                          <div class="bg-neutral-200 dark:bg-neutral-900 rounded-full px-3 py-1">
                                             <span x-text="tag"></span>
                                             <x-text-button type="button" x-on:click="removeTag(tag)" class="ml-2">
                                                <i class="fa fa-times"></i>
                                             </x-text-button>
                                          </div>
                                       </template>
                                    </div>
                                    <x-text-input-icon id="tag-search" icon="fa fa-fw fa-search" type="text" x-model="tag_input" maxlength="20" placeholder="{{ __('Cari tag') }}" x-on:keydown.enter.prevent="addTag" />
                                    <datalist id="tags">
                                       @foreach($tags as $tag)
                                          <option value="{{ $tag }}"></option>
                                       @endforeach
                                    </datalist>
                                 </div>  
                              </form>
                           </div>
                        </x-modal>                           
                     </div>
                  </div>
                @else
                  <div class="py-6 flex flex-col lg:flex-row gap-x-6 gap-y-3 text-neutral-500 text-sm">                    
                     <div>{{ $item['code'] ?: __('TAK ADA KODE') }}</div>
                     <div><i class="fa fa-fw fa-map-marker-alt me-2"></i>{{ $item['loc_name'] ?: __('Tak ada lokasi') }}</div>
                     <div><i class="fa fa-fw fa-tag me-2"></i>{{ $item['tags_list'] ?: __('Tak ada tag') }}</div>
                  </div>
                @endif

            </div>
            @if($is_editing)
            <div class="mt-6 p-4 mb-8 italic tracking-wider text-sm text-center text-neutral-500 border border-dashed rounded-lg border-neutral-300 dark:border-neutral-600" role="alert">
               {{ __('Stok dan Sirkulasi') }}
            </div>

            @else
               <livewire:inventory.items.stocks />

            @endif
        </div>
    </div>
</div>
