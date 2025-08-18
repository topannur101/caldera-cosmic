<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use Livewire\WithPagination;

use Carbon\Carbon;
use App\InsStc;
use App\Models\InsStcDSum;
use App\Models\InsStcAdjust;
use App\Models\InsStcMachine;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Traits\HasDateRangeFilter;
use Illuminate\Support\Facades\DB;

new class extends Component {

    use WithPagination;
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = '';

    #[Url]
    public string $end_at = '';

    #[Url]
    public $line;

    #[Url]
    public string $position = '';

    public array $lines = [];

    public int $perPage = 10;

    private function getAdjustmentsQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        // Get d-sum records with adjustment data
        $dSumQuery = InsStcDSum::join('ins_stc_machines', 'ins_stc_d_sums.ins_stc_machine_id', '=', 'ins_stc_machines.id')
            ->join('users', 'ins_stc_d_sums.user_id', '=', 'users.id')
            ->select(
                'ins_stc_d_sums.id',
                'ins_stc_d_sums.created_at',
                'ins_stc_machines.line as machine_line',
                'ins_stc_d_sums.position',
                'ins_stc_d_sums.at_values as current_temp',
                'ins_stc_d_sums.at_values as delta_temp',
                'ins_stc_d_sums.sv_values as sv_before',
                'ins_stc_d_sums.svp_values as sv_after',
                \DB::raw('NULL as adjustment_applied'),
                \DB::raw('NULL as adjustment_reason'),
                \DB::raw('NULL as ins_stc_d_sum_id'),
                'users.emp_id as user_emp_id',
                'users.name as user_name',
                'users.photo as user_photo'
            )
            ->selectRaw("'d_sum' as record_type")
            ->whereBetween('ins_stc_d_sums.created_at', [$start, $end])
            ->whereNotNull('ins_stc_d_sums.at_values');

        // Get ins_stc_adjust records
        $adjustQuery = InsStcAdjust::join('ins_stc_d_sums', 'ins_stc_adjusts.ins_stc_d_sum_id', '=', 'ins_stc_d_sums.id')
            ->join('ins_stc_machines', 'ins_stc_d_sums.ins_stc_machine_id', '=', 'ins_stc_machines.id')
            ->join('users', 'ins_stc_d_sums.user_id', '=', 'users.id')
            ->select(
                'ins_stc_adjusts.id',
                'ins_stc_adjusts.created_at',
                'ins_stc_machines.line as machine_line',
                'ins_stc_d_sums.position',
                'ins_stc_adjusts.current_temp',
                'ins_stc_adjusts.delta_temp',
                'ins_stc_adjusts.sv_before',
                'ins_stc_adjusts.sv_after',
                'ins_stc_adjusts.adjustment_applied',
                'ins_stc_adjusts.adjustment_reason',
                'ins_stc_adjusts.ins_stc_d_sum_id',
                'users.emp_id as user_emp_id',
                'users.name as user_name',
                'users.photo as user_photo'
            )
            ->selectRaw("'adjustment' as record_type")
            ->whereBetween('ins_stc_adjusts.created_at', [$start, $end]);

        // Apply filters
        if ($this->line) {
            $dSumQuery->where('ins_stc_machines.line', $this->line);
            $adjustQuery->where('ins_stc_machines.line', $this->line);
        }

        if ($this->position) {
            $dSumQuery->where('ins_stc_d_sums.position', $this->position);
            $adjustQuery->where('ins_stc_d_sums.position', $this->position);
        }

        // Union the queries and order by created_at
        return $dSumQuery->unionAll($adjustQuery)
            ->orderBy('created_at', 'DESC');
    }

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisWeek();
        }

        $this->lines = InsStcMachine::orderBy('line')->get()->pluck('line')->toArray();
    }

    #[On('updated')]
    public function with(): array
    {
        $adjustments = $this->getAdjustmentsQuery()->paginate($this->perPage);

        return [
            'adjustments' => $adjustments,
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }

    public function download()
    {
        $this->js('toast("' . __('Unduhan dimulai...') . '", { type: "success" })');
        $filename = 'adjustments_export_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            __('Waktu'), __('Line'), __('Posisi'), __('Tipe'), __('AT'), __('Delta'), 
            __('SV Awal'), __('SV Akhir'), __('Status'), __('Operator')
        ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $this->getAdjustmentsQuery()->chunk(1000, function ($records) use ($file) {
                foreach ($records as $record) {
                    if ($record->record_type === 'd_sum') {
                        $atValues = json_decode($record->current_temp, true) ?? [0, 0, 0];
                        $svValues = json_decode($record->sv_before, true) ?? [];
                        $svpValues = json_decode($record->sv_after, true) ?? [];
                        
                        fputcsv($file, [
                            $record->created_at,
                            $record->machine_line,
                            InsStc::positionHuman($record->position),
                            'HB',
                            $atValues[1] ?? 0,
                            $atValues[2] ?? 0,
                            implode(',', $svValues),
                            implode(',', $svpValues),
                            __('HB'),
                            $record->user_name . ' - ' . $record->user_emp_id,
                        ]);
                    } else {
                        $svBefore = is_array($record->sv_before) ? $record->sv_before : json_decode($record->sv_before, true) ?? [];
                        $svAfter = is_array($record->sv_after) ? $record->sv_after : json_decode($record->sv_after, true) ?? [];
                        
                        $status = 'Gagal';
                        if ($record->adjustment_applied) {
                            $status = 'Diterapkan';
                        } elseif (str_contains($record->adjustment_reason, 'DRY RUN')) {
                            $status = 'Dry Run';
                        }
                        
                        fputcsv($file, [
                            $record->created_at,
                            $record->machine_line,
                            InsStc::positionHuman($record->position),
                            'AT',
                            $record->current_temp,
                            sprintf('%+.1f', $record->delta_temp),
                            implode(',', $svBefore),
                            implode(',', $svAfter),
                            $status,
                            $record->user_name . ' - ' . $record->user_emp_id,
                        ]);
                    }
                }
            });

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
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
                    <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at"  id="cal-date-end" type="date"></x-text-input>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grid grid-cols-2 lg:flex gap-3">
                <div>
                    <label for="device-line"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                    <x-select class="w-full lg:w-auto" id="device-line" wire:model.live="line">
                        <option value=""></option>
                        @foreach($lines as $line)
                        <option value="{{ $line }}">{{ $line }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label for="device-position"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Posisi') }}</label>
                    <x-select class="w-full lg:w-auto" id="device-position" wire:model.live="position">
                        <option value=""></option>
                        <option value="upper">{{ __('Atas') }}</option>
                        <option value="lower">{{ __('Bawah') }}</option>
                    </x-select>
                </div>
            </div>
            <div class="border-l border-neutral-300 dark:border-neutral-700 mx-2"></div>
            <div class="grow flex justify-between gap-x-2 items-center">
                <div>
                    <div class="px-3">
                        <div wire:loading.class="hidden">{{ $adjustments->total() . ' ' . __('ditemukan') }}</div>
                        <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
                            <div class="relative w-3">
                                <x-spinner class="sm mono"></x-spinner>
                            </div>
                            <div>
                                {{ __('Memuat...') }}
                            </div>
                        </div>
                    </div>
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <x-text-button><i class="icon-ellipsis-vertical"></i></x-text-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="#" wire:click.prevent="download">
                            <i class="icon-download me-2"></i>{{ __('CSV Export') }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
    <div wire:key="modals"> 
        <x-modal name="d_sum-show" maxWidth="3xl">
            <livewire:insights.stc.data.d-sum-show />
        </x-modal>
    </div>
    @if (!$adjustments->count())
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
        <div wire:key="raw-adjustments" class="overflow-auto p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('Line') }}</th>
                        <th>{{ __('Posisi') }}</th>  
                        <th>{{ __('Waktu') }}</th>
                        <th>{{ __('AT') }}</th>
                        <th>{{ __('Delta') }}</th>
                        <th>{{ __('SV Awal') }}</th>
                        <th>{{ __('SV Akhir') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Operator') }}</th>
                    </tr>
                    @foreach ($adjustments as $adjustment)
                        @php
                            if ($adjustment->record_type === 'd_sum') {
                                $atValues = json_decode($adjustment->current_temp, true) ?? [0, 0, 0];
                                $svValues = json_decode($adjustment->sv_before, true) ?? [];
                                $svpValues = json_decode($adjustment->sv_after, true) ?? [];
                                
                                $at = $atValues[1] ?? 0;
                                $delta = $atValues[2] ?? 0;
                                $svAwal = implode(', ', array_map(fn($val) => sprintf('%02d', (int)$val), $svValues));
                                $svAkhir = implode(', ', array_map(fn($val) => sprintf('%02d', (int)$val), $svpValues));
                                $status = '<x-pill color="blue">HB</x-pill>';
                                $targetId = $adjustment->id;
                            } else {
                                $at = $adjustment->current_temp;
                                $delta = sprintf('%+.1f', $adjustment->delta_temp);
                                
                                $svBefore = is_array($adjustment->sv_before) ? $adjustment->sv_before : json_decode($adjustment->sv_before, true) ?? [];
                                $svAfter = is_array($adjustment->sv_after) ? $adjustment->sv_after : json_decode($adjustment->sv_after, true) ?? [];
                                
                                $svAwal = implode(', ', array_map(fn($val) => sprintf('%02d', (int)$val), $svBefore));
                                $svAkhir = implode(', ', array_map(fn($val) => sprintf('%02d', (int)$val), $svAfter));
                                
                                if ($adjustment->adjustment_applied) {
                                    $status = '<x-pill color="green">' . __('Diterapkan') . '</x-pill>';
                                } elseif (str_contains($adjustment->adjustment_reason, 'DRY RUN')) {
                                    $status = '<x-pill color="yellow">Dry Run</x-pill>';
                                } else {
                                    $status = '<x-pill color="red">' . __('Gagal') . '</x-pill>';
                                }
                                $targetId = $adjustment->ins_stc_d_sum_id;
                            }
                        @endphp
                        <tr wire:key="adjustment-tr-{{ $adjustment->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'd_sum-show'); $dispatch('d_sum-show', { id: '{{ $targetId }}'})">
                            <td>{{ $adjustment->machine_line }}</td>
                            <td>{{ InsStc::positionHuman($adjustment->position) }}</td>
                            <td class="font-mono">{{ Carbon::parse($adjustment->created_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ number_format($at, 1) }}°C</td>
                            <td class="{{ $adjustment->record_type === 'adjustment' && $adjustment->delta_temp > 0 ? 'text-red-600' : ($adjustment->record_type === 'adjustment' && $adjustment->delta_temp < 0 ? 'text-blue-600' : '') }}">
                                {{ $delta }}{{ $adjustment->record_type === 'd_sum' ? '' : '°C' }}
                            </td>
                            <td class="font-mono text-xs">{{ $svAwal }}</td>
                            <td class="font-mono text-xs">{{ $svAkhir }}</td>
                            <td>{!! $status !!}</td>
                            <td>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                        @if($adjustment->user_photo ?? false)
                                        <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$adjustment->user_photo }}" />
                                        @else
                                        <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                        @endif
                                    </div>
                                    <div class="text-sm px-2"><span>{{ $adjustment->user_name }}</span></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="flex items-center relative h-16">
            @if (!$adjustments->isEmpty())
                @if ($adjustments->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((adjustments) => {
                                adjustments.forEach(adjustment => {
                                    if (adjustment.isIntersecting) {
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