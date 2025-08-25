<div x-data="{ 
        filter: @entangle('filter').live,
        aging: @entangle('aging').live,
        limit: @entangle('limit').live,
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
        },
        get limit_name() {
            switch (this.limit) {
            case 'under-qty-limit':
                return `{{ __('Di bawah minimum') }}`;
            case 'over-qty-limit':
                return `{{ __('Di atas maksimum') }}`;
            case 'outside-qty-limit':
                return `{{ __('Di luar batas') }}`;
            case 'inside-qty-limit':
                return `{{ __('Di dalam batas') }}`;
            case 'no-qty-limit':
                return `{{ __('Tanpa batas qty') }}`;
            default:
                return '';
            }
        },
        get active_filter_count() {
            let count = 0;
            if (this.filter) count++;
            if (this.aging) count++;
            if (this.limit) count++;
            return count;
        },
        get display_text() {
            if (this.active_filter_count === 0) {
                return '{{ __('Filter') }}';
            } else if (this.active_filter_count === 1) {
                return this.filter_name || this.aging_name || this.limit_name;
            } else {
                return this.active_filter_count + ' {{ __('filter aktif') }}';
            }
        }
    }" class="flex items-center px-4">
    <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'search-filter')" ::class="active_filter_count > 0 ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'">
        <i class="icon-funnel me-3"></i>
        <span x-show="active_filter_count <= 1" x-text="display_text"></span>
        <span x-show="active_filter_count > 1">
            <x-pill color="red" x-text="active_filter_count"></x-pill>
        </span>
    </x-text-button>
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
            <div>
                <label class="block uppercase mb-1 text-xs text-neutral-500">
                    {{ __('Batas qty') }}
                </label>  
                <div>
                    <x-radio x-model="limit" id="limit-under-qty-limit" name="limit-under-qty-limit" value="under-qty-limit">{{ __('Di bawah minimum') }}</x-radio>
                    <x-radio x-model="limit" id="limit-over-qty-limit" name="limit-over-qty-limit" value="over-qty-limit">{{ __('Di atas maksimum') }}</x-radio>
                    <x-radio x-model="limit" id="limit-outside-qty-limit" name="limit-outside-qty-limit" value="outside-qty-limit">{{ __('Di luar batas') }}</x-radio>
                    <x-radio x-model="limit" id="limit-inside-qty-limit" name="limit-inside-qty-limit" value="inside-qty-limit">{{ __('Di dalam batas') }}</x-radio>
                    <x-radio x-model="limit" id="limit-no-qty-limit" name="limit-no-qty-limit" value="no-qty-limit">{{ __('Tanpa batas qty') }}</x-radio>
                </div> 
                <div class="px-5">
                    <x-text-button class="text-xs uppercase font-semibold" type="button" x-on:click="limit = ''; $dispatch('close');" x-show="limit"><span class="text-red-500"><div class="px-1">{{ __('Reset') }}</div></span></x-text-button>
                </div>
            </div>     
        </div>
    </x-modal>   
</div>