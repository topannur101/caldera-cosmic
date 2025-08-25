<div x-data="{ 
        filter: @entangle('filter').live,
        aging: @entangle('aging').live,
        get filter_name() {
         switch (this.filter) {
            case 'no-code':
               return `{{ __('Tanpa kode') }}`;
            case 'no-photo':
               return `{{ __('Tanpa foto') }}`;
            case 'no-location':
               return `{{ __('Tanpa lokasi') }}`;
            case 'no-tags':
               return `{{ __('Tanpa tag') }}`;
            case 'inactive':
               return `{{ __('Nonaktif') }}`;
            default:
                return '';
            }
        },
        get aging_name() {
            switch (this.aging) {
            case 'gt-100-days':
                return `{{ '> 100' . __(' hari') }}`;
            case 'gt-90-days':
                return `{{ '> 90' . __(' hari') }}`;
            case 'gt-60-days':
                return `{{ '> 60' . __(' hari') }}`;
            case 'gt-30-days':
                return `{{ '> 30' . __(' hari') }}`;
            case 'lt-30-days':
                return `{{ '< 30' . __(' hari') }}`;
            default:
                return '';
            }
        }
    }" class="flex items-center px-4">
    <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'search-filter')" ::class="(filter || aging) ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'"><i class="icon-funnel me-3"></i><span x-text="(filter || aging) ? filter_name + ' ' + aging_name : '{{ __('Filter') }}'"></span></x-text-button>
    <x-modal name="search-filter" maxWidth="sm">
        <div class="p-6 flex flex-col gap-y-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    <i class="icon-funnel me-3"></i>{{ __('Filter tambahan') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')">
                    <i class="icon-x"></i>
                </x-text-button>
            </div>
            <div>
                <label class="block uppercase mb-1 text-xs text-neutral-500">
                    {{ __('Kelengkapan data') }}
                </label>
                <div>
                    <x-radio x-model="filter" id="filter-no-code" name="filter-no-code" value="no-code">{{ __('Tanpa kode') }}</x-radio>
                    <x-radio x-model="filter" id="filter-no-photo" name="filter-no-photo" value="no-photo">{{  __('Tanpa foto') }}</x-radio>
                    <x-radio x-model="filter" id="filter-no-location" name="filter-no-location" value="no-location">{{ __('Tanpa lokasi') }}</x-radio>
                    <x-radio x-model="filter" id="filter-no-tags" name="filter-no-tags" value="no-tags">{{ __('Tanpa tag') }}</x-radio>
                    <x-radio x-model="filter" id="filter-inactive" name="filter-inactive" value="inactive">{{ __('Nonaktif') }}</x-radio>
                </div> 
                <div class="px-5">
                    <x-text-button class="text-xs uppercase font-semibold" type="button" x-on:click="filter = ''; $dispatch('close');" x-show="filter"><span class="text-red-500"><div class="px-1">{{ __('Reset') }}</div></span></x-text-button>
                </div>
            </div>
            <div>
                <label class="block uppercase mb-1 text-xs text-neutral-500">
                    {{ __('Barang yang menua') }}
                </label>  
                <div>
                    <x-radio x-model="aging" id="aging-gt-100-days" name="aging-gt-100-days" value="gt-100-days">{{ '> 100' . __(' hari') }}</x-radio>
                    <x-radio x-model="aging" id="aging-gt-90-days" name="aging-gt-90-days" value="gt-90-days">{{ '> 90' . __(' hari') }}</x-radio>
                    <x-radio x-model="aging" id="aging-gt-60-days" name="aging-gt-60-days" value="gt-60-days">{{ '> 60' . __(' hari') }}</x-radio>
                    <x-radio x-model="aging" id="aging-gt-30-days" name="aging-gt-30-days" value="gt-30-days">{{ '> 30' . __(' hari') }}</x-radio>
                    <x-radio x-model="aging" id="aging-lt-30-days" name="aging-lt-30-days" value="lt-30-days">{{ '< 30' . __(' hari') }}</x-radio>
                </div> 
                <div class="px-5">
                    <x-text-button class="text-xs uppercase font-semibold" type="button" x-on:click="aging = ''; $dispatch('close');" x-show="aging"><span class="text-red-500"><div class="px-1">{{ __('Reset') }}</div></span></x-text-button>
                </div>
            </div>     
        </div>
    </x-modal>   
</div>