<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

use App\InsStc;
use App\Models\InsStcDSum;
use App\Models\InsStcDLog;
use App\Models\InsStcMachine;

new class extends Component {

    public array $d_sums = [];

    public int      $machine_id;
    public string   $position = '';
    public array    $machines = [];


    public function mount()
    {
        $this->machines = InsStcMachine::orderBy('line')->get()->toArray();                
    }

    public function rules()
    {
        return [
            'machine_id'    => ['required', 'exists:ins_stc_machines,id'],
            'position'      => ['required', 'in:upper,lower']
        ];
    }

    public function insertDSum()
    {
        $this->validate();

        $latestDSum = InsStcDSum::where('ins_stc_machine_id', $this->machine_id)
            ->where('position', $this->position)
            ->latest('created_at')
            ->first();

        $d_sum_ids = array_column($this->d_sums, 'id');
        
        if ($latestDSum) {
            if (!in_array($latestDSum->id, $d_sum_ids)) {
                
                $this->d_sums[] = [
                    'id'    => $latestDSum->id,
                    'line'  => $latestDSum->ins_stc_machine->line,
                    'positionHuman'  => InsStc::positionHuman($latestDSum->position),
                ];

            } else {
                $this->js('toast("' . __('Hasil ukur untuk line dan posisi tersebut sudah ada') . '", { type: "danger" })');
            }
        }

        $this->dispatch('update');
        $this->reset(['machine_id', 'position']);
        $this->js('window.dispatchEvent(escKey)'); 
    }

    public function removeDSum($id)
    {
        $this->d_sums = array_filter($this->d_sums, function ($d_sum) use ($id) {
            return $d_sum['id'] !== $id;
        });

        $this->d_sums = array_values($this->d_sums);
        $this->dispatch('update');
    }

    #[On('update')]
    public function update()
    {
        $d_sum_ids = array_column($this->d_sums, 'id');
        // Now filter d_log using these d_sum IDs
        $d_sums = InsStcDLog::whereIn('ins_stc_d_sum_id', $d_sum_ids)
            ->get()
            ->groupBy('ins_stc_d_sum_id');

        // Rest of your existing code remains the same
        $this->js(
            "
            let recentsOptions = " . 
            json_encode(InsStc::getStandardZoneChartOptions($d_sums, 100, 100)) .
            ";

            // Render recents chart
            const recentsChartContainer = \$wire.\$el.querySelector('#stc-data-comparison-chart-container');
            recentsChartContainer.innerHTML = '<div id=\"stc-data-comparison-chart\"></div>';
            let recentsChart = new ApexCharts(recentsChartContainer.querySelector('#stc-data-comparison-chart'), recentsOptions);
            recentsChart.render();
            ",
        );        
    }
};

?>

<div>
    <div wire:key="data-selected-d_sums-container" class="px-8 flex items-center gap-x-6 mb-8">

        @if($d_sums)
        <div class="flex gap-3 text-xs uppercase">
            @foreach ($d_sums as $d_sum)
            <div class="bg-white dark:bg-neutral-800 rounded-full px-4 py-2">
                {{ $d_sum['line'] . ' ' . $d_sum['positionHuman'] }}
                <x-text-button type="button" wire:click="removeDSum({{ $d_sum['id'] }})" class="ml-2"><i class="icon-x"></i></x-text-button>
            </div>
            @endforeach
        </div>
        @else
            <div><i class="icon-info me-2"></i>{{ __('Belum ada hasil ukur yang dipilih') }}</div>
        @endif
        <x-secondary-button type="button" x-data="" 
            x-on:click.prevent="$dispatch('open-modal', 'group-set')">{{ __('Sisipkan') }}</x-secondary-button>
    </div>
    <div wire:key="modals">
        <x-modal name="group-set">
            <form wire:submit="insertDSum" class="p-6">
                <div class="flex justify-between items-start mb-6">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Sisipkan hasil ukur') }}
                    </h2>
                    <x-text-button type="button" x-on:click="$dispatch('close')"><i
                            class="icon-x"></i></x-text-button>
                </div>
                <div class="mb-6">
                    <div class="grid grid-cols-2 gap-x-3">
                        <div>
                            <label for="d-log-machine_id"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                            <x-select class="w-full" id="d-log-machine_id" wire:model="machine_id">
                                <option value="0"></option>
                                @foreach ($machines as $machine)
                                    <option value="{{ $machine['id'] }}">{{ $machine['line'] }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div>
                            <label for="d-log-position"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                            <x-select class="w-full" id="d-log-position" wire:model="position">
                                <option value=""></option>
                                <option value="upper">{{ __('Atas') }}</option>
                                <option value="lower">{{ __('Bawah') }}</option>
                            </x-select>
                        </div>
                    </div>
                    @error('machine_id')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                    @error('position')
                        <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                    @enderror
                </div>
                <div class="flex justify-end">
                    <x-primary-button type="submit">{{ __('Terapkan') }}</x-primary-button>
                </div>
            </form>
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target="insertDSum"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target="insertDSum" class="hidden"></x-spinner>
        </x-modal>
    </div>
    <div wire:key="stc-data-comparison">
        <div wire:key="stc-data-comparison-chart" class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 sm:p-6 overflow-hidden">
            <div id="stc-data-comparison-chart-container" class="h-96" wire:key="stc-data-comparison-chart-container" wire:ignore>
            </div>
            <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="insertDSum"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" wire:target.except="insertDSum" class="hidden"></x-spinner>
        </div>  
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript
