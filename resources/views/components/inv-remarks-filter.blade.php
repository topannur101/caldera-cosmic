@props(['isQuery' => false ])

<div x-data="{ 
        circ_remarks: @entangle('remarks.0').live, 
        eval_remarks: @entangle('remarks.1').live,
        get remarks_facade() {
            if (!this.circ_remarks.trim() && !this.eval_remarks.trim()) {
                return '';
            }
            return `${this.circ_remarks} ${this.eval_remarks}`.trim();
        }
    }" class="flex items-center {{ $isQuery ? 'px-4' : '' }}">
    <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'remarks-filter')" ::class="remarks_facade ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'"><span x-text="remarks_facade ? remarks_facade : '{{ $isQuery ? __('Keterangan') : __('Filter keterangan') }}'"></span></x-text-button>
    <x-modal name="remarks-filter" maxWidth="sm" focusable>
        <div class="p-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Keterangan') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')">
                    <i class="fa fa-times"></i>
                </x-text-button>
            </div>
            <div class="grid grid-cols-1 gap-y-6 gap-x-3 mt-6">        
                <div>
                    <label for="circ-remarks" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Keterangan sirkulasi') }}</label>
                    <x-text-input id="circ-remarks" autocomplete="circ-remarks" type="text" x-model="circ_remarks" x-on:keydown.enter.prevent="$nextTick(() => { $refs.evalRemarks.focus() })" />
                    </datalist>
                </div>                           
                <div>
                    <label for="eval-remarks" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Keterangan evaluasi') }}</label>
                    <x-text-input id="eval-remarks" autocomplete="eval-remarks" type="text" x-model="eval_remarks" x-ref="evalRemarks" x-on:keydown.enter.prevent="$dispatch('close')" />
                </div>
                <div class="flex justify-end">
                    <x-text-button class="text-xs uppercase font-semibold" type="button" x-on:click="circ_remarks = ''; eval_remarks = ''; $dispatch('close');" x-show="circ_remarks || eval_remarks"><span class="text-red-500"><div class="px-1">{{ __('Hapus filter keterangan') }}</div></span></x-text-button>
                </div>
            </div>  
        </div>
    </x-modal>   
</div>