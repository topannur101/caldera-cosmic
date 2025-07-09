<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Models\InsCtcMetric;
use App\Models\InsCtcMachine;
use App\Models\InsCtcRecipe;
use App\Models\InsRubberBatch;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Traits\HasDateRangeFilter;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public $machine_id;

    #[Url]
    public string $recipe_name = '';

    #[Url]
    public string $mcs = '';

    #[Url]
    public string $quality_status = '';

    public array $machines = [];
    public int $perPage = 20;

    public function mount()
    {
        if (!$this->start_at || !$this->end_at) {
            $this->setThisWeek();
        }

        $this->machines = InsCtcMachine::orderBy('line')->get()->pluck('line', 'id')->toArray();
    }

    private function getMetricsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsCtcMetric::leftJoin('ins_ctc_machines', 'ins_ctc_metrics.ins_ctc_machine_id', '=', 'ins_ctc_machines.id')
            ->leftJoin('ins_ctc_recipes', 'ins_ctc_metrics.ins_ctc_recipe_id', '=', 'ins_ctc_recipes.id')
            ->leftJoin('ins_rubber_batches', 'ins_ctc_metrics.ins_rubber_batch_id', '=', 'ins_rubber_batches.id')
            ->select(
                'ins_ctc_metrics.*',
                'ins_ctc_metrics.created_at as metric_created_at',
                'ins_ctc_machines.line as machine_line',
                'ins_ctc_recipes.name as recipe_name',
                'ins_ctc_recipes.std_min',
                'ins_ctc_recipes.std_max',
                'ins_rubber_batches.code as batch_code',
                'ins_rubber_batches.mcs as batch_mcs',
                'ins_rubber_batches.color as batch_color'
            )
            ->whereBetween('ins_ctc_metrics.created_at', [$start, $end]);

        if ($this->machine_id) {
            $query->where('ins_ctc_machines.id', $this->machine_id);
        }

        if ($this->recipe_name) {
            $query->where('ins_ctc_recipes.name', 'like', '%' . $this->recipe_name . '%');
        }

        if ($this->mcs) {
            $query->where('ins_rubber_batches.mcs', $this->mcs);
        }

        if ($this->quality_status) {
            if ($this->quality_status === 'pass') {
                $query->where('ins_ctc_metrics.t_mae', '<=', 1.0);
            } else {
                $query->where('ins_ctc_metrics.t_mae', '>', 1.0);
            }
        }

        return $query->orderBy('ins_ctc_metrics.created_at', 'DESC');
    }

    #[On('updated')]
    public function with(): array
    {
        $metrics = $this->getMetricsQuery()->paginate($this->perPage);

        return [
            'metrics' => $metrics,
        ];
    }

    private function calculateDuration($data): string
    {
        if (!$data || !is_array($data) || count($data) < 2) {
            return '00:00:00';
        }

        $firstTimestamp = $data[0][0] ?? null;
        $lastTimestamp = $data[count($data) - 1][0] ?? null;

        if (!$firstTimestamp || !$lastTimestamp) {
            return '00:00:00';
        }

        try {
            $start = new DateTime($firstTimestamp);
            $end = new DateTime($lastTimestamp);
            $interval = $start->diff($end);
            
            return sprintf('%02d:%02d:%02d', 
                $interval->h, 
                $interval->i, 
                $interval->s
            );
        } catch (Exception $e) {
            return '00:00:00';
        }
    }

    private function getStartedAt($data): string
    {
        if (!$data || !is_array($data) || count($data) === 0) {
            return 'N/A';
        }

        $firstTimestamp = $data[0][0] ?? null;
        
        if (!$firstTimestamp) {
            return 'N/A';
        }

        try {
            return (new DateTime($firstTimestamp))->format('H:i:s');
        } catch (Exception $e) {
            return 'N/A';
        }
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }

    public function download($type)
    {
        switch ($type) {
            case 'metrics':
                $this->js('toast("' . __('Unduhan dimulai...') . '", { type: "success" })');
                $filename = 'ctc_metrics_export_' . now()->format('Y-m-d_His') . '.csv';

                $headers = [
                    'Content-type' => 'text/csv',
                    'Content-Disposition' => "attachment; filename=$filename",
                    'Pragma' => 'no-cache',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Expires' => '0',
                ];

                $columns = [
                    __('Batch'), __('Line'), __('Resep'), __('MCS'), __('AVG Kiri'), __('AVG Kanan'),
                    __('MAE Kiri'), __('MAE Kanan'), __('SSD Kiri'), __('SSD Kanan'), __('Balance'),
                    __('CU'), __('CR'), __('Durasi'), __('Mulai'), __('Kualitas')
                ];

                $callback = function () use ($columns) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, $columns);

                    $this->getMetricsQuery()->chunk(1000, function ($metrics) use ($file) {
                        foreach ($metrics as $metric) {
                            fputcsv($file, [
                                $metric->batch_code,
                                $metric->machine_line,
                                $metric->recipe_name,
                                $metric->batch_mcs,
                                number_format($metric->t_avg_left, 2),
                                number_format($metric->t_avg_right, 2),
                                number_format($metric->t_mae_left, 2),
                                number_format($metric->t_mae_right, 2),
                                number_format($metric->t_ssd_left, 2),
                                number_format($metric->t_ssd_right, 2),
                                number_format($metric->t_balance, 2),
                                $metric->correction_uptime . '%',
                                $metric->correction_rate . '%',
                                $this->calculateDuration($metric->data),
                                $this->getStartedAt($metric->data),
                                $metric->t_mae <= 1.0 ? __('Lulus') : __('Gagal'),
                            ]);
                        }
                    });

                    fclose($file);
                };

                return new StreamedResponse($callback, 200, $headers);
        }
    }
};

?>

<div>
    <div class="p-0 sm:p-1 mb-6">
        <div class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div>
                <div class="flex mb-2 text-xs text-neutral-500">
                    <div class="flex">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <x-text-button class="uppercase ml-3">{{ __('Rentang') }}<i class="icon-chevron-down ms-1"></i></x-text-button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="#" wire:click.prevent="setToday">
                                    {{ __('Hari ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setYesterday">
                                    {{ __('Kemarin') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisWeek">
                                    {{ __('Minggu ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastWeek">
                                    {{ __('Minggu lalu') }}
                                </x-dropdown-link>
                                <hr class="border-neutral-300 dark:border-neutral-600" />
                                <x-dropdown-link href="#" wire:click.prevent="setThisMonth">
                                    {{ __('Bulan ini') }}
                                </x-dropdown-link>
                                <x-dropdown-link href="#" wire:click.prevent="setLastMonth">
                                    {{ __('Bulan lalu') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="flex gap-3">
                    <x-text-input wire:model.live="start_at" type="date" class="w-40" />
                    <x-text-input wire:model.live="end_at" type="date" class="w-40" />
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grid grid-cols-2 lg:flex gap-3">
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-select wire:model.live="machine_id" class="w-full lg:w-20">
                        <option value=""></option>
                        @foreach($machines as $id => $line)
                            <option value="{{ $id }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
                </div>
                
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Resep') }}</label>
                    <x-text-input wire:model.live="recipe_name" class="w-full lg:w-32" />
                </div>
                
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('MCS') }}</label>
                    <x-text-input wire:model.live="mcs" class="w-full lg:w-20" />
                </div>
                
                <div>
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kualitas') }}</label>
                    <x-select wire:model.live="quality_status" class="w-full lg:w-24">
                        <option value=""></option>
                        <option value="pass">{{ __('Lulus') }}</option>
                        <option value="fail">{{ __('Gagal') }}</option>
                    </x-select>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-between gap-x-2 items-center">
                <div>
                    <div class="px-3">
                        <div wire:loading.class="hidden">{{ $metrics->total() . ' ' . __('entri') }}</div>
                        <div wire:loading.class.remove="hidden" class="hidden">{{ __('Memuat...') }}</div>
                    </div>
                </div>
                <div class="flex gap-x-2">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <x-text-button><i class="icon-ellipsis-vertical"></i></x-text-button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link href="#" wire:click.prevent="download('metrics')">
                                <i class="icon-download me-2"></i>{{ __('CSV Metrik') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </div>
    <div wire:key="modals">
        <x-modal name="metric-detail" maxWidth="3xl">
            <livewire:insights.ctc.data.metric-detail />
        </x-modal>
    </div>

    @if (!$metrics->count())
        @if (!$start_at || !$end_at)
            <div wire:key="no-range" class="py-20">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="icon-calendar relative"><i
                            class="icon-circle-help absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                </div>
                <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih rentang tanggal') }}
                </div>
            </div>
        @else
            <div wire:key="no-match" class="py-20">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="icon-ghost"></i>
                </div>
                <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
                </div>
            </div>
        @endif
    @else
        <div wire:key="raw-metrics" class="overflow-auto p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('Batch') }}</th>
                        <th>{{ __('Line') }}</th>
                        <th>{{ __('Resep') }}</th>
                        <th>{{ __('MCS') }}</th>
                        <th>{{ __('AVG') }}</th>
                        <th>{{ __('MAE') }}</th>
                        <th>{{ __('SSD') }}</th>
                        <th>{{ __('BAL') }}</th>
                        <th>{{ __('CU') }}</th>
                        <th>{{ __('CR') }}</th>
                        <th>{{ __('Durasi') }}</th>
                        <th>{{ __('Dibuat') }}</th>
                        <th>{{ __('Kualitas') }}</th>
                    </tr>
                    @foreach ($metrics as $metric)
                        <tr wire:key="metric-tr-{{ $metric->id }}" tabindex="0"
                            x-on:click="
                                $dispatch('open-modal', 'metric-detail');
                                $dispatch('metric-detail-load', { id: '{{ $metric->id }}'})"
                            class="cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-700">
                            <td>{{ $metric->batch_code ?? 'N/A' }}</td>
                            <td>{{ $metric->machine_line ?? 'N/A' }}</td>
                            <td class="max-w-32 truncate" title="{{ $metric->recipe_name }}">{{ $metric->recipe_name ?? 'N/A' }}</td>
                            <td>{{ $metric->batch_mcs ?? 'N/A' }}</td>
                            <td class="font-mono">{{ number_format($metric->t_avg_left, 2) }} | {{ number_format($metric->t_avg_right, 2) }}</td>
                            <td class="font-mono">{{ number_format($metric->t_mae_left, 2) }} | {{ number_format($metric->t_mae_right, 2) }}</td>
                            <td class="font-mono">{{ number_format($metric->t_ssd_left, 2) }} | {{ number_format($metric->t_ssd_right, 2) }}</td>
                            <td class="font-mono">{{ number_format($metric->t_balance, 2) }}</td>
                            <td class="font-mono">{{ $metric->correction_uptime }}%</td>
                            <td class="font-mono">{{ $metric->correction_rate }}%</td>
                            <td class="font-mono">{{ $this->calculateDuration($metric->data) }}</td>
                            <td class="font-mono">{{ $metric->created_at }}</td>
                            <td>
                                @if($metric->t_mae <= 1.0)
                                    <span class="inline-flex px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">{{ __('Lulus') }}</span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">{{ __('Gagal') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="flex items-center relative h-16">
            @if (!$metrics->isEmpty())
                @if ($metrics->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((metrics) => {
                                metrics.forEach(metric => {
                                    if (metric.isIntersecting) {
                                        @this.loadMore()
                                    }
                                })
                            })
                            observer.observe(this.$el)
                        }
                    }" x-init="observe"></div>
                    <x-spinner class="sm" />
                @else
                    <div class="mx-auto">{{ __('Tidak ada lagi') }}</div>
                @endif
            @endif
        </div>
    @endif
</div>

@script
<script>
    $wire.$dispatch('updated');
</script>
@endscript