<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsLdcGroup;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Caldera;
use Illuminate\Support\Facades\Cache;

new class extends Component {

    public array $groups = [];

    public int $sgid        = 0;
    public string $line     = '';
    public string $workdate = '';
    public string $style    = '';
    public string $material = '';

    public function rules()
    {
        return [
            'line'      => ['required', 'string', 'min:2', 'max:3', 'regex:/^[a-zA-Z]+[0-9]+$/'],
            'workdate'  => ['required', 'date'],
            'style'     => ['required', 'string', 'min:9', 'max:11', 'regex:/^[a-zA-Z0-9]+-[a-zA-Z0-9]+$/'],
            'material'  => ['required', 'string', 'max:140'],
        ];
    }

    public function with(): array
    {
        $this->groups = InsLdcGroup::where('updated_at', '>=', Carbon::now()->subDay())
        ->orderBy('updated_at', 'desc')
        ->get()->toArray();

        $cached_styles      = Cache::get('styles', collect([]));
        $cached_lines       = Cache::get('lines', collect([]));
        $cached_materials   = Cache::get('materials', collect([]));

        return [
            'cached_styles'     => $cached_styles->sortBy('name')->values(),
            'cached_lines'      => $cached_lines->sortBy('name')->values(),
            'cached_materials'  => $cached_materials->sortBy('name')->values(),
        ];
    }


    public function setGroup($group_id)
    {

        $this->line     = $line;
        $this->workdate = $workdate;
        $this->style    = $style;
        $this->material = $material;
        $this->applyGroup();
    }

    public function clean($string): string
    {
        return trim(strtoupper($string));
    }

    #[On('set-hide')]
    public function applyGroup(int $group_id = null)
    {
        $group;
        $this->reset(['sgid']);
        $this->dispatch('set-form-group', group_id: '', material: '');  

        if ($group_id === null) {
            $this->line     = $this->clean($this->line);
            $this->style    = $this->clean($this->style);
            $this->material = $this->clean($this->material);
            $this->validate();

            $group = InsLdcGroup::firstOrCreate([
                'line'      => $this->line,
                'workdate'  => $this->workdate,
                'style'     => $this->style,
                'material'  => $this->material,
            ]); 
        } else {
            $group = InsLdcGroup::find($group_id);
        }

        if ($group) {
            $group->updated_at = now();
            $group->save();
            $this->sgid = $group->id;
            
            $this->dispatch('set-form-group', group_id: $group->id, material: $group->material);        
            $this->js('window.dispatchEvent(escKey)');
            $this->reset(['line', 'workdate', 'style', 'material']);
        }
    }

    #[On('hide-saved')]
    public function customReset()
    {
        $this->reset(['line', 'workdate', 'style', 'material', 'sgid']);
    }
};

?>

<div wire:key="index-groups-container" class="flex items-stretch whitespace-nowrap text-nowrap text-sm min-h-20">
    <div class="p-1">
        <x-text-button type="button" x-data="" class="p-3 h-full text-lg"
            x-on:click.prevent="$dispatch('open-modal', 'group-set')"><i class="icon-plus"></i></x-text-button>
    </div>
    <x-modal name="group-set">
        <form wire:submit="applyGroup" class="p-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Grup baru') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')"><i
                        class="icon-x"></i></x-text-button>
            </div>
            <div class="mb-6">
                <div class="grid grid-cols1 sm:grid-cols-2 mt-6 gap-y-6 gap-x-3">
                    <div>
                        <label for="gs-hide-line"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                        <x-text-input id="gs-hide-line" list="gs-hide-lines" wire:model="line" type="text"
                            autocomplete="off" />
                        @error('line')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                        <datalist id="gs-hide-lines">
                            @foreach ($cached_lines as $cached_line)
                                <option value="{{ $cached_line['name'] }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div>
                        <label for="gs-hide-workdate"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('WO') }}</label>
                        <x-text-input id="gs-hide-workdate" wire:model="workdate" type="date" />
                        @error('workdate')
                            <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                        @enderror
                    </div>
                </div>
                <div class="mt-6">
                    <label for="gs-hide-style"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Style') }}</label>
                    <x-text-input id="gs-hide-style" list="gs-hide-styles" wire:model="style" autocomplete="off"
                        type="text" />
                    @error('style')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                    <datalist id="gs-hide-styles">
                        @foreach ($cached_styles as $cached_style)
                            <option value="{{ $cached_style['name'] }}">
                        @endforeach
                    </datalist>
                </div>

                <div class="mt-6">
                    <label for="gs-hide-material"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Material') }}</label>
                    <x-text-input id="gs-hide-material" list="gs-hide-materials" wire:model="material" type="text"
                        autocomplete="off" />
                    @error('material')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                    <datalist id="gs-hide-materials">
                        @foreach ($cached_materials as $cached_material)
                            <option value="{{ $cached_material['name'] }}">
                        @endforeach
                    </datalist>
                </div>
            </div>
            <div class="flex justify-end">
                <x-primary-button type="submit">{{ __('Terapkan') }}</x-primary-button>
            </div>
        </form>
        <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
        <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
    </x-modal>
    @if(!count($groups) && !$sgid)
        <div>
            <div class="h-full flex items-center">
                <div>{{ __('Tak ada riwayat grup ditemukan') }}</div>
            </div>
        </div>
    @else
        @foreach ($groups as $group)
            <div wire:key="{{ $loop->iteration . $group['id'] }}">
                <input type="radio" name="sgid" id="sgid-{{ $loop->iteration }}" value="{{ $group['id'] }}"
                    wire:model="sgid"
                    @click="$dispatch('set-form-group', { group_id: '{{ $group['id'] }}', material: '{{ $group['material'] }}' })"
                    :checked="{{ $group['id'] == $sgid ? 'true' : 'false' }}" class="peer hidden" />
                <label for="sgid-{{ $loop->iteration }}"
                    class="block h-full cursor-pointer px-6 py-3 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 peer-checked:bg-opacity-100 dark:peer-checked:bg-opacity-100 peer-checked:text-white hover:bg-opacity-10 dark:hover:bg-opacity-10">
                    <div class="flex items-center justify-between text-lg">
                        <div>{{ $group['line'] }} <span
                                class="text-xs uppercase ml-1 mr-2">{{ Carbon::parse($group['workdate'])->format('d M') }}</span>
                        </div>
                    </div>
                    <div class="mt-1">{{ $group['style'] }}</div>
                    <div class="my-1 max-w-32 truncate text-xs">{{ $group['material'] }}</div>
                </label>
            </div>
        @endforeach
    @endif
</div>
