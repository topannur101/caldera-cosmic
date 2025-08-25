@props(['isQuery' => false ])

<div x-data="{ 
        uom: @entangle('uom').live,
        uom_hints: @entangle('uom_hints').live,
    }" class="flex items-center {{ $isQuery ? 'px-4' : '' }}">
    <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'uom-selector')" ::class="uom ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'"><i class="icon-ruler-dimension-line me-3"></i><span x-text="uom ? uom : '{{__('UOM') }}'"></span></x-text-button>
    <x-modal name="uom-selector" maxWidth="sm" focusable>
        <div class="p-6 flex flex-col gap-y-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    <i class="icon-ruler-dimension-line me-3"></i>{{ __('UOM') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')">
                    <i class="icon-x"></i>
                </x-text-button>
            </div>
            <div>        
                <x-text-input id="uom-input" list="uom_list" type="text" x-model="uom" x-on:keydown.enter.prevent="$dispatch('close')" />
                <datalist id="uom_list">
                    <template x-for="hint in uom_hints">
                        <option :value="hint"></option>
                    </template>
                </datalist>
            </div>              
            <div class="flex justify-end">
                <x-text-button class="text-xs uppercase font-semibold" type="button" x-on:click="uom = ''; $dispatch('close');" x-show="uom"><span class="text-red-500"><div class="px-1">{{ __('Reset') }}</div></span></x-text-button>
            </div>
        </div>
    </x-modal>   
</div>