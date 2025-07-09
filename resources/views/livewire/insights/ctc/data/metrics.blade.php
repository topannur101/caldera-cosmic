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
                            <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-slide-over', 'metrics-info')">
                                <i class="icon-info me-2"></i>{{ __('Penjelasan Metrik') }}
                            </x-dropdown-link>
                            <hr class="border-neutral-300 dark:border-neutral-600" />
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
        <x-slide-over name="metrics-info">
            <div class="p-6 overflow-auto">
                <div class="flex justify-between items-start mb-6">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Penjelasan Metrik') }}
                    </h2>
                    <x-text-button type="button" x-on:click="window.dispatchEvent(escKey)">
                        <i class="icon-x"></i>
                    </x-text-button>
                </div>
                
                <div class="space-y-6 text-sm text-neutral-600 dark:text-neutral-400">
                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('MAE (Mean Average Error)') }}</h3>
                        <p class="mb-2">{{ __('Rata-rata kesalahan dari target ketebalan. Mengukur seberapa dekat hasil produksi dengan target yang ditetapkan.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Rumus: Average of |actual_thickness - target_thickness|') }}<br>
                            {{ __('Unit: mm') }}<br>
                            {{ __('Kualitas: ≤ 1.0 mm = Lulus, > 1.0 mm = Gagal') }}<br>
                            {{ __('Interpretasi: Nilai lebih rendah = akurasi lebih baik') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('SSD (Sample Standard Deviation)') }}</h3>
                        <p class="mb-2">{{ __('Standar deviasi sampel yang mengukur konsistensi ketebalan. Menunjukkan seberapa konsisten hasil produksi.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Unit: mm') }}<br>
                            {{ __('Interpretasi: Nilai lebih rendah = konsistensi lebih baik') }}<br>
                            {{ __('Fungsi: Mengidentifikasi variabilitas dalam proses produksi') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('AVG (Average Thickness)') }}</h3>
                        <p class="mb-2">{{ __('Ketebalan rata-rata aktual yang dihasilkan selama proses produksi.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Unit: mm') }}<br>
                            {{ __('Format: Left side | Right side') }}<br>
                            {{ __('Fungsi: Menunjukkan ketebalan rata-rata pada setiap sisi') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('BAL (Balance)') }}</h3>
                        <p class="mb-2">{{ __('Keseimbangan ketebalan antara sisi kiri dan kanan. Mengukur perbedaan ketebalan antar sisi.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Rumus: Left thickness - Right thickness') }}<br>
                            {{ __('Unit: mm') }}<br>
                            {{ __('Ideal: Mendekati 0 mm (balanced)') }}<br>
                            {{ __('Positif: Sisi kiri lebih tebal, Negatif: Sisi kanan lebih tebal') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('CU (Correction Uptime)') }}</h3>
                        <p class="mb-2">{{ __('Persentase waktu sistem koreksi otomatis dinyalakan. Mengukur seberapa lama sistem auto correction aktif.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Unit: %') }}<br>
                            {{ __('Interpretasi: Nilai tinggi = sistem auto correction lebih lama aktif') }}<br>
                            {{ __('Fungsi: Monitoring utilisasi sistem koreksi otomatis') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('CR (Correction Rate)') }}</h3>
                        <p class="mb-2">{{ __('Tingkat frekuensi koreksi otomatis ditrigger. Seberapa sering koreksi auto dilakukan.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Unit: %') }}<br>
                            {{ __('Fungsi: Mengukur frekuensi aktivasi koreksi otomatis') }}<br>
                            {{ __('Analisis: Membantu evaluasi performa sistem koreksi') }}
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Quality Status (Status Kualitas)') }}</h3>
                        <p class="mb-2">{{ __('Status kualitas batch berdasarkan nilai MAE. Menentukan apakah batch memenuhi standar kualitas.') }}</p>
                        <div class="bg-neutral-50 dark:bg-neutral-800 p-3 rounded text-xs font-mono">
                            {{ __('Pass (Lulus): MAE ≤ 1.0 mm') }}<br>
                            {{ __('Fail (Gagal): MAE > 1.0 mm') }}<br>
                            {{ __('Basis: Threshold MAE untuk menentukan kualitas batch') }}
                        </div>
                    </div>

                    <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4">
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Indikator Warna Kualitas') }}</h3>
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                                <span>{{ __('Hijau: Pass - MAE ≤ 1.0 mm (Kualitas Baik)') }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-red-500 rounded mr-2"></div>
                                <span>{{ __('Merah: Fail - MAE > 1.0 mm (Perlu Perhatian)') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-slide-over>
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
                            @php
                                $avgEval = $metric->avg_evaluation;
                                $maeEval = $metric->mae_evaluation;
                                $ssdEval = $metric->ssd_evaluation;
                                $cuEval = $metric->correction_evaluation;
                            @endphp
                            <td class="font-mono whitespace-nowrap">
                                <div class="flex items-center gap-1">
                                    <i class="{{ ($avgEval['is_good'] ?? false) ? 'icon-circle-check text-green-500' : 'icon-circle-x text-red-500' }}"></i>
                                    <span>{{ number_format($metric->t_avg_left, 2) }} | {{ number_format($metric->t_avg_right, 2) }}</span>
                                </div>
                            </td>
                            <td class="font-mono whitespace-nowrap">
                                <div class="flex items-center gap-1">
                                    <i class="{{ ($maeEval['is_good'] ?? false) ? 'icon-circle-check text-green-500' : 'icon-circle-x text-red-500' }}"></i>
                                    <span>{{ number_format($metric->t_mae_left, 2) }} | {{ number_format($metric->t_mae_right, 2) }}</span>
                                </div>
                            </td>
                            <td class="font-mono whitespace-nowrap">
                                <div class="flex items-center gap-1">
                                    <i class="{{ ($ssdEval['is_good'] ?? false) ? 'icon-circle-check text-green-500' : 'icon-circle-x text-red-500' }}"></i>
                                    <span>{{ number_format($metric->t_ssd_left, 2) }} | {{ number_format($metric->t_ssd_right, 2) }}</span>
                                </div>
                            </td>
                            <td class="font-mono">{{ number_format($metric->t_balance, 2) }}</td>
                            <td class="font-mono whitespace-nowrap">
                                <div class="flex items-center gap-1">
                                    <i class="{{ ($cuEval['is_good'] ?? false) ? 'icon-circle-check text-green-500' : 'icon-circle-x text-red-500' }}"></i>
                                    <span>{{ $metric->correction_uptime }}%</span>
                                </div>
                            </td>
                            <td class="font-mono">{{ $metric->correction_rate }}%</td>
                            <td class="font-mono">{{ $this->calculateDuration($metric->data) }}</td>
                            <td class="font-mono">{{ $metric->created_at }}</td>
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