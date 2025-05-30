<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InvCirc;
use Carbon\Carbon;

new class extends Component
{
    public int $stock_id = 0;
    public array $circulation_data = [];
    public string $active_tab = 'quantity';
    public bool $has_data = false;

    #[On('circs-chart')]
    public function loadCirculationData(int $stock_id)
    {
        $this->stock_id = $stock_id;
        
        // Load 1000 most recent approved circulations for this stock
        $circulations = InvCirc::where('inv_stock_id', $stock_id)
            ->where('eval_status', 'approved')
            ->whereIn('type', ['deposit', 'withdrawal'])
            ->with(['inv_stock.inv_curr'])
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get();

        if ($circulations->isEmpty()) {
            $this->has_data = false;
            return;
        }

        $this->has_data = true;
        $this->circulation_data = $this->aggregateByMonth($circulations);

        // Generate both charts
        $this->generateCharts();
    }

    private function aggregateByMonth($circulations)
    {
        $monthlyData = [];

        foreach ($circulations as $circ) {
            $monthKey = Carbon::parse($circ->created_at)->format('Y-m');
            
            if (!isset($monthlyData[$monthKey])) {
                $monthlyData[$monthKey] = [
                    'deposits_qty' => 0,
                    'withdrawals_qty' => 0,
                    'deposits_amount' => 0,
                    'withdrawals_amount' => 0,
                ];
            }

            if ($circ->type === 'deposit') {
                $monthlyData[$monthKey]['deposits_qty'] += abs($circ->qty_relative);
                $monthlyData[$monthKey]['deposits_amount'] += abs($circ->amount ?? 0);
            } elseif ($circ->type === 'withdrawal') {
                $monthlyData[$monthKey]['withdrawals_qty'] += abs($circ->qty_relative);
                $monthlyData[$monthKey]['withdrawals_amount'] += abs($circ->amount ?? 0);
            }
        }

        // Sort by month and limit to reasonable display range
        ksort($monthlyData);
        return array_slice($monthlyData, -24, 24, true); // Last 24 months max
    }

    private function generateCharts()
    {
        $quantityChartOptions = $this->getQuantityChartOptions();
        $amountChartOptions = $this->getAmountChartOptions();

        $this->js('
            setTimeout(function() {
                // Quantity Chart
                const quantityOptions = ' . json_encode($quantityChartOptions) . ';
                
                quantityOptions.options.plugins.tooltip = {
                    callbacks: {
                        label: function(context) {
                            const value = Math.abs(context.parsed.y);
                            const type = context.parsed.y >= 0 ? "Deposit" : "Withdrawal";
                            return type + ": " + value + " unit";
                        },
                        title: function(context) {
                            if (!context[0]) return "";
                            return context[0].label;
                        }
                    }
                };

                const quantityContainer = document.querySelector("#quantity-chart-container");
                if (quantityContainer) {
                    quantityContainer.innerHTML = "";
                    const quantityCanvas = document.createElement("canvas");
                    quantityCanvas.id = "quantity-chart";
                    quantityContainer.appendChild(quantityCanvas);
                    new Chart(quantityCanvas, quantityOptions);
                }

                // Amount Chart
                const amountOptions = ' . json_encode($amountChartOptions) . ';
                
                // Add custom tick callback for amount formatting
                amountOptions.options.scales.y.ticks.callback = function(value) {
                    return new Intl.NumberFormat("id-ID", {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    }).format(Math.abs(value));
                };
                
                amountOptions.options.plugins.tooltip = {
                    callbacks: {
                        label: function(context) {
                            const value = Math.abs(context.parsed.y);
                            const type = context.parsed.y >= 0 ? "Deposit" : "Withdrawal";
                            const formatter = new Intl.NumberFormat("id-ID", {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                            return type + ": USD " + formatter.format(value);
                        },
                        title: function(context) {
                            if (!context[0]) return "";
                            return context[0].label;
                        }
                    }
                };

                const amountContainer = document.querySelector("#amount-chart-container");
                if (amountContainer) {
                    amountContainer.innerHTML = "";
                    const amountCanvas = document.createElement("canvas");
                    amountCanvas.id = "amount-chart";
                    amountContainer.appendChild(amountCanvas);
                    new Chart(amountCanvas, amountOptions);
                }
            }, 100);
        ');
    }

    private function getQuantityChartOptions()
    {
        $labels = [];
        $depositsData = [];
        $withdrawalsData = [];

        foreach ($this->circulation_data as $month => $data) {
            $labels[] = Carbon::createFromFormat('Y-m', $month)->format('M Y');
            $depositsData[] = $data['deposits_qty'];
            $withdrawalsData[] = -$data['withdrawals_qty']; // Negative for withdrawals
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Deposit'),
                        'data' => $depositsData,
                        'backgroundColor' => 'rgba(34, 197, 94, 0.8)', // Green
                        'borderColor' => 'rgba(34, 197, 94, 1)',
                        'borderWidth' => 1,
                    ],
                    [
                        'label' => __('Withdrawal'),
                        'data' => $withdrawalsData,
                        'backgroundColor' => 'rgba(239, 68, 68, 0.8)', // Red
                        'borderColor' => 'rgba(239, 68, 68, 1)',
                        'borderWidth' => 1,
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'interaction' => [
                    'intersect' => false,
                    'mode' => 'index',
                ],
                'scales' => [
                    'x' => [
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ],
                    'y' => [
                        'title' => [
                            'display' => true,
                            'text' => __('Quantity'),
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ],
                        'grid' => [
                            'display' => true,
                            'color' => session('bg') === 'dark' ? '#404040' : '#e5e5e5',
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ]
                ],
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'top',
                        'labels' => [
                            'color' => session('bg') === 'dark' ? '#a3a3a3' : '#525252',
                        ]
                    ],
                    'datalabels' => [
                        'display' => false
                    ]
                ]
            ]
        ];
    }

    private function getAmountChartOptions()
    {
        $labels = [];
        $depositsData = [];
        $withdrawalsData = [];

        foreach ($this->circulation_data as $month => $data) {
            $labels[] = Carbon::createFromFormat('Y-m', $month)->format('M Y');
            $depositsData[] = $data['deposits_amount'];
            $withdrawalsData[] = -$data['withdrawals_amount']; // Negative for withdrawals
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Deposit'),
                        'data' => $depositsData,
                        'backgroundColor' => 'rgba(34, 197, 94, 0.8)', // Green
                        'borderColor' => 'rgba(34, 197, 94, 1)',
                        'borderWidth' => 1,
                    ],
                    [
                        'label' => __('Withdrawal'),
                        'data' => $withdrawalsData,
                        'backgroundColor' => 'rgba(239, 68, 68, 0.8)', // Red
                        'borderColor' => 'rgba(239, 68, 68, 1)',
                        'borderWidth' => 1,
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'interaction' => [
                    'intersect' => false,
                    'mode' => 'index',
                ],
                'scales' => [
                    'x' => [
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ],
                    'y' => [
                        'title' => [
                            'display' => true,
                            'text' => 'USD',
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ],
                        'grid' => [
                            'display' => true,
                            'color' => session('bg') === 'dark' ? '#404040' : '#e5e5e5',
                        ],
                        'ticks' => [
                            'color' => session('bg') === 'dark' ? '#525252' : '#a3a3a3',
                        ]
                    ]
                ],
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'top',
                        'labels' => [
                            'color' => session('bg') === 'dark' ? '#a3a3a3' : '#525252',
                        ]
                    ],
                    'datalabels' => [
                        'display' => false
                    ]
                ]
            ]
        ];
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Data sirkulasi tidak ditemukan') . '", { type: "danger" })');
    }
}

