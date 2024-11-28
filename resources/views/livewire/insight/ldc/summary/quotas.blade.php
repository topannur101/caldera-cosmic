<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

use Carbon\Carbon;
use App\Models\InsLdcHide;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Traits\HasDateRangeFilter;

new #[Layout('layouts.app')] 
class extends Component {

    use WithPagination;
    use HasDateRangeFilter;

    #[Url]
    public $start_at;

    #[Url]
    public $end_at;

    #[Url]
    public bool $is_workdate = false;

    #[Url]
    public $code;

    #[Url]
    public $style;

    #[Url]
    public $line;

    #[Url]
    public $material;

    public $perPage = 20;

    public $sort = 'updated';

    private function getHidesQuery()
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $query = InsLdcHide::join('ins_ldc_groups', 'ins_ldc_hides.ins_ldc_group_id', '=', 'ins_ldc_groups.id')
        ->join('users', 'ins_ldc_hides.user_id', '=', 'users.id')
        ->select(
        'ins_ldc_hides.*',
        'ins_ldc_hides.updated_at as hide_updated_at',
        'ins_ldc_groups.workdate as group_workdate',
        'ins_ldc_groups.style as group_style',
        'ins_ldc_groups.line as group_line',
        'ins_ldc_groups.material as group_material',
        'users.emp_id as user_emp_id',
        'users.name as user_name');

        if (!$this->is_workdate) {
            $query->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
        } else {
            $query->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
        }

        if ($this->code) {
            $query->where('ins_ldc_hides.code', 'LIKE', '%' . $this->code . '%');
        }

        if ($this->style) {
            $query->where('ins_ldc_groups.style', 'LIKE', '%' . $this->style . '%');
        }

        if ($this->line) {
            $query->where('ins_ldc_groups.line', 'LIKE', '%' . $this->line . '%');
        }

        if ($this->material) {
            $query->where('ins_ldc_groups.material', 'LIKE', '%' . $this->material . '%');
        }


        switch ($this->sort) {
            case 'updated':
                if (!$this->is_workdate) {
                    $query->orderBy('ins_ldc_hides.updated_at', 'DESC');
                } else {
                    $query->orderBy('ins_ldc_groups.workdate', 'DESC');
                }
                break;
            
            case 'sf_low':
            $query->orderBy(DB::raw('ins_ldc_hides.area_vn + ins_ldc_hides.area_ab + ins_ldc_hides.area_qt'), 'ASC');
                break;

            case 'sf_high':
            $query->orderBy(DB::raw('ins_ldc_hides.area_vn + ins_ldc_hides.area_ab + ins_ldc_hides.area_qt'), 'DESC');
                break;
        }

        return $query;
    }

    public function mount()
    {
        if(!$this->start_at || !$this->end_at)
        {
            $this->setThisWeek();
        }
    }

    public function with(): array
    {
        $query = $this->getHidesQuery();
        $hides = $query->paginate($this->perPage);
        $sum_area_vn = $query->sum('area_vn');
        $sum_area_ab = $query->sum('area_ab');
        $sum_area_qt = $query->sum('area_qt');

        $query_g0 = $this->getHidesQuery();
        $query_g1 = $this->getHidesQuery();
        $query_g2 = $this->getHidesQuery();
        $query_g3 = $this->getHidesQuery();
        $query_g4 = $this->getHidesQuery();
        $query_g5 = $this->getHidesQuery();

        $count_g0 = $query_g0->whereNull('grade')->count();
        $count_g1 = $query_g1->where('grade', 1)->count();
        $count_g2 = $query_g2->where('grade', 2)->count();
        $count_g3 = $query_g3->where('grade', 3)->count();
        $count_g4 = $query_g4->where('grade', 4)->count();
        $count_g5 = $query_g5->where('grade', 5)->count();

        $diff_area = 0;
        $defect_area = 0;

        if ($sum_area_vn > 0) {
            $diff_area = number_format((($sum_area_vn - $sum_area_ab) / $sum_area_vn) * 100, 1);
            $defect_area = number_format((($sum_area_vn - $sum_area_qt) / $sum_area_vn) * 100, 1);
        }

        return [
            'hides' => $hides,
            'sum_area_vn'   => $sum_area_vn,
            'sum_area_ab'   => $sum_area_ab,
            'sum_area_qt'   => $sum_area_qt,
            'diff_area'     => $diff_area,
            'defect_area'   => $defect_area,
            'count_g0'      => $count_g0,
            'count_g1'      => $count_g1,
            'count_g2'      => $count_g2,
            'count_g3'      => $count_g3,
            'count_g4'      => $count_g4,
            'count_g5'      => $count_g5
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }

    public function download()
    {
        $filename = 'ldc_hides_export_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            __('Diperbarui'), 
            __('Kode'),
            __('VN'),
            __('AB'),
            __('QT'),
            __('G'),
            __('S'),
            __('WO'),
            __('Style'),
            __('Line'),
            __('Material'),
            __('NIK'),
            __('Nama'),
        ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $this->getHidesQuery()->chunk(1000, function ($hides) use ($file) {
                foreach ($hides as $hide) {
                    fputcsv($file, [
                        $hide->hide_updated_at,
                        $hide->code,
                        $hide->area_vn,
                        $hide->area_ab,
                        $hide->area_qt,
                        $hide->grade ?? '',
                        $hide->shift,
                        $hide->group_workdate,
                        $hide->group_style,
                        $hide->group_line,
                        $hide->group_material ?? '',
                        $hide->user_emp_id,
                        $hide->user_name,
                    ]);
                }
            });

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
};

?>

<div>
    <div x-data="{ show: false }" class="p-0 sm:p-1 mb-6">
        <div x-show="!show" class="flex flex-col lg:flex-row gap-3 w-full bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <div>
                <div class="flex mb-2 text-xs text-neutral-500">
                    <div class="flex">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <x-text-button class="uppercase ml-3">{{ __('Rentang') }}<i class="fa fa-fw fa-chevron-down ms-1"></i></x-text-button>
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
                <div class="grid gap-3">
                    <x-text-input wire:model.live="start_at" id="cal-date-start" type="date"></x-text-input>
                    <x-text-input wire:model.live="end_at"  id="cal-date-end" type="date"></x-text-input>
                    <div class="px-3">
                        <x-checkbox id="hides_is_workdate" wire:model.live="is_workdate"
                            value="is_workdate"><span class="uppercase text-xs">{{ __('Workdate') }}</span></x-checkbox>
                    </div>
                </div>
            </div>
            <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-6 lg:my-0"></div>
            <div class="grid gap-3">
                <div class="flex gap-3">
                    <div class="w-full lg:w-32">
                        <label for="hides-machine"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mesin') }}</label>
                        <x-text-input id="hides-machine" wire:model.live="style" type="number" />
                    </div>
                    <div class="w-full lg:w-32">
                        <label for="hides-line"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Line') }}</label>
                        <x-text-input id="hides-line" wire:model.live="line" type="text" />
                    </div>
                    <div class="w-full lg:w-32">
                        <label for="hides-style"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Style') }}</label>
                        <x-text-input id="hides-style" wire:model.live="style" type="text" />
                    </div>
                </div>
                <div>
                    <table class="text-sm">
                        <tr>
                            <td class="text-neutral-500">{{ __('Jatah') . ': ' }}</td>
                            <td class="px-2">{{ 0 }}</td>
                        </tr>
                        <tr>
                            <td class="text-neutral-500">{{ __('Terpenuhi') . ': ' }}</td>
                            <td class="px-2">{{ 0 }}</td>
                        </tr>
                        <tr>
                            <td class="text-neutral-500">{{ __('Tersisa') . ': ' }}</td>
                            <td class="px-2">{{ 0 }}</td>
                        </tr>
                    </table>
                </div>
            </div>            
            <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-6 lg:my-0"></div>
            <div class="flex flex-row lg:flex-col">                
                <div class="mb-1">
                <label for="hides-sort"
                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Urut') }}</label>
                    <x-select id="hides-sort" wire:model.live="sort">
                        <option value="updated">{{ __('Diperbarui') }}</option>
                        <option value="sf_low">{{ __('SF Terkecil') }}</option>
                        <option value="sf_high">{{ __('SF Terbesar') }}</option>
                    </x-select>
                </div>
                <div class="grow"></div>
                <div class="px-3" wire:loading.class="hidden">{{ $hides->total() . ' ' . __('ditemukan') }}</div>
                <div wire:loading.class.remove="hidden" class="flex text-center gap-3 hidden">
                    <div class="relative w-3">
                        <x-spinner class="sm white"></x-spinner>
                    </div>
                    <div>
                        {{ __('Memuat...') }}
                    </div>
                </div>
            </div>
            <div class="border-t border-l border-neutral-300 dark:border-neutral-700 mx-0 my-6 lg:mx-6 lg:my-0"></div>
            <div class="grow flex justify-between gap-x-2 items-center">
                <div class="flex w-full justify-center">
                    <x-primary-button type="button" x-on:click="show = !show">{{ __('Buat jatah') }}</x-primary-button>
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <x-text-button><i class="fa fa-fw fa-ellipsis-v"></i></x-text-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="#" x-on:click.prevent="$dispatch('open-modal', 'raw-stats-info')">
                            <i class="fa fa-fw me-2"></i>{{ __('Statistik ')}}
                        </x-dropdown-link>
                        <hr
                            class="border-neutral-300 dark:border-neutral-600" />
                        <x-dropdown-link href="#" wire:click.prevent="download">
                            <i class="fa fa-fw fa-download me-2"></i>{{ __('Unduh sebagai CSV') }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
        <div x-show="show" x-cloak class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
            <div id="ldc-create-groups" class="border-b border-neutral-100 dark:border-neutral-700 overflow-x-auto">
                <livewire:insight.ldc.create.groups />
            </div>
            <div>
                <div class="max-w-xl mx-auto px-6 py-8">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="quota-machine"
                            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mesin') }}</label>
                            <x-text-input id="quota-machine" type="number" step="1" />
                        </div>
                        <div>
                            <label for="quota-value"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Jatah') }}</label>
                            <x-text-input-suffix suffix="SF" id="quota-value" type="number" step=".01" autocomplete="off" />
                        </div>
                    </div>
                    <div class="flex w-full justify-between mt-6">
                        <x-secondary-button type="button" x-on:click="show = !show">{{ __('Selesai') }}</x-secondary-button>
                        <x-primary-button type="button" x-on:click="show = !show">{{ __('Simpan') }}</x-primary-button>
                    </div>
                </div>
            </div>      
        </div>
    </div>
    <div wire:key="modals"> 
        <x-modal name="raw-stats-info">
            <div class="p-6">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __('Statistik data mentah') }}
                </h2>
                <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Belum ada informasi statistik yang tersedia.') }}
                </p>
                <div class="mt-6 flex justify-end">
                    <x-primary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Paham') }}
                    </x-primary-button>
                </div>
            </div>
        </x-modal> 
        <x-modal name="hide-edit" maxWidth="sm">
            <livewire:insight.ldc.summary.hide-edit />
        </x-modal>
    </div>
    @if (!$hides->count())
        @if (!$start_at || !$end_at)
            <div wire:key="no-range" class="py-20">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="fa fa-calendar relative"><i
                            class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                </div>
                <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih rentang tanggal') }}
                </div>
            </div>
        @else
            <div wire:key="no-match" class="py-20">
                <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                    <i class="fa fa-ghost"></i>
                </div>
                <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
                </div>
            </div>
        @endif
    @else
        <div wire:key="raw-hides" class="overflow-auto p-0 sm:p-1">
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg table">
                <table class="table table-sm text-sm table-truncate text-neutral-600 dark:text-neutral-400">
                    <tr class="uppercase text-xs">
                        <th>{{ __('Diperbarui') }}</th>
                        <th>M</th>
                        <th>{{ __('WO') }}</th>
                        <th>{{ __('Style') }}</th>
                        <th>{{ __('Line') }}</th>
                        <th>{{ __('Jatah') }}</th>
                        <th>{{ __('Terpenuhi')}}</th>
                        <th>{{ __('Tersisa') }}</th>
                        <th>{{ __('Persentase') }}</th>
                        <th>{{ __('NIK') }}</th>
                        <th>{{ __('Nama') }}</th>
                    </tr>
                    @foreach ($hides as $hide)
                        <tr wire:key="hide-tr-{{ $hide->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'hide-edit'); $dispatch('hide-edit', { id: '{{ $hide->id }}'})">
                            <td>{{ $hide->hide_updated_at }}</td>
                            <td>20</td>
                            <td>{{ $hide->group_workdate }}</td>
                            <td>{{ $hide->group_style }}</td>
                            <td>{{ $hide->group_line }}</td>
                            <td>1200</td>
                            <td>600</td>
                            <td>600</td>
                            <td>
                                <div class="flex items-center">
                                    <div class="relative bg-neutral-200 rounded-full h-1.5 dark:bg-neutral-700 w-16">
                                        <div class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500 transition-all duration-200"
                                        style="width: 50%"></div>
                                    </div>
                                    <div class="ml-2">50%</div>
                                </div>
                            </td>
                            <td>{{ $hide->user_emp_id }}</td>
                            <td>{{ $hide->user_name }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="flex items-center relative h-16">
            @if (!$hides->isEmpty())
                @if ($hides->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((hides) => {
                                hides.forEach(hide => {
                                    if (hide.isIntersecting) {
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

{{-- <div class="overflow-auto w-full">
    <div>
        <div class="flex justify-between items-center mb-6 px-5 py-1">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">
                {{ __('Data Mentah') }}</h1>
            <div class="flex gap-x-5 items-center">
                <div class="grow text-sm"><span class="text-neutral-500">{{  __('Total') . ' VN | AB : ' }}</span><span>{{ $sum_area_vn . ' | ' .  $sum_area_ab }}</span><span class="text-neutral-500 ms-4">{{  __('Total lembar') . ' : ' }}</span><span>{{ $hides->total() }}</span></div>
                <x-select wire:model.live="sort">
                    <option value="updated">{{ __('Diperbarui') }}</option>
                    <option value="sf_low">{{ __('SF Terkecil') }}</option>
                    <option value="sf_high">{{ __('SF Terbesar') }}</option>
                </x-select>
            </div>
        </div>
        <div wire:key="hide-edit">   
            <x-modal name="hide-edit">
                <livewire:insight.ldc.summary.hide-edit />
            </x-modal>
        </div>
        @if (!$hides->count())
            @if (!$start_at || !$end_at)
                <div wire:key="no-range" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="fa fa-calendar relative"><i
                                class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih rentang tanggal') }}
                    </div>
                </div>
            @else
                <div wire:key="no-match" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="fa fa-ghost"></i>
                    </div>
                    <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
                    </div>
                </div>
            @endif
        @else
            <div wire:key="raw-hides" class="p-0 sm:p-1 overflow-auto">
                <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg w-full table">
                    <table class="table table-sm table-truncate text-sm text-neutral-600 dark:text-neutral-400">
                        <tr class="uppercase text-xs">
                            <th>{{ __('Diperbarui') }}</th>
                            <th>{{ __('Kode')}}</th>
                            <th>{{ __('VN') }}</th>
                            <th>{{ __('AB') }}</th>
                            <th>{{ __('QT') }}</th>
                            <th>{{ __('G') }}</th>
                            <th>{{ __('M') }}</th>
                            <th>{{ __('S') }}</th>
                            <th>{{ __('WO') }}</th>
                            <th>{{ __('Style') }}</th>
                            <th>{{ __('Line') }}</th>
                            <th>{{ __('Material') }}</th>
                            <th>{{ __('NIK') }}</th>
                            <th>{{ __('Nama') }}</th>
    
                        </tr>
                        @foreach ($hides as $hide)
                        <tr wire:key="hide-tr-{{ $hide->id . $loop->index }}" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'hide-edit'); $dispatch('hide-edit', { id: '{{ $hide->id }}'})">
                            <td>{{ $hide->hide_updated_at }}</td>
                            <td>{{ $hide->code }}</td>
                            <td>{{ $hide->area_vn }}</td>
                            <td>{{ $hide->area_ab }}</td>
                            <td>{{ $hide->area_qt }}</td>
                            <td>{{ $hide->grade }}</td>
                            <td>{{ $hide->machine }}</td>
                            <td>{{ $hide->shift }}</td>
                            <td>{{ $hide->group_workdate }}</td>
                            <td>{{ $hide->group_style }}</td>
                            <td>{{ $hide->group_line }}</td>
                            <td>{{ $hide->group_material }}</td>
                            <td>{{ $hide->user_emp_id }}</td>
                            <td>{{ $hide->user_name }}</td>
                        </tr>
                    @endforeach
                    </table>
                </div>
            </div>
            <div class="flex items-center relative h-16">
                @if (!$hides->isEmpty())
                    @if ($hides->hasMorePages())
                        <div wire:key="more" x-data="{
                            observe() {
                                const observer = new IntersectionObserver((hides) => {
                                    hides.forEach(hide => {
                                        if (hide.isIntersecting) {
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
</div> --}}
