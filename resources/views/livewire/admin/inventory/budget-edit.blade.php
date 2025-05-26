<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

use App\Models\InvOrderBudget;
use App\Models\InvArea;
use App\Models\InvCurr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

new class extends Component {
    
    public int $id = 0;

    public array $budget = [
        'id' => 0,
        'name' => '',
        'balance' => 0,
        'inv_area_id' => 0,
        'inv_curr_id' => 0,
        'is_active' => true
    ];

    public array $areas = [];
    public string $currency_name = '';

    public function mount()
    {
        $this->areas = InvArea::all()->toArray();
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
                })->ignore($this->budget['id'])
            ],
            'budget.balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'budget.inv_area_id' => ['required', 'exists:inv_areas,id'],
            'budget.is_active' => ['required', 'boolean']
        ];
    }

    public function messages()
    {
        return [
            'budget.name.unique' => __('Nama anggaran sudah ada di area tersebut'),
            'budget.balance.required' => __('Saldo wajib diisi'),
            'budget.balance.min' => __('Saldo tidak boleh negatif'),
            'budget.inv_area_id.required' => __('Area wajib dipilih')
        ];
    }

    #[On('budget-edit')]
    public function loadBudget(int $id)
    {
        $budget = InvOrderBudget::with(['inv_curr'])->find($id);
        if ($budget) {
            $this->budget['id'] = $budget->id;
            $this->budget['name'] = $budget->name;
            $this->budget['balance'] = $budget->balance;
            $this->budget['inv_area_id'] = $budget->inv_area_id;
            $this->budget['inv_curr_id'] = $budget->inv_curr_id;
            $this->budget['is_active'] = (bool) $budget->is_active;
            
            $this->currency_name = $budget->inv_curr->name;
            $this->resetValidation();
        } else {
            $this->handleNotFound();
        }
    }

    public function save()
    {
        Gate::authorize('superuser');
        
        $this->budget['name'] = trim($this->budget['name']);
        $this->validate();

        $budget = InvOrderBudget::find($this->budget['id']);
        if ($budget) {
            $budget->update([
                'name' => $this->budget['name'],
                'balance' => $this->budget['balance'],
                'inv_area_id' => $this->budget['inv_area_id'],
                'is_active' => $this->budget['is_active']
            ]);

            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Anggaran diperbarui') . '", { type: "success" })');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
        }
        
        $this->customReset();
    }



    public function customReset()
    {
        $this->reset(['id', 'budget', 'currency_name']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }
};

?>
<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Anggaran') }}
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
                <x-text-input-t class="px-3" id="budget-currency" value="{{ $currency_name }}" type="text" disabled />
                <div class="px-3 mt-2 text-xs text-neutral-500">{{ __('Mata uang tidak dapat diubah setelah anggaran dibuat') }}</div>
            </div>
            <div>
                <label for="budget-balance" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Saldo') }}</label>
                <x-text-input id="budget-balance" wire:model="budget.balance" type="number" step="0.01" min="0" />
                @error('budget.balance')
                    <x-input-error messages="{{ $message }}" class="px-3 mt-2" />
                @enderror
            </div>
            <div>
                <x-toggle id="budget-is_active" wire:model="budget.is_active">{{ __('Aktif') }}</x-toggle>
            </div>
        </div>  
        <div class="mt-6 flex justify-end">
            <x-primary-button type="submit">
                {{ __('Perbarui') }}
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>