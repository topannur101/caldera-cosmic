<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsStcDSum;
use App\Models\InsStcAdjust;
use App\InsStc;

new class extends Component {
    
    public int $id;

    public array $d_sum = [

        // defaults
        'created_at'        => '',
        'started_at'        => '',
        'ended_at'          => '',
        'speed'             => '',
        'sequence'          => '',
        'position'          => '',
        'sv_values'         => [],
        'formula_id'        => '',
        'sv_used'           => '',
        'is_applied'        => '',
        'target_values'     => [],
        'hb_values'         => [],
        'svp_values'        => [],
        'at_values'         => [0,0,0],
        
        // relationship
        'user' => [
            'photo'         => '',
            'name'          => '',
            'emp_id'        => ''
        ],
        'ins_stc_machine'   => [
            'line'          => ''
        ],
        'ins_stc_device'    => [
            'code'          => '',
            'name'          => '',
        ],
        'ins_stc_d_logs'    => [],
        
        // calculated
        'duration'              => '',
        'latency'               => '',
        'adjustment_friendly'   => '',
        'integrity_friendly'    => '',
    ];

    public $adjustments = [];

    public string $active_tab = 'd-sum';

    public function loadAdjustments()
    {
        $this->adjustments = InsStcAdjust::where('ins_stc_d_sum_id', $this->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($adjustment) {
                $array = $adjustment->toArray();
                $array['formatted_created_at'] = $adjustment->formatted_created_at;
                return $array;
            })
            ->toArray();
    }

    #[On('d_sum-show')]
    public function showDSum(int $id)
    {
        $this->id = $id;
        $dSum =InsStcDSum::with(['user', 'ins_stc_machine', 'ins_stc_d_logs', 'ins_stc_device'])->find($id);

        if ($dSum) {
            $this->d_sum = $dSum->toArray();

            // Decode JSON for specified keys (including at_values)
            foreach (['sv_values', 'target_values', 'hb_values', 'svp_values', 'at_values'] as $key) {
                $this->d_sum[$key] = json_decode($this->d_sum[$key], true);
            }

            // calculated
            foreach (['duration', 'latency', 'adjustment_friendly', 'integrity_friendly'] as $key) {
                $this->d_sum[$key] = $dSum->$key();
            }

            $this->loadAdjustments();

            $this->js("
            const options = " . json_encode(InsStc::getChartJsOptions($this->d_sum['ins_stc_d_logs'], $this->d_sum['sv_values'])) . ";
        
            // Add tooltip configuration
            options.options.plugins.tooltip = {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y + '°C';
                    },
                    title: function(context) {
                        if (!context[0]) return '';
                        const date = new Date(context[0].parsed.x);
                        return date.toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                }
            };
        
            // Render modal chart
            const modalChartContainer = \$wire.\$el.querySelector('#modal-chart-container');
            modalChartContainer.innerHTML = '';
            const modalCanvas = document.createElement('canvas');
            modalCanvas.id = 'modal-chart';
            modalChartContainer.appendChild(modalCanvas);
            new Chart(modalCanvas, options);
        
            // Render print chart
            const printChartContainer = document.querySelector('#print-chart-container');
            printChartContainer.innerHTML = '';
            const printCanvas = document.createElement('canvas');
            printCanvas.id = 'print-chart';
            printChartContainer.appendChild(printCanvas);
            new Chart(printCanvas, options);
        ");
        
        } else {
            $this->handleNotFound();
        }
    }

    public function customReset()
    {
        $this->reset(['id', 'dSum']);
    }

    public function handleNotFound()
    {
        $this->js('$dispatch("close")');
        $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
        $this->dispatch('updated');
    }

    public function printPrepare()
    {
        $this->dispatch('print-prepare', $this->d_sum);
    }

    #[On('print-execute')]
    public function printExecute()
    {
        $this->js("window.print()");
    }

    public function download()
    {
        // Create a unique token for this download request
        $token = md5(uniqid());        

        session()->put('ins_stc_d_logs_token', $token);
        session()->put('ins_stc_d_logs_id', $this->id);
        
        return redirect()->route('download.ins-stc-d-logs', ['token' => $token]);
    }

    public function delete()
    {

        $dSum = InsStcDSum::find($this->id);
        Gate::authorize('manage', $dSum);

        if ($dSum) {
            $dSum->delete();
            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Pengukuran berhasil dihapus') . '", { type: "success" })');
            $this->dispatch('updated');
        } else {
            $this->handleNotFound();
        }

    }

};

?>
<div class="p-6">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Rincian') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
    </div>
    <div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="col-span-2">
                <div class="h-80 overflow-hidden mt-6"
                    id="modal-chart-container" wire:key="modal-chart-container" wire:ignore>
                </div>
                <table class="table table-xs text-sm text-center mt-6">
                    <tr class="text-xs uppercase text-neutral-500 dark:text-neutral-400 border-b border-neutral-300 dark:border-neutral-700">
                        <td></td>
                        <td>1</td>
                        <td>2</td>
                        <td>3</td>
                        <td>4</td>
                        <td>5</td>
                        <td>6</td>
                        <td>7</td>
                        <td>8</td>
                    </tr>
                    <tr>
                        <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('HB') }}</td>
                        @foreach($d_sum['hb_values'] as $hb_value)
                            <td>{{ $hb_value }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('SV') }}</td>
                        @foreach($d_sum['sv_values'] as $sv_value)
                            <td>{{ $sv_value }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __('SVP') }}</td>
                        @foreach($d_sum['svp_values'] as $svp_value)
                            <td>{{ $svp_value }}</td>
                        @endforeach
                    </tr>
                </table>        
            </div>
            <div>
                <div class="grid grid-cols-1 gap-6">
                    <div class="flex gap-6">
                        <div>
                            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Line') }}</div>
                            <div>
                                <span>
                                    {{ $d_sum['ins_stc_machine']['line'] . ' ' . ($d_sum['position'] == 'upper' ? '△' : ($d_sum['position'] == 'lower' ? '▽' : '')) }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Urutan') }}</div>
                            <div>
                                <span class="uppercase">
                                    {{ $d_sum['sequence'] }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('RPM') }}</div>
                            <div>
                                <span class="uppercase">
                                    {{ $d_sum['speed'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Penyetelan') }}</div>
                        <div>
                            
                            {!! $d_sum['adjustment_friendly'] !!}
                        </div>
                    </div>
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Integritas') }}</div>
                        <div>
                            {!! $d_sum['integrity_friendly'] !!}
                        </div>
                    </div>
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Operator') }}</div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 inline-block bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                @if($d_sum['user']['photo'] ?? false)
                                <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$d_sum['user']['photo'] }}" />
                                @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                @endif
                            </div>
                            <div class="text-sm px-2"><span class="text-neutral-500 dark:text-neutral-400">{{ ' ' . $d_sum['user']['emp_id'] . ' ' }}</span><span>{{ $d_sum['user']['name'] }}</span></div>
                        </div>
                    </div>
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Alat ukur') }}</div>
                        <div>
                            <span class="uppercase">
                                {{ $d_sum['ins_stc_device']['code'] . ' | ' .$d_sum['ins_stc_device']['name'] }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Awal') }}</div>
                        <div>
                            <span class="font-mono">
                                {{ $d_sum['started_at'] }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Akhir') }}</div>
                        <div>
                            <span class="font-mono">
                                {{ $d_sum['ended_at'] }}
                            </span>
                        </div>
                    </div>  
                    <div class="flex gap-6">
                        <div>
                            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Durasi') }}</div>
                            <div>
                                <span>
                                    {{ $d_sum['duration'] }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="text-neutral-500 dark:text-neutral-400 text-xs uppercase">{{ __('Latensi') }}</div>
                            <div>
                                <span class="">
                                    {{ $d_sum['latency'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>         
            </div>
        </div>
                {{-- Adjustments Table --}}
        <table class="table table-xs text-sm text-center mt-6">
            <tr class="text-xs uppercase text-neutral-500 dark:text-neutral-400 border-b border-neutral-300 dark:border-neutral-700">
                <td>{{ __('Waktu') }}</td>
                <td>{{ __('Status') }}</td>
                <td>{{ __('Suhu') }}</td>
                <td>{{ __('Delta') }}</td>
                <td>{{ __('SVP') }}</td>
            </tr>
            {{-- Reference row (always first) --}}
            <tr>
                <td class="font-mono">{{ \Carbon\Carbon::parse($d_sum['created_at'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                <td><x-pill>{{ strtoupper(__('Referensi')) }}</x-pill></td>
                <td>{{ number_format($d_sum['at_values'][1] ?? 0, 1) }}°C</td>
                <td>0°C</td>
                <td><span class="font-mono">{{ collect($d_sum['svp_values'])->map(fn($val) => sprintf('%02d', (int)$val))->implode(', ') }}</span></td>
            </tr>
            @foreach($adjustments as $adjustment)
                <tr>
                    <td class="font-mono">{{ $adjustment['formatted_created_at'] }}</td>
                    <td>
                        @if($adjustment['adjustment_applied'])
                            <x-pill color="green">{{ strtoupper(__('Diterapkan')) }}</x-pill>
                        @elseif(str_contains($adjustment['adjustment_reason'], 'DRY RUN'))
                            <x-pill color="yellow">{{ strtoupper(__('Dry Run')) }}</x-pill>
                        @else
                            <x-pill color="red">{{ strtoupper(__('Gagal')) }}</x-pill>
                        @endif
                    </td>
                    <td>{{ number_format($adjustment['current_temp'], 1) }}°C</td>
                    <td>
                        <span class="{{ $adjustment['delta_temp'] > 0 ? 'text-red-600' : ($adjustment['delta_temp'] < 0 ? 'text-blue-600' : '') }}">
                            {{ sprintf('%+.1f', $adjustment['delta_temp']) }}°C
                        </span>
                    </td>
                    <td>
                        @php
                            $svAfter = $adjustment['sv_after'];
                            $svValues = [];
                            
                            if (is_array($svAfter) && count($svAfter) === 8) {
                                $svValues = array_map(fn($val) => sprintf('%02d', (int)$val), $svAfter);
                            }
                        @endphp
                        
                        @if(count($svValues) > 0)
                            <span class="font-mono">{{ implode(', ', $svValues) }}</span>
                        @else
                            <span class="text-neutral-400 font-mono">{{ __('--') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
        @if(count($adjustments) === 0)
            <div class="mt-3 text-center text-neutral-500 dark:text-neutral-400 text-sm">
                {{ __('Tidak ada penyetelan berdasarkan AT') }}
            </div>
        @endif
        <div class="flex gap-x-3 justify-between items-end mt-6">
            <div>
                @can('manage', InsStcDSum::class)
                    <x-text-button type="button" wire:click="delete" wire:confirm="{{ __('Yakin ingin menghapus pengukuran ini?') }}"><i class="icon-trash text-red-500"></i></x-text-button>
                @endcan
            </div>
            <div class="flex justify-between gap-x-3">
                <x-secondary-button type="button" wire:click="download"><i class="icon-download me-2"></i>{{ __('CSV') }}</x-primary-button>
                <x-primary-button type="button" wire:click="printPrepare"><i class="icon-printer me-2"></i>{{ __('Cetak') }}</x-primary-button>
            </div>
        </div>
    </div>
    
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>