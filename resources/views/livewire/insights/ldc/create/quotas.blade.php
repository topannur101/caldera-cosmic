<?php

use Livewire\Volt\Component;
use App\Models\InsLdcQuota;
use App\Models\InsLdcHide;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {

    public array $cmachines = [];
    
    public int $selected_quota_for_form = -1;

    public string $active_tab = 'manage';
    
    // Navigation state
    public string $current_view = 'machines'; // 'machines', 'machine', 'quota'
    public int $selected_machine = 0;
    public $selected_quota_id = 0;
    
    // Form data
    public string $quota_value = '';
    public string $edit_quota_value = '';
    
    // Data
    public array $machine_quotas = [];
    public array $quota_details = [];
    public array $quota_hides = [];
    public array $pinned_machines_data = [];

    public function mount()
    {
        $this->cmachines = session('ins-ldc-cmachines', []);
        $this->loadPinnedMachinesData();
    }

    public function rules()
    {
        return [
            'quota_value' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'edit_quota_value' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
        ];
    }

    public function showMachine($machine)
    {
        $this->selected_machine = $machine;
        $this->current_view = 'machine';
        $this->loadMachineQuotas();
        $this->reset(['quota_value']);
    }

    public function showQuota($quota_id)
    {
        $this->selected_quota_id = $quota_id;
        $this->current_view = 'quota';
        $this->loadQuotaDetails();
    }

    public function backToMachines()
    {
        $this->current_view = 'machines';
        $this->reset(['selected_machine', 'selected_quota_id', 'quota_value', 'edit_quota_value']);
    }

    public function backToMachine()
    {
        $this->current_view = 'machine';
        $this->reset(['selected_quota_id', 'edit_quota_value']);
        $this->loadMachineQuotas();
    }

    public function createQuota()
    {
        $this->validate(['quota_value' => $this->rules()['quota_value']]);

        InsLdcQuota::create([
            'machine' => $this->selected_machine,
            'value' => $this->quota_value,
        ]);

        $this->reset(['quota_value']);
        $this->loadMachineQuotas();
        $this->loadPinnedMachinesData();
    }

    public function updateQuota()
    {
        $this->validate(['edit_quota_value' => $this->rules()['edit_quota_value']]);

        $quota = InsLdcQuota::find($this->selected_quota_id);
        if ($quota) {
            $quota->update(['value' => $this->edit_quota_value]);
            $this->loadQuotaDetails();
            $this->loadPinnedMachinesData();
        }
    }

    public function deleteQuota()
    {
        $quota = InsLdcQuota::find($this->selected_quota_id);
        if ($quota) {
            // Set all associated hides' quota_id to null
            InsLdcHide::where('ins_ldc_quota_id', $quota->id)
                     ->update(['ins_ldc_quota_id' => null]);
            
            $quota->delete();
            $this->backToMachine();
            $this->loadPinnedMachinesData();
        }
    }

    public function removeHideFromQuota($hide_id)
    {
        InsLdcHide::where('id', $hide_id)->update(['ins_ldc_quota_id' => null]);
        $this->loadQuotaDetails();
        $this->loadPinnedMachinesData();
    }

    private function loadMachineQuotas()
    {
        $quotas = InsLdcQuota::where('machine', $this->selected_machine)
                            ->where('created_at', '>=', Carbon::now()->subDay())
                            ->orderBy('created_at', 'desc')
                            ->get();

        $this->machine_quotas = $quotas->map(function ($quota) {
            $total_ab = InsLdcHide::where('ins_ldc_quota_id', $quota->id)->sum('area_ab');
            $progress = $quota->value > 0 ? min(100, ($total_ab / $quota->value) * 100) : 0;
            
            return [
                'id' => $quota->id,
                'value' => $quota->value,
                'created_at' => $quota->created_at,
                'total_ab' => $total_ab,
                'progress' => round($progress, 1),
            ];
        })->toArray();
    }

    private function loadQuotaDetails()
    {
        $quota = InsLdcQuota::find($this->selected_quota_id);
        if (!$quota) return;

        $total_ab = InsLdcHide::where('ins_ldc_quota_id', $quota->id)->sum('area_ab');
        $progress = $quota->value > 0 ? min(100, ($total_ab / $quota->value) * 100) : 0;

        $this->quota_details = [
            'id' => $quota->id,
            'machine' => $quota->machine,
            'value' => $quota->value,
            'created_at' => $quota->created_at,
            'updated_at' => $quota->updated_at,
            'total_ab' => $total_ab,
            'progress' => round($progress, 1),
        ];

        $this->edit_quota_value = (string) $quota->value;

        $this->quota_hides = InsLdcHide::where('ins_ldc_quota_id', $quota->id)
                                     ->select('id', 'code', 'area_ab')
                                     ->get()
                                     ->toArray();
    }

    private function loadPinnedMachinesData()
    {
        $this->pinned_machines_data = [];

        sort($this->cmachines);
                
        foreach ($this->cmachines as $machine) {
            $latest_quota = InsLdcQuota::where('machine', $machine)
                                    ->orderBy('created_at', 'desc')
                                    ->first();
            
            if ($latest_quota) {
                $total_ab = InsLdcHide::where('ins_ldc_quota_id', $latest_quota->id)->sum('area_ab');
                $progress = $latest_quota->value > 0 ? min(100, ($total_ab / $latest_quota->value) * 100) : 0;
                
                $this->pinned_machines_data[] = [
                    'machine' => $machine,
                    'quota_id' => $latest_quota->id,
                    'quota_value' => $latest_quota->value,
                    'total_ab' => $total_ab,
                    'progress' => round($progress, 1),
                ];
            } else {
                $this->pinned_machines_data[] = [
                    'machine' => $machine,
                    'quota_id' => null,
                    'quota_value' => 0,
                    'total_ab' => 0,
                    'progress' => 0,
                ];
            }
        }
    }

    public function togglePin($machine)
    {
        if (in_array($machine, $this->cmachines)) {
            $this->cmachines = array_values(array_filter($this->cmachines, fn($m) => $m !== $machine));
        } else {
            $this->cmachines[] = $machine;
        }
        
        session(['ins-ldc-cmachines' => $this->cmachines]);
        $this->loadPinnedMachinesData();
    }

    public function selectQuotaForForm($quota_id)
    {
        $this->selected_quota_for_form = $quota_id;
        $this->dispatch('set-form-quota', quota_id: $quota_id);
    }

    #[On('hide-saved')]
    public function customReset()
    {
        $this->loadPinnedMachinesData();
        $this->reset(['selected_quota_for_form']);
    }

    

};

?>

<div class="py-6">
    <x-slide-over name="quotas">
        <div class="p-6 overflow-auto relative h-full">
            <div class="flex justify-between items-start mb-6">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Kuota') }}
                </h2>
                <x-text-button type="button" x-on:click="window.dispatchEvent(escKey)">
                    <i class="icon-x"></i>
                </x-text-button>
            </div>
            {{-- Tab Navigation --}}
            <div x-data="{
                    tabSelected: @entangle('active_tab'),
                    tabButtonClicked(tabButton){
                        this.tabSelected = tabButton.dataset.tab;
                    }
                }" class="relative w-full">                
                <div class="relative inline-grid items-center justify-center w-full h-10 grid-cols-2 p-1 text-neutral-500 bg-neutral-200 dark:bg-neutral-900 rounded-full select-none">
                    <button data-tab="manage" @click="tabButtonClicked($el);" type="button" 
                            :class="tabSelected === 'manage' ? 'text-neutral-900 dark:text-neutral-100' : ''"
                            class="relative z-10 inline-flex items-center justify-center w-full h-8 px-3 text-sm font-medium transition-all rounded-full cursor-pointer whitespace-nowrap">
                        {{ __('Kelola') }}
                    </button>
                    <button data-tab="pin" @click="tabButtonClicked($el);" type="button" 
                            :class="tabSelected === 'pin' ? 'text-neutral-900 dark:text-neutral-100' : ''"
                            class="relative z-10 inline-flex items-center justify-center w-full h-8 px-3 text-sm font-medium transition-all rounded-full cursor-pointer whitespace-nowrap">
                        {{ __('Semat') }}
                    </button>
                    
                    {{-- Marker positioned with CSS based on active tab --}}
                    <div class="absolute left-0 h-full p-1 duration-300 ease-out transition-transform" 
                        :class="tabSelected === 'pin' ? 'translate-x-full' : 'translate-x-0'"
                        style="width: calc(50%);">
                        <div class="w-full h-full bg-white dark:bg-neutral-700 rounded-full shadow-sm"></div>
                    </div>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto pt-4" 
                x-data="{ tabSelected: @entangle('active_tab') }">
                <div class="h-full">
                    {{-- Manage Tab --}}
                    <div x-show="tabSelected === 'manage'" x-cloak class="p-1">
                        @if($current_view === 'machines')
                            <div class="flex items-center text-sm text-neutral-500 mb-4">
                                {{ __('Pilih mesin untuk mengelola kuota mesin tersebut') }}
                            </div>
                            {{-- Machine List View --}}
                            <div class="grid grid-cols-2 gap-2">
                                @for($i = 1; $i <= 20; $i++)
                                    <div wire:click="showMachine({{ $i }})" 
                                         class="border border-neutral-200 dark:border-neutral-700 rounded px-4 py-2 cursor-pointer
                                         bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 hover:bg-opacity-10 dark:hover:bg-opacity-10">
                                        <div class="font-medium">{{ 'MC ' . $i }}</div>
                                    </div>
                                @endfor
                            </div>
                        @endif

                        @if($current_view === 'machine')
                            {{-- Machine View --}}
                            <div>
                                {{-- Nav --}}
                                <div class="flex items-center gap-x-2 mb-4 font-medium uppercase text-sm text-neutral-500">
                                    <x-secondary-button type="button" wire:click="backToMachines" 
                                        class="hover:text-neutral-700 dark:hover:text-neutral-300">
                                        <i class="icon-arrow-left"></i>
                                    </x-secondary-button>     
                                    <div class="ml-2">{{ 'MC ' . $selected_machine }}</div>  
                                </div>

                                {{-- Create Quota Form --}}
                                <form wire:submit="createQuota" class="mb-6">
                                    <div class="flex gap-3">
                                        <div class="flex-1">
                                            <x-text-input-suffix wire:model="quota_value" suffix="SF" id="quota_value" type="number" step=".01" autocomplete="off" />
                                        </div>
                                        <x-primary-button type="submit">{{ __('Buat') }}</x-primary-button>
                                    </div>
                                    @error('quota_value')
                                        <x-input-error messages="{{ $message }}" class="mx-3 mt-1" />
                                    @enderror
                                </form>

                                {{-- Quota Cards --}}
                                <div class="space-y-3">
                                    @if(count($machine_quotas) > 0)
                                        @foreach($machine_quotas as $quota)
                                            <div wire:click="showQuota({{ $quota['id'] }})" 
                                                 class="border border-neutral-200 dark:border-neutral-700 rounded p-4 cursor-pointer
                                                        bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 hover:bg-opacity-10 dark:hover:bg-opacity-10">
                                                <div>
                                                    <div class="flex justify-between text-sm mb-2">
                                                        <span>{{ Carbon::parse($quota['created_at'])->format('d M H:i') }}</span>
                                                        <span>{{ number_format($quota['total_ab'], 0) }}/{{ number_format($quota['value'], 0) }}</span>
                                                    </div>
                                                    <div class="w-full bg-neutral-200 rounded-full h-1.5 dark:bg-neutral-700">
                                                        <div class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500" style="width: {{ $quota['progress'] }}%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="border border-neutral-200 dark:border-neutral-700 rounded p-4 text-center text-neutral-500">
                                            {{ __('Tidak ada kuota') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($current_view === 'quota')
                            {{-- Quota Detail View --}}
                            <div>

                                {{-- Nav --}}
                                <div class="flex items-center gap-x-2 mb-4 font-medium uppercase text-sm text-neutral-500">
                                    <x-secondary-button type="button" wire:click="backToMachine" 
                                        class="hover:text-neutral-700 dark:hover:text-neutral-300">
                                        <i class="icon-arrow-left"></i>
                                    </x-secondary-button>     
                                    <div class="ml-2">{{ 'MC ' . $selected_machine }}</div>  
                                    <i class="icon-chevron-right"></i>   
                                    <div>{{ __('Rincian kuota') }}</div>                       
                                </div>

                                {{-- Quota Details --}}
                                <div class="space-y-4 mb-6">
                                    <div class="p-4 border border-neutral-200 dark:border-neutral-700 rounded">
                                        <div class="flex justify-between items-start w-full">
                                            <div class="uppercase text-sm text-neutral-500 mb-4">{{ __('Edit kuota') }}</div>                                            
                                            <x-text-button wire:click="deleteQuota" wire:confirm type="button" 
                                                class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                <i class="icon-trash"></i>
                                            </x-text-button>
                                        </div>
                                        <div>
                                            <div class="flex justify-between text-sm mb-2">
                                                <span>{{ Carbon::parse($quota_details['created_at'])->format('d M H:i') }}</span>
                                                <span>{{ number_format($quota_details['total_ab'], 0) }}/{{ number_format($quota_details['value'], 0) }}</span>
                                            </div>
                                            <div class="w-full bg-neutral-200 rounded-full h-1.5 dark:bg-neutral-700">
                                                <div class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500" style="width: {{ $quota_details['progress'] }}%"></div>
                                            </div>
                                        </div>                                        

                                        {{-- Edit Value Form --}}
                                        <form wire:submit="updateQuota" class="mt-6">
                                            <div class="flex gap-3">
                                                <div class="flex-1">
                                                    <x-text-input-suffix suffix="SF" wire:model="edit_quota_value" type="number" step="0.01" />
                                                </div>
                                                <x-primary-button type="submit">{{ __('Perbarui') }}</x-primary-button>
                                            </div>
                                            @error('edit_quota_value')
                                                <x-input-error messages="{{ $message }}" class="mx-3 mt-1" />
                                            @enderror
                                        </form>
                                    </div>
                                </div>

                                {{-- Associated Hides --}}
                                <div class="p-4 border border-neutral-200 dark:border-neutral-700 rounded">
                                    <h3 class="font-medium text-sm uppercase text-neutral-500 mb-3">{{ __('Kulit terkait') }}</h3>
                                    @if(count($quota_hides) > 0)
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="border-b border-neutral-200 dark:border-neutral-700">
                                                        <th class="text-left py-2">{{ __('Kode') }}</th>
                                                        <th class="text-right py-2">{{ __('AB (SF)') }}</th>
                                                        <th class="w-16"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($quota_hides as $hide)
                                                        <tr class="border-b border-neutral-100 dark:border-neutral-800">
                                                            <td class="py-2">{{ $hide['code'] }}</td>
                                                            <td class="text-right py-2">{{ number_format($hide['area_ab'], 2) }}</td>
                                                            <td class="p-1 text-right">
                                                                <x-text-button type="button" wire:click="removeHideFromQuota({{ $hide['id'] }})" 
                                                                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                                    <i class="icon-x"></i>
                                                                </x-text-button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="text-center text-neutral-500 py-4">
                                            {{ __('Tidak ada kulit terkait') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Pin Tab --}}
                    <div x-show="tabSelected === 'pin'" x-cloak class="p-1">
                        <div class="flex items-center text-sm text-neutral-500 mb-4">
                            {{ __('Sematkan mesin agar dapat muncul di formulir') }}
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            @for($i = 1; $i <= 20; $i++)
                                <div wire:click="togglePin({{ $i }})" 
                                     class="border rounded px-4 py-2 cursor-pointer
                                         bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 hover:bg-opacity-10 dark:hover:bg-opacity-10
                                            {{ in_array($i, $cmachines) ? 'border-caldy-500' : 'border-neutral-200 dark:border-neutral-700' }}">
                                    <div class="flex justify-between items-center">
                                        <div class="font-medium">{{ 'MC ' . $i }}</div>
                                        @if(in_array($i, $cmachines))
                                            <i class="icon-pin text-caldy-500"></i>
                                        @endif
                                    </div>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
            <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
            <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
        </div>
    </x-slide-over>
    <div class="text-center">        
        <x-text-button class="mb-2" x-on:click="$dispatch('open-slide-over', 'quotas')">
            <div class="uppercase text-xs text-neutral-500">{{ __('Kuota') }} <i class="icon-settings-2 ml-1"></i></div>
        </x-text-button>
    </div>     
    <div class="whitespace-nowrap text-nowrap text-xs w-full">
        <input type="radio" name="selected_quota" id="quota-0" 
                value="0" wire:model="selected_quota_for_form"
                @click="$dispatch('set-form-quota', { quota_id: 0 })"
                class="peer hidden" />
        <label for="quota-0"
                class="block h-full cursor-pointer px-4 py-3 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 hover:bg-opacity-10 dark:hover:bg-opacity-10
                        peer-checked:bg-opacity-100 dark:peer-checked:bg-opacity-100 peer-checked:text-white">
            <div>{{ __('Tidak ditentukan') }}</div>
        </label>
        @foreach($pinned_machines_data as $machine_data)
            <div wire:key="pinned-machine-{{ $machine_data['machine'] }}">
                @if($machine_data['quota_id'])
                    <input type="radio" name="selected_quota" id="quota-{{ $machine_data['quota_id'] }}" 
                            value="{{ $machine_data['quota_id'] }}" wire:model="selected_quota_for_form"
                            @click="$dispatch('set-form-quota', { quota_id: {{ $machine_data['quota_id'] }} })"
                            class="peer hidden" />
                    <label for="quota-{{ $machine_data['quota_id'] }}"
                            class="block h-full cursor-pointer px-4 py-3 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 
                                    peer-checked:bg-opacity-100 dark:peer-checked:bg-opacity-100 peer-checked:text-white 
                                    hover:bg-opacity-10 dark:hover:bg-opacity-10">                        
                        <div class="flex justify-between items-center gap-x-3">
                            <div class="grow">{{ 'MC ' . $machine_data['machine'] }}</div>
                            <div>{{ number_format($machine_data['total_ab'], 0) }}/{{ number_format($machine_data['quota_value'], 0) }}</div>
                            <div class="w-20 bg-neutral-200 rounded-full h-1 dark:bg-neutral-700">
                                <div class="bg-white h-1 rounded-full peer-checked:bg-white opacity-60 peer-checked:opacity-100" 
                                        style="width: {{ $machine_data['progress'] }}%"></div>
                            </div>
                        </div>
                    </label>
                @else
                    <div class="text-neutral-500 px-4 py-3">
                        <div class="flex justify-between mb-1">
                            <span>{{ 'MC ' . $machine_data['machine'] }}</span>
                            <span>{{ __('Tidak ada kuota') }}</span>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>