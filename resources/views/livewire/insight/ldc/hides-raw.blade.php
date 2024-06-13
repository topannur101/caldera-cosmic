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
        $end = Carbon::parse($this->end_at)->addDay();

        $hides = InsLdcHide::join('ins_ldc_groups', 'ins_ldc_hides.ins_ldc_group_id', '=', 'ins_ldc_groups.id')
        ->join('users', 'ins_ldc_hides.user_id', '=', 'users.id');

        if (!$this->is_workdate) {
            $hides->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
        } else {
            $hides->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
        }

        switch ($this->ftype) {
            case 'code':
                $hides->where('ins_ldc_hides.code', 'LIKE', '%' . $this->fquery . '%');
                break;
            case 'style':
                $hides->where('ins_ldc_groups.style', 'LIKE', '%' . $this->fquery . '%');
            break;
            case 'emp_id':
                $hides->where('users.emp_id', 'LIKE', '%' . $this->fquery . '%');
            break;
            
            default:
                $hides->where(function (Builder $query) {
                $query
                    ->orWhere('ins_ldc_hides.code', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('ins_ldc_groups.style', 'LIKE', '%' . $this->fquery . '%')
                    ->orWhere('users.emp_id', 'LIKE', '%' . $this->fquery . '%');
                });
                break;
        }

        if (!$this->is_workdate) {
            $hides->orderBy('ins_ldc_hides.updated_at', 'DESC');
        } else {
            $hides->orderBy('ins_ldc_groups.workdate', 'DESC');
        }

        $hides = $hides->paginate($this->perPage);

        return [
            'hides' => $hides,
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
                <div class="text-sm"><span class="text-neutral-500">{{  __('Total') . ' ' }}<span class="mx-2">VN</span><span>|</span><span class="mx-2">AB</span><span>|</span><span class="mx-2">QT</span>:</span><span><span class="mx-2">{{ $hides->sum('area_vn') }}</span><span>|</span><span class="mx-2">{{ $hides->sum('area_ab') }}</span><span>|</span><span class="mx-2">{{ $hides->sum('area_qt') }}</span></span></div>
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
                    <table class="table table-sm table-truncate text-neutral-600 dark:text-neutral-400">
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
                            <th>{{ __('IDK') }}</th>
                            <th>{{ __('Nama') }}</th>
    
                        </tr>
                        @foreach ($hides as $hide)
                            <tr>
                                <td>{{ $hide->updated_at }}</td>
                                <td>{{ $hide->code }}</td>
                                <td>{{ $hide->area_vn }}</td>
                                <td>{{ $hide->area_ab }}</td>
                                <td>{{ $hide->area_qt }}</td>
                                <td>{{ $hide->grade }}</td>
                                <td>{{ $hide->shift }}</td>
                                <td>{{ $hide->ins_ldc_group->workdate }}</td>
                                <td>{{ $hide->ins_ldc_group->style }}</td>
                                <td>{{ $hide->ins_ldc_group->line }}</td>
                                <td>{{ $hide->ins_ldc_group->material }}</td>
                                <td>{{ $hide->user->emp_id }}</td>
                                <td>{{ $hide->user->name }}</td>
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
