<div 
    x-data="{ 
        areas: @entangle('areas'),
        area_ids: @entangle('area_ids').live,
        get areas_facade() {
            
            if (!this.area_ids || this.area_ids.length === 0) {
                return null; // or return '';
            }
            
            const areaIdsAsIntegers = this.area_ids.map(id => parseInt(id, 10));

            if (areaIdsAsIntegers.length === 1) {
                const area = this.areas.find(a => a.id === areaIdsAsIntegers[0]);
                return area ? area.name : null; // or return '';
            }

            const mostRecentAreaId = areaIdsAsIntegers[areaIdsAsIntegers.length - 1];
            const mostRecentArea = this.areas.find(a => a.id === mostRecentAreaId);

            if (mostRecentArea) {
                return `${mostRecentArea.name} +${areaIdsAsIntegers.length - 1}`;
            }
            return null;
        }
    }" class="flex items-center px-4">
    <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'area-selector')" ::class="areas_facade ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'"><i class="icon-house me-3"></i><span x-text="areas_facade ? areas_facade : '{{ __('Area') }}'"></span></x-text-button>
    <x-modal name="area-selector" maxWidth="sm">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    <i class="icon-house me-3"></i>{{ __('Area') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')">
                    <i class="icon-x"></i>
                </x-text-button>
            </div>
            <div class="grid grid-cols-1 gap-y-3 mt-6">
                @foreach($areas as $area)
                    <x-checkbox 
                        id="area-id-{{ $area['id'] }}" x-model="area_ids"
                        value="{{ $area['id'] }}">{{ $area['name'] }}</x-checkbox>                    
                @endforeach
            </div>   
        </div>
    </x-modal>   
</div>