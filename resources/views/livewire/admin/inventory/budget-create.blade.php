<?php

use Livewire\Volt\Component;

use App\Models\InvOrderBudget;
use App\Models\InvArea;
use App\Models\InvCurr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

new class extends Component {

    public array $budget = [
        'name' => '',
        'balance' => 0,
        'inv_area_id' => 0,
        'inv_curr_id' => 0
    ];

    public array $areas = [];
    public array $currencies = [];

    public function mount()
    {
        $this->areas = InvArea::all()->toArray();
        $this->currencies = InvCurr::where('is_active', true)->get()->toArray();
        
        // Set default currency (id = 1)
        $defaultCurrency = InvCurr::find(1);
        if ($defaultCurrency) {
            $this->budget['inv_curr_id'] = $defaultCurrency->id;
        }
    }

    public function rules()
    {
        return [
            'budget.name' => [
                'required', 
                'string', 
                'min:1', 
                'max:128',
                Rule::unique('inv_order_budget', 'name')->where(function ($query) {
                    return $query->where('inv_area_id', $this->budget['inv_area_id']);
                })
            ],
            'budget.balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'budget.inv_area_id' => ['required', 'exists:inv_areas,id'],
            'budget.inv_curr_id' => ['required', 'exists:inv_currs,id']
        ];
    }

    public function messages()
    {
        return [
            'budget.name.unique' => __('Nama anggaran sudah ada di area tersebut'),
            'budget.balance.required' => __('Saldo wajib diisi'),
            'budget.balance.min' => __('Saldo tidak boleh negatif'),
            'budget.inv_area_id.required' => __('Area wajib dipilih'),
            'budget.inv_curr_id.required' => __('Mata uang wajib dipilih')
        ];
    }

    public function save()
    {
        Gate::authorize('superuser');

        $this->budget['name'] = trim($this->budget['name']);
        $this->validate();

        InvOrderBudget::create([
            'name' => $this->budget['name'],
            'balance' => $this->budget['balance'],
            'inv_area_id' => $this->budget['inv_area_id'],
            'inv_curr_id' => $this->budget['inv_curr_id'],
            'is_active' => true
        ]);

        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Anggaran dibuat') . '", { type: "success" })');
        $this->dispatch('updated');

        $this->customReset();
    }

    public function customReset()
    {
        $this->reset(['budget']);
        
        // Reset default currency
        $defaultCurrency = InvCurr::find(1);
        if ($defaultCurrency) {
            $this->budget['inv_curr_id'] = $defaultCurrency->id;
        }
    }
};

?>
<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Anggaran baru') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>
        <div class="grid grid-cols-1 gap-y-6 mt-6">
            <div>
                <label for="budget-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama') }}</label>
                <x-text-input id="budget-name" wire:model="budget.name" type="text" />
                @error('budget.name')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="budget-area" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Area') }}</label>
                <x-select id="budget-area" wire:model="budget.inv_area_id" class="w-full">
                    <option value="">{{ __('Pilih area') }}</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
                    @endforeach
                </x-select>
                @error('budget.inv_area_id')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="budget-currency" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mata uang') }}</label>
                <x-select id="budget-currency" wire:model="budget.inv_curr_id" class="w-full">
                    <option value="">{{ __('Pilih mata uang') }}</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency['id'] }}">{{ $currency['name'] }}</option>
                    @endforeach
                </x-select>
                @error('budget.inv_curr_id')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <label for="budget-balance" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Saldo awal') }}</label>
                <x-text-input id="budget-balance" wire:model="budget.balance" type="number" step="0.01" min="0" />
                @error('budget.balance')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
        </div>  
        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __('Simpan') }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>