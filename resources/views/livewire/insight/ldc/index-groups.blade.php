<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsLdcGroup;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Caldera;
use Illuminate\Support\Facades\Cache;

new class extends Component {
    public $line;
    public $workdate;
    public $style;
    public $material;

    public $sgid;

    public function rules()
    {
        return [
            'line' => ['required', 'string', 'min:2', 'max:3', 'regex:/^[a-zA-Z]+[0-9]+$/'],
            'workdate' => ['required', 'date'],
            'style' => ['required', 'string', 'min:9', 'max:11', 'regex:/^[a-zA-Z0-9]+-[a-zA-Z0-9]+$/'],
            'material' => ['nullable', 'string', 'max:140'],
        ];
    }

    public function with(): array
    {
        $groups = InsLdcGroup::where('updated_at', '>=', Carbon::now()->subDay())->orderBy('updated_at', 'desc')->get();

        $cached_styles = Cache::get('styles', collect([]));
        $cached_lines = Cache::get('lines', collect([]));
        $cached_materials = Cache::get('materials', collect([]));

        // Filter the records to find a specific group and get the IDs
        $sgid = $groups
            ->filter(function ($group) {
                return $group->line == $this->line && $group->workdate == $this->workdate && $group->style == $this->style && $group->material == $this->material;
            })
            ->first();

        if ($sgid) {
            $this->sgid = $sgid->id;
        } elseif ($this->line && $this->workdate && $this->style) {
            $this->sgid = 0;
        } else {
            $this->reset(['sgid']);
        }

        return [
            'groups' => $groups,
            'cached_styles' => $cached_styles->sortBy('name')->values(),
            'cached_lines' => $cached_lines->sortBy('name')->values(),
            'cached_materials' => $cached_materials->sortBy('name')->values(),
        ];
    }

    #[On('set-hide')]
    public function setGroup($line, $workdate, $style, $material)
    {
        $this->customReset();
        $this->line = $line;
        $this->workdate = $workdate;
        $this->style = $style;
        $this->material = $material;
    }

    public function clean($string): string
    {
        return trim(strtoupper($string));
    }

    public function applyGroup()
    {
        if( !Auth::user() ) {
            $this->js('notyfError("' . __('Kamu belum masuk') . '")');
        } else {
            $this->line = $this->clean($this->line);
            $this->style = $this->clean($this->style);
            $this->material = $this->clean($this->material);
            $this->validate();
            $this->js('window.dispatchEvent(escKey)');
            $this->dispatch('set-group', line: $this->line, workdate: $this->workdate, style: $this->style, material: $this->material);
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
            x-on:click.prevent="$dispatch('open-modal', 'group-set')"><i class="fa fa-plus"></i></x-text-button>
    </div>
    <x-modal name="group-set" maxWidth="sm">
        <form wire:submit="applyGroup" class="p-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Grup baru') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')"><i
                        class="fa fa-times"></i></x-text-button>
            </div>
            <div class="mb-6">
                <div class="mt-6">
                    <label for="gs-hide-workdate"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('WO') }}</label>
                    <x-text-input id="gs-hide-workdate" wire:model="workdate" type="date" />
                    @error('workdate')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
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
    <div class="{{ $sgid === 0 ? '' : 'hidden' }}">
        <input type="radio" name="sgid" id="sgid-0" value="0" wire:model="sgid"
            @click="$dispatch('set-group', { line: '{{ $line }}', workdate: '{{ $workdate }}', style: '{{ $style }}', material: '{{ $material }}' })"
            :checked="{{ $sgid == 0 ? 'true' : 'false' }}" class="peer hidden" />
        <label for="sgid-0"
            class="block h-full cursor-pointer px-6 py-3 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 peer-checked:bg-opacity-100 dark:peer-checked:bg-opacity-100 peer-checked:text-white hover:bg-opacity-20 dark:hover:bg-opacity-20">
            <div class="flex items-center justify-between text-lg">
                <div>{{ $line }} <span
                        class="text-xs uppercase ml-1 mr-2">{{ Carbon::parse($workdate)->format('d M') }}</span></div>
                <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                    fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd" />
                </svg>
            </div>
            <div class="mt-1">{{ $style }}</div>
            <div class="mt-1 max-w-32 truncate text-xs">{{ $material }}</div>
        </label>
    </div>
    @if($groups->isEmpty() && $sgid === null)
        <div>
            <div class="h-full flex items-center">
                <div>{{ __('Tak ada riwayat grup ditemukan') }}</div>
            </div>
        </div>
    @else
        @foreach ($groups as $group)
            <div>
                <input type="radio" name="sgid" id="sgid-{{ $loop->iteration }}" value="{{ $group->id }}"
                    wire:model="sgid"
                    @click="$dispatch('set-group', { line: '{{ $group->line }}', workdate: '{{ $group->workdate }}', style: '{{ $group->style }}', material: '{{ $group->material }}' })"
                    :checked="{{ $group->id == $sgid ? 'true' : 'false' }}" class="peer hidden" />
                <label for="sgid-{{ $loop->iteration }}"
                    class="block h-full cursor-pointer px-6 py-3 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 peer-checked:bg-opacity-100 dark:peer-checked:bg-opacity-100 peer-checked:text-white hover:bg-opacity-10 dark:hover:bg-opacity-10">
                    <div class="flex items-center justify-between text-lg">
                        <div>{{ $group->line }} <span
                                class="text-xs uppercase ml-1 mr-2">{{ Carbon::parse($group->workdate)->format('d M') }}</span>
                        </div>
                    </div>
                    <div class="mt-1">{{ $group->style }}</div>
                    <div class="my-1 max-w-32 truncate text-xs">{{ $group->material }}</div>
                </label>
            </div>
        @endforeach
    @endif
</div>
