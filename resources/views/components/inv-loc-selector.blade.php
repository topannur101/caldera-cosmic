@props(['isQuery' => false ])

<div x-data="{ 
        loc_parent: @entangle('loc_parent'), 
        loc_bin: @entangle('loc_bin'),
        get loc_name() {
            if (!this.loc_parent.trim() && !this.loc_bin.trim()) {
                return '';
            }
            return `${this.loc_parent} ${this.loc_bin}`.trim();
        } 
    }" class="flex items-center {{ $isQuery ? 'px-4' : '' }}">
    <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'loc-selector')" ::class="loc_name ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'"><i class="fa fa-fw fa-map-marker-alt me-3"></i><span x-text="loc_name ? loc_name : '{{ $isQuery ? __('Lokasi') : __('Tak ada lokasi') }}'"></span></x-text-button>
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
                <div class="grid grid-cols-2 gap-y-6 gap-x-3 mt-6">        
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