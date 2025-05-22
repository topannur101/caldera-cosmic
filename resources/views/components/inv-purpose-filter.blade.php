<div x-data="{ 
        purpose: @entangle('purpose').live, 
        get purpose_facade() {
            if (!this.purpose.trim()) {
                return '';
            }
            return `${this.purpose}`.trim();
        }
    }" class="flex items-center px-4">
    <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'purpose-filter')" ::class="purpose_facade ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'"><span x-text="purpose_facade ? purpose_facade : '{{ __('Keperluan') }}'"></span></x-text-button>
    <x-modal name="purpose-filter" maxWidth="sm" focusable>
        <div class="p-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Keperluan') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')">
                    <i class="fa fa-times"></i>
                </x-text-button>
            </div>
            <div class="grid grid-cols-1 gap-y-6 gap-x-3 mt-6">        
                <div>
                    <label for="purpose" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Keperluan') }}</label>
                    <x-text-input id="purpose" autocomplete="purpose" type="text" x-model="purpose" />
                    </datalist>
                </div>   
                <div class="flex justify-end">
                    <x-text-button class="text-xs uppercase font-semibold" type="button" x-on:click="purpose = ''; $dispatch('close');" x-show="purpose"><span class="text-red-500"><div class="px-1">{{ __('Hapus filter keperluan') }}</div></span></x-text-button>
                </div>
            </div>  
        </div>
    </x-modal>   
</div>