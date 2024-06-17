<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use App\Models\InsLdcHide;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Reactive]
    public $start_at;

    #[Reactive]
    public $end_at;
    
    #[Reactive]
    public $is_workdate;

    #[Reactive]
    public $fquery;

    #[Reactive]
    public $ftype;

    public $perPage = 20;

    public function with(): array
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at)->endOfDay();

        $hidesQuery = InsLdcHide::join('ins_ldc_groups', 'ins_ldc_hides.ins_ldc_group_id', '=', 'ins_ldc_groups.id')
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
            $hidesQuery->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
        } else {
            $hidesQuery->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
        }

        switch ($this->ftype) {
            case 'code':
                $hidesQuery->where('ins_ldc_hides.code', 'LIKE', '%' . $this->fquery . '%');
                break;
            case 'style':
                $hidesQuery->where('ins_ldc_groups.style', 'LIKE', '%' . $this->fquery . '%');
            break;
            case 'line':
                $hidesQuery->where('ins_ldc_groups.line', 'LIKE', '%' . $this->fquery . '%');
            break;
            case 'material':
                $hidesQuery->where('ins_ldc_groups.material', 'LIKE', '%' . $this->fquery . '%');
            break;
            case 'emp_id':
                $hidesQuery->where('users.emp_id', 'LIKE', '%' . $this->fquery . '%');
            break;
            
            default:
                $hidesQuery->where(function (Builder $query) {
                $query
                    ->orWhere('ins_ldc_hides.code', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('ins_ldc_groups.style', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('ins_ldc_groups.line', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('ins_ldc_groups.material', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('users.emp_id', 'LIKE', '%' . $this->fquery . '%');
                });
                break;
        }

        if (!$this->is_workdate) {
            $hidesQuery->orderBy('ins_ldc_hides.updated_at', 'DESC');
        } else {
            $hidesQuery->orderBy('ins_ldc_groups.workdate', 'DESC');
        }

        $hides = $hidesQuery->paginate($this->perPage);
        $sum_area_vn = $hidesQuery->sum('area_vn');
        $sum_area_ab = $hidesQuery->sum('area_ab');

        return [
            'hides' => $hides,
            'sum_area_vn' => $sum_area_vn,
            'sum_area_ab' => $sum_area_ab
        ];
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }
};

?>

<div class="overflow-auto w-full">
    <div>
        <div class="flex justify-between items-center mb-6 px-5 py-1">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">
                {{ __('Data Mentah') }}</h1>
            <div class="flex gap-x-2 items-center">
                <div class="text-sm"><span class="text-neutral-500">{{  __('Total') . ' VN | AB : ' }}</span><span>{{ $sum_area_vn . ' | ' .  $sum_area_ab }}</span><span class="text-neutral-500 ms-4">{{  __('Total lembar') . ' : ' }}</span><span>{{ $hides->total() }}</span></div>
            </div>
        </div>
        @if (!$hides->count())
            @if (!$start_at || !$end_at)
                <div wire:key="no-range" class="py-20">
                    <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                        <i class="fa fa-calendar relative"><i
                                class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                    </div>
                    <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Tentukan rentang tanggal') }}
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
                            <th>{{ __('S') }}</th>
                            <th>{{ __('WO') }}</th>
                            <th>{{ __('Style') }}</th>
                            <th>{{ __('Line') }}</th>
                            <th>{{ __('Material') }}</th>
                            <th>{{ __('NK') }}</th>
                            <th>{{ __('Nama') }}</th>
    
                        </tr>
                        @foreach ($hides as $hide)
                        <tr>
                            <td>{{ $hide->hide_updated_at }}</td>
                            <td>{{ $hide->code }}</td>
                            <td>{{ $hide->area_vn }}</td>
                            <td>{{ $hide->area_ab }}</td>
                            <td>{{ $hide->area_qt }}</td>
                            <td>{{ $hide->grade }}</td>
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
</div>
