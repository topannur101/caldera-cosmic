<?php

use App\Models\InvArea;
use App\Models\InvCirc;
use App\Models\InvTag;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    public int $area_id = 0;

    public array $tags = [];

    public array $areas = [];

    public string $date_fr = '';

    public string $date_to = '';

    public function mount()
    {
        $user_id = Auth::user()->id;

        if ($user_id === 1) {
            $areas = InvArea::all();
        } else {
            $user = User::find($user_id);
            $areas = $user->inv_areas;
        }

        $this->areas = $areas->toArray();

    }

    public function resetDates()
    {
        $this->reset(['date_fr', 'date_to']);
    }

    #[On('update')]
    public function update()
    {
        $this->js("
            let container = '';
            let canvas = '';

            const amountValue = ".json_encode($this->amountValue()).";
            container = \$wire.\$el.querySelector('#amount-value-container');
            container.innerHTML = '';
            canvas = document.createElement('canvas');
            canvas.id = 'amount-value';
            container.appendChild(canvas);
            new Chart(canvas, amountValue);

            const amountDeposit = ".json_encode($this->amountDepositPercentage()).";
            container = \$wire.\$el.querySelector('#amount-deposit-percentage-container');
            container.innerHTML = '';
            canvas = document.createElement('canvas');
            canvas.id = 'amount-deposit-percentage';
            container.appendChild(canvas);
            new Chart(canvas, amountDeposit);

            const amountWithdrawal = ".json_encode($this->amountWithdrawalPercentage()).";
            container = \$wire.\$el.querySelector('#amount-withdrawal-percentage-container');
            container.innerHTML = '';
            canvas = document.createElement('canvas');
            canvas.id = 'amount-withdrawal-percentage';
            container.appendChild(canvas);
            new Chart(canvas, amountWithdrawal);
      ");

    }

    public function updated()
    {
        $this->update();
    }

    public function amountDepositPercentage()
    {
        $tags = InvTag::whereHas('inv_items', function($query) {
            $query->where('inv_area_id', $this->area_id);
        })->get();
    
        $totalDepositAmount = InvCirc::whereHas('inv_item', function($query) {
            $query->where('inv_area_id', $this->area_id);
        })->where('type', 'deposit')->where('eval_status', 'approved')->whereBetween('updated_at', [$this->date_fr, $this->date_to])->sum('amount');
    
        $data = [
            'type' => 'pie',
            'data' => [
                'labels' => [],
                'datasets' => [
                    [
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'right',
                     ],
                    'datalabels' => [
                        'anchor' => 'center',
                        'align' => 'center',
                        'color' => 'white',
                        'font' => [
                            'weight' => 'bold',
                        ],
                        'formatter' => function($value) {
                            return $value;
                        },
                    ],
                ],
            ],
        ];
    
        foreach ($tags as $tag) {
            $depositAmount = InvCirc::whereHas('inv_item', function($query) use ($tag) {
                $query->where('inv_area_id', $this->area_id)->whereHas('inv_tags', function($query) use ($tag) {
                    $query->where('id', $tag->id);
                });
            })->where('type', 'deposit')->where('eval_status', 'approved')->whereBetween('updated_at', [$this->date_fr, $this->date_to])->sum('amount');
    
            $data['data']['labels'][] = $tag->name;
            $data['data']['datasets'][0]['data'][] = round(($depositAmount / ($totalDepositAmount ?: 1) * 100), 1);
            $data['data']['datasets'][0]['backgroundColor'][] = '#' . substr(md5(rand()), 0, 6); // Random color
        }
    
        // Add "No Tag" series
        $depositAmountNoTag = InvCirc::whereHas('inv_item', function($query) {
            $query->where('inv_area_id', $this->area_id)->doesntHave('inv_tags');
        })->where('type', 'deposit')->where('eval_status', 'approved')->whereBetween('updated_at', [$this->date_fr, $this->date_to])->sum('amount');
    
        $data['data']['labels'][] = 'No Tag';
        $data['data']['datasets'][0]['data'][] = round(($depositAmountNoTag / ($totalDepositAmount ?: 1) * 100), 1);
        $data['data']['datasets'][0]['backgroundColor'][] = '#' . substr(md5(rand()), 0, 6); // Random color
    
        return $data;
    }

    public function amountWithdrawalPercentage()
    {
        $tags = InvTag::whereHas('inv_items', function($query) {
            $query->where('inv_area_id', $this->area_id);
        })->get();
    
        $totalWithdrawalAmount = InvCirc::whereHas('inv_item', function($query) {
            $query->where('inv_area_id', $this->area_id);
        })->where('type', 'withdrawal')->where('eval_status', 'approved')->whereBetween('updated_at', [$this->date_fr, $this->date_to])->sum('amount');
    
        $data = [
            'type' => 'pie',
            'data' => [
                'labels' => [],
                'datasets' => [
                    [
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'right',
                     ],
                    'datalabels' => [
                        'anchor' => 'center',
                        'align' => 'center',
                        'color' => 'white',
                        'font' => [
                            'weight' => 'bold',
                        ],
                        'formatter' => function($value) {
                            return $value;
                        },
                    ],
                ],
            ],
        ];
    
        foreach ($tags as $tag) {
            $withdrawalAmount = InvCirc::whereHas('inv_item', function($query) use ($tag) {
                $query->where('inv_area_id', $this->area_id)->whereHas('inv_tags', function($query) use ($tag) {
                    $query->where('id', $tag->id);
                });
            })->where('type', 'withdrawal')->where('eval_status', 'approved')->whereBetween('updated_at', [$this->date_fr, $this->date_to])->sum('amount');
    
            $data['data']['labels'][] = $tag->name;
            $data['data']['datasets'][0]['data'][] = round(($withdrawalAmount / ($totalWithdrawalAmount ?: 1) * 100), 1);
            $data['data']['datasets'][0]['backgroundColor'][] = '#' . substr(md5(rand()), 0, 6); // Random color
        }
    
        // Add "No Tag" series
        $withdrawalAmountNoTag = InvCirc::whereHas('inv_item', function($query) {
            $query->where('inv_area_id', $this->area_id)->doesntHave('inv_tags');
        })->where('type', 'withdrawal')->where('eval_status', 'approved')->whereBetween('updated_at', [$this->date_fr, $this->date_to])->sum('amount');
    
        $data['data']['labels'][] = 'No Tag';
        $data['data']['datasets'][0]['data'][] = round(($withdrawalAmountNoTag / ($totalWithdrawalAmount ?: 1) * 100), 1);
        $data['data']['datasets'][0]['backgroundColor'][] = '#' . substr(md5(rand()), 0, 6); // Random color
    
        return $data;
    }
    
    public function amountValue()
    {
        $tags = InvTag::whereHas('inv_items', function($query) {
            $query->where('inv_area_id', $this->area_id);
        })->get();
    
        $data = [
            'type' => 'bar',
            'data' => [
                'labels' => $tags->pluck('name')->toArray(),
                'datasets' => [
                    [
                        'label' => __('Pengambilan'),
                        'data' => [],
                        'backgroundColor' => '#FF6384', // Color for withdrawals
                    ],
                    [
                        'label' => __('Penambahan'),
                        'data' => [],
                        'backgroundColor' => '#36A2EB', // Color for deposits
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'indexAxis' => 'y', // This makes the chart horizontal
                'scales' => [
                    'x' => [
                        'stacked' => true,
                        'beginAtZero' => true,
                    ],
                    'y' => [
                        'stacked' => true,
                        'beginAtZero' => true,
                    ],
                ],
                'plugins' => [
                    'legend' => [
                        'display' => true, // This shows the legend
                    ],
                    'datalabels' => [
                        'anchor' => 'center',
                        'align' => 'center',
                        'color' => 'white',
                        'font' => [
                            'weight' => 'bold',
                        ],
                        'formatter' => function($value) {
                            return $value;
                        },
                    ],
                ],
            ],
        ];
    
        foreach ($tags as $tag) {
            $withdrawalAmount = InvCirc::whereHas('inv_item', function($query) use ($tag) {
                $query->where('inv_area_id', $this->area_id)->whereHas('inv_tags', function($query) use ($tag) {
                    $query->where('id', $tag->id);
                });
            })->where('type', 'withdrawal')->where('eval_status', 'approved')->whereBetween('updated_at', [$this->date_fr, $this->date_to])->sum('amount');
    
            $depositAmount = InvCirc::whereHas('inv_item', function($query) use ($tag) {
                $query->where('inv_area_id', $this->area_id)->whereHas('inv_tags', function($query) use ($tag) {
                    $query->where('id', $tag->id);
                });
            })->where('type', 'deposit')->where('eval_status', 'approved')->whereBetween('updated_at', [$this->date_fr, $this->date_to])->sum('amount');
    
            $data['data']['datasets'][0]['data'][] = $withdrawalAmount;
            $data['data']['datasets'][1]['data'][] = $depositAmount;
        }
    
        // Add "No Tag" series
        $withdrawalAmountNoTag = InvCirc::whereHas('inv_item', function($query) {
            $query->where('inv_area_id', $this->area_id)->doesntHave('inv_tags');
        })->where('type', 'withdrawal')->where('eval_status', 'approved')->whereBetween('updated_at', [$this->date_fr, $this->date_to])->sum('amount');
    
        $depositAmountNoTag = InvCirc::whereHas('inv_item', function($query) {
            $query->where('inv_area_id', $this->area_id)->doesntHave('inv_tags');
        })->where('type', 'deposit')->where('eval_status', 'approved')->whereBetween('updated_at', [$this->date_fr, $this->date_to])->sum('amount');
    
        $data['data']['labels'][] = __('Tanpa tag');
        $data['data']['datasets'][0]['data'][] = $withdrawalAmountNoTag;
        $data['data']['datasets'][1]['data'][] = $depositAmountNoTag;
    
        return $data;
    }


};

?>



<x-slot name="title">{{ __('Ringkasan sirkulasi') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Ringkasan sirkulasi') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-700 dark:text-neutral-200">
    @vite(['resources/js/apexcharts.js'])
    <div class="flex flex-col lg:flex-row w-full bg-white dark:bg-neutral-800 divide-x-0 divide-y lg:divide-x lg:divide-y-0 divide-neutral-200 dark:divide-neutral-700 shadow sm:rounded-lg lg:rounded-full py-0 lg:py-2 mb-6">
        <div class="px-8 py-3 lg:py-0">
            <x-select wire:model.live="area_id">
                <option value="0"></option>
                @foreach ($areas as $area)
                    <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
                @endforeach
            </x-select>
        </div>
        <div class="flex items-center gap-x-4 p-4 lg:py-0 ">
            <x-date-selector isQuery="true" class="text-xs font-semibold uppercase" />
        </div>
        <div wire:loading.class.remove="hidden" class="px-8 py-3 lg:py-0 flex gap-3 hidden items-center">
            <div class="relative w-3">
                <x-spinner class="sm mono"></x-spinner>
            </div>
            <div>
                {{ __('Melakukan kalkulasi...') }}
            </div>
        </div>      
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Persentase amount penambahan per kategori') }}</label>
            <div class="flex gap-x-3">
                <i class="fa fa-fw fa-plus text-green-500 text-3xl my-auto"></i>
                <div 
                    wire:ignore
                    id="amount-deposit-percentage-container" 
                    class="h-32 overflow-hidden"
                    wire:key="amount-deposit-container">
                </div>  
            </div>
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Persentase amount pengambilan per kategori') }}</label>
            <div class="flex gap-x-3">
                <i class="fa fa-fw fa-minus text-red-500 text-3xl my-auto"></i>
                <div 
                    wire:ignore
                    id="amount-withdrawal-percentage-container" 
                    class="h-32 overflow-hidden"
                    wire:key="amount-withdrawal-container">
                </div>  
            </div>
        </div>
    </div>
    <div class="mt-4">
        <div class="col-span-2 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Jumlah amount per kategori') }}</label>
            <div 
                wire:ignore
                id="amount-value-container" 
                class="overflow-hidden"
                wire:key="amount-value-container">
            </div>  
        </div>
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript