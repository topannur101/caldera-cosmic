@props(['isQuery' => false ])

<div x-data="{ 
        loc_parent: @entangle('loc_parent').live, 
        loc_bin: @entangle('loc_bin').live,

        loc_parent_hints: @entangle('loc_parent_hints').live,
        loc_bin_hints: @entangle('loc_bin_hints').live,

        get loc_name() {
            if (!this.loc_parent.trim() && !this.loc_bin.trim()) {
                return '';
            }
            return `${this.loc_parent}-${this.loc_bin}`.trim();
        }
    }" class="flex items-center {{ $isQuery ? 'px-4' : '' }}">
    <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'loc-selector')" ::class="loc_name ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'"><i class="icon-map-pin me-3"></i><span x-text="loc_name ? loc_name : '{{__('Lokasi') }}'"></span></x-text-button>
    <x-modal name="loc-selector" maxWidth="sm" focusable>
        <div class="p-6 flex flex-col gap-y-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    <i class="icon-map-pin me-3"></i>{{ __('Lokasi') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')">
                    <i class="icon-x"></i>
                </x-text-button>
            </div>
            <div class="grid grid-cols-2 gap-y-6 gap-x-3">        
                <div>
                    <label for="loc-parent" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Induk') }}</label>
                    <x-text-input id="loc-parent" list="loc_parents" type="text" x-model="loc_parent" x-on:keydown.enter.prevent="$nextTick(() => { $refs.locBin.focus() })" />
                    <datalist id="loc_parents">
                        <template x-for="hint in loc_parent_hints">
                            <option :value="hint"></option>
                        </template>
                    </datalist>
                </div>                           
                <div>
                    <label for="loc-bin" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Bin') }}</label>
                    <x-text-input id="loc-bin" list="loc_bins" type="text" x-model="loc_bin" x-ref="locBin" x-on:keydown.enter.prevent="$dispatch('close')" />
                    <datalist id="loc_bins">
                        <template x-for="hint in loc_bin_hints">
                            <option :value="hint"></option>
                        </template>
                    </datalist>
                </div>
            </div>              
            <div class="flex justify-end">
                <x-text-button class="text-xs uppercase font-semibold" type="button" x-on:click="loc_parent = ''; loc_bin = ''; $dispatch('close');" x-show="loc_parent || loc_bin"><span class="text-red-500"><div class="px-1">{{ __('Reset') }}</div></span></x-text-button>
            </div>
        </div>
    </x-modal>   
</div>