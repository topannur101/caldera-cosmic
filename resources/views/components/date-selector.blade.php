@props(['isQuery' => false ])
<div x-data="{
        date_fr: @entangle('date_fr').live,
        date_to: @entangle('date_to').live,
        get date_facade() {
            if (!this.date_fr.trim() || !this.date_to.trim()) {
                return '';
            }
            return `${this.date_fr} — ${this.date_to}`.trim();
        },
        setToday() {
            const today = new Date();
            this.date_fr = this.formatDate(today);
            this.date_to = this.formatDate(today);
        },
        setYesterday() {
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            this.date_fr = this.formatDate(yesterday);
            this.date_to = this.formatDate(yesterday);
        },
        setThisWeek() {
            const today = new Date();
            const dayOfWeek = today.getDay();            
            const daysToMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 2;            
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - daysToMonday);
            startOfWeek.setHours(0, 0, 0, 0);            
            this.date_fr = this.formatDate(startOfWeek);
            this.date_to = this.formatDate(today);
        },
        setLastWeek() {
            const today = new Date();
            const dayOfWeek = today.getDay();
            const daysToLastMonday = dayOfWeek === 0 ? 13 : dayOfWeek + 5;            
            const lastMonday = new Date(today);
            lastMonday.setDate(today.getDate() - daysToLastMonday);
            lastMonday.setHours(0, 0, 0, 0);            
            const lastSunday = new Date(lastMonday);
            lastSunday.setDate(lastMonday.getDate() + 6);            
            this.date_fr = this.formatDate(lastMonday);
            this.date_to = this.formatDate(lastSunday);
        },
        setThisMonth() {
            const today = new Date();
            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 2);            
            this.date_fr = this.formatDate(startOfMonth);
            this.date_to = this.formatDate(today);
        },
        setLastMonth() {
            const today = new Date();
            const startOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 2);
            const endOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 1);            
            this.date_fr = this.formatDate(startOfLastMonth);
            this.date_to = this.formatDate(endOfLastMonth);
        },
        formatDate(date) {
            return date.toISOString().split('T')[0];
        }
    }" class="flex items-center {{ $isQuery ? 'px-4' : '' }}">
    <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'date-selector')" ::class="date_facade ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'"><i class="fa fa-fw fa-calendar me-3"></i><span x-text="date_facade ? date_facade : '{{ $isQuery ? __('Tanggal') : __('Tanggal manapun') }}'"></span></x-text-button>
    <x-modal name="date-selector" maxWidth="sm">
        <div class="grid grid-cols-1 gap-y-6 p-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    <i class="fa fa-fw fa-calendar me-3"></i>{{ __('Tanggal') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')">
                    <i class="fa fa-times"></i>
                </x-text-button>
            </div>
            <div class="grid grid-cols-2 gap-y-6 gap-x-3">        
                <div>
                    <label for="date_fr" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Dari') }}</label>
                    <x-text-input id="date_fr" type="date" x-model="date_fr" />
                </div>                          
                <div>
                    <label for="date_to" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Sampai') }}</label>
                    <x-text-input id="date_to" type="date" x-model="date_to" />
                </div>
            </div>  
            <div class="flex flex-wrap gap-2">
                <x-secondary-button size="sm" type="button" x-on:click="setToday();$dispatch('close');"><div class="px-1">{{ __('Hari ini') }}</div></x-secondary-button>
                <x-secondary-button size="sm" type="button" x-on:click="setYesterday();$dispatch('close');"><div class="px-1">{{ __('Kemarin') }}</div></x-secondary-button>
                <x-secondary-button size="sm" type="button" x-on:click="setThisWeek();$dispatch('close');"><div class="px-1">{{ __('Minggu ini') }}</div></x-secondary-button>
                <x-secondary-button size="sm" type="button" x-on:click="setLastWeek();$dispatch('close');"><div class="px-1">{{ __('Minggu lalu') }}</div></x-secondary-button>
                <x-secondary-button size="sm" type="button" x-on:click="setThisMonth();$dispatch('close');"><div class="px-1">{{ __('Bulan ini') }}</div></x-secondary-button>
                <x-secondary-button size="sm" type="button" x-on:click="setLastMonth();$dispatch('close');"><div class="px-1">{{ __('Bulan lalu') }}</div></x-secondary-button>
            </div>
            <div class="flex justify-end">
                <x-text-button class="text-xs uppercase font-semibold" type="button" wire:click="resetDates" x-on:click="$dispatch('close');" x-show="date_fr || date_to"><span class="text-red-500"><div class="px-1">{{ __('Hapus filter tanggal') }}</div></span></x-text-button>
            </div>
        </div>
    </x-modal>  
</div>