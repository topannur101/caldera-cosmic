<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;
use App\Models\InsRtcDevice;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Reactive]
    public $start_at;

    #[Reactive]
    public $fline;
    public $perPage = 20;

    public function with(): array
    {
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->start_at)->addDay();

        $devices = InsRtcDevice::whereHas('ins_rtc_metrics', function (Builder $query) use ($start, $end) {
            $query->whereBetween('dt_client', [$start, $end]);
        })->orderBy('line');

        if ($this->fline) {
            $devices->where('line', $this->fline);
        }
        $devices = $devices->paginate($this->perPage);

        return [
            'devices' => $devices,
            'start' => $start,
            'end' => $end,
        ];

    }

    public function loadMore()
    {
        $this->perPage += 10;
    }
};

?>

<div wire:poll class="w-full">
    <h1 class="text-2xl mb-6 text-neutral-900 dark:text-neutral-100 px-5">
        {{ __('Ringkasan Harian') }}</h1>

    @if (!$devices->count())

        <div wire:key="no-match" class="py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                <i class="fa fa-ghost"></i>
            </div>
            <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Tidak ada yang cocok') }}
            </div>
        </div>
    @else
        <div wire:key="line-all-devices" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto">
            <table class="table table-sm table-truncate text-neutral-600 dark:text-neutral-400">
                <tr class="uppercase text-xs">
                    <th>{{ __('Line') }}</th>
                    <th>{{ __('Gilingan') }}</th>
                    <th>{{ __('Rerata') }}</th>
                    <th>{{ __('W. Total') }}</th>
                    <th>{{ __('W. Aktif') }}</th>
                    <th>{{ __('W. Pasif') }}</th>
                </tr>
                @foreach ($devices as $device)
                    <tr>
                        <td>{{ $device->line }}</td>
                        <td>{{ $device->ins_rtc_clumps->count() }}</td>
                        <td>{{ $device->avg_clump_duration() }}</td>
                        <td>{{ $device->total_time() }}</td>
                        <td>{{ $device->active_time() }}</td>
                        <td>{{ $device->passive_time() }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div class="flex items-center relative h-16">
            @if (!$devices->isEmpty())
                @if ($devices->hasMorePages())
                    <div wire:key="more" x-data="{
                        observe() {
                            const observer = new IntersectionObserver((devices) => {
                                devices.forEach(device => {
                                    if (device.isIntersecting) {
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