?>

<div class="h-full flex flex-col">
    <div class="p-6">
        <div class="flex justify-between items-start mb-4">
            <h2 class="text-lg font-medium">
                {{ __('Grafik sirkulasi') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')">
                <i class="icon-x"></i>
            </x-text-button>
        </div>

        @if($has_data)
            {{-- Tab Navigation --}}
            <div x-data="{
                    tabSelected: @entangle('active_tab'),
                    tabButtonClicked(tabButton){
                        this.tabSelected = tabButton.dataset.tab;
                    }
                }" class="relative w-full">
                
                <div class="relative inline-grid items-center justify-center w-full h-10 grid-cols-2 p-1 text-neutral-500 bg-neutral-100 dark:bg-neutral-800 rounded-lg select-none">
                    <button data-tab="quantity" @click="tabButtonClicked($el);" type="button" 
                            :class="tabSelected === 'quantity' ? 'text-neutral-900 dark:text-neutral-100' : ''"
                            class="relative z-10 inline-flex items-center justify-center w-full h-8 px-3 text-sm font-medium transition-all rounded-md cursor-pointer whitespace-nowrap">
                        {{ __('Qty') }}
                    </button>
                    <button data-tab="amount" @click="tabButtonClicked($el);" type="button" 
                            :class="tabSelected === 'amount' ? 'text-neutral-900 dark:text-neutral-100' : ''"
                            class="relative z-10 inline-flex items-center justify-center w-full h-8 px-3 text-sm font-medium transition-all rounded-md cursor-pointer whitespace-nowrap">
                        {{ __('Amount') }}
                    </button>
                    
                    {{-- Marker positioned with CSS based on active tab --}}
                    <div class="absolute left-0 h-full duration-300 ease-out transition-transform" 
                        :class="tabSelected === 'amount' ? 'translate-x-full' : 'translate-x-0'"
                        style="width: calc(50% - 4px); margin: 2px;">
                        <div class="w-full h-full bg-white dark:bg-neutral-700 rounded-md shadow-sm"></div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="flex-1 overflow-y-auto" 
         x-data="{ tabSelected: @entangle('active_tab') }">
        @if($has_data)
            <div class="px-6 pb-6 h-full">
                {{-- Quantity Chart --}}
                <div x-show="tabSelected === 'quantity'" class="h-80">
                    <div id="quantity-chart-container" wire:key="quantity-chart-container" wire:ignore class="h-full"></div>
                </div>

                {{-- Amount Chart --}}
                <div x-show="tabSelected === 'amount'" x-cloak class="h-80">
                    <div id="amount-chart-container" wire:key="amount-chart-container" wire:ignore class="h-full"></div>
                </div>
            </div>
        @else
            <div class="flex flex-col gap-y-3 items-center justify-center my-auto text-neutral-400 dark:text-neutral-600 py-12">
                <i class="icon-chart-column-big text-4xl opacity-50"></i>
                <div class="text-lg font-medium text-center">{{ __('Bagan riwayat tidak tersedia') }}</div>
                <div class="text-sm text-center">{{ __('Tidak ada sirkulasi yang sudah disetujui pada unit stok ini') }}</div>
            </div>
        @endif
    </div>

    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>