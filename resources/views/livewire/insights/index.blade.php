<?php

use App\Models\InsStcMLog;
use App\Models\InsStcDSum;
use App\Models\InsOmvMetric;
use App\Models\InsRtcClump;
use App\Models\InsRdcTest;
use App\Models\InsLdcHide;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

new #[Layout('layouts.app')] 
class extends Component {

    public int $stc_m_logs_recent = 0;
    public int $stc_d_sums_recent = 0;
    public int $omv_lines_recent = 0;
    public int $rtc_lines_recent = 0;
    public int $rdc_machines_recent = 0;
    public int $ldc_codes_recent = 0;
    public bool $is_aurelia_up = false;

    public function mount()
    {
        $this->calculateMetrics();
    }

    private function pingAureliaService()
    {
        try {

            // $response = Http::withoutVerifying()->timeout(2)->get('https://taekwang-id.comelz.cloud');
            // if ($response->successful()) {
            //     return true;
            // }

            $pingResult = exec("ping -n 1 172.70.77.230", $output, $status);
            if ($status === 0) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    private function getCachedStcMLogs(): int 
    {
        return Cache::remember('stc_m_logs_recent', now()->addMinutes(30), function () {
            $timeWindow = Carbon::now()->subHours(5);
            return InsStcMLog::where('updated_at', '>=', $timeWindow)
                ->distinct('ins_stc_machine_id')
                ->count('ins_stc_machine_id');
        });
    }

    private function getCachedStcDSums(): int 
    {
        return Cache::remember('stc_d_sums_recent', now()->addMinutes(30), function () {
            $timeWindow = Carbon::now()->subHours(5);
            return InsStcDSum::where('updated_at', '>=', $timeWindow)
                ->distinct('ins_stc_machine_id')
                ->count('ins_stc_machine_id');
        });
    }


    private function getCachedOmvLines(): int 
    {
        return Cache::remember('omv_lines_recent', now()->addMinutes(30), function () {
            $timeWindow = Carbon::now()->subMinutes(60);
            return InsOmvMetric::where('updated_at', '>=', $timeWindow)
                ->distinct('line')
                ->count('line');
        });
    }

    private function getCachedRtcLines(): int 
    {
        return Cache::remember('rtc_lines_recent', now()->addMinutes(30), function () {
            $timeWindow = Carbon::now()->subMinutes(60);
            return InsRtcClump::where('updated_at', '>=', $timeWindow)
                ->distinct('ins_rtc_device_id')
                ->count('ins_rtc_device_id');
        });
    }

    private function getCachedRdcMachines(): int 
    {
        return Cache::remember('rdc_machines_recent', now()->addMinutes(30), function () {
            $timeWindow = Carbon::now()->subMinutes(60);
            return InsRdcTest::where('updated_at', '>=', $timeWindow)
                ->distinct('ins_rdc_machine_id')
                ->count('ins_rdc_machine_id');
        });
    }

    private function getCachedLdcCodes(): int 
    {
        return Cache::remember('ldc_codes_recent', now()->addMinutes(30), function () {
            $timeWindow = Carbon::now()->subMinutes(60);
            $validCodes = ['XA', 'XB', 'XC', 'XD'];
            
            $recentCodes = InsLdcHide::where('updated_at', '>=', $timeWindow)
                ->get()
                ->map(function ($hide) {
                    preg_match('/X[A-D]/', $hide->code, $matches);
                    return $matches[0] ?? null;
                })
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            return count(array_intersect($validCodes, $recentCodes));
        });
    }

    public function calculateMetrics()
    {
        $this->stc_m_logs_recent = $this->getCachedStcMLogs();
        $this->stc_d_sums_recent = $this->getCachedStcDSums();
        $this->omv_lines_recent = $this->getCachedOmvLines();
        $this->rtc_lines_recent = $this->getCachedRtcLines();
        $this->rdc_machines_recent = $this->getCachedRdcMachines();
        $this->ldc_codes_recent = $this->getCachedLdcCodes();
        $this->is_aurelia_up = Cache::remember('is_aurelia_up', now()->addMinutes(30), function () {
            return $this->pingAureliaService();
        });
    }

    #[On('recalculate')]
    public function recalculate()
    {
        Cache::forget('stc_m_logs_recent');
        Cache::forget('stc_d_sums_recent');
        Cache::forget('omv_lines_recent');
        Cache::forget('rtc_lines_recent');
        Cache::forget('rdc_machines_recent');
        Cache::forget('ldc_codes_recent');
        Cache::forget('is_aurelia_up');
        $this->calculateMetrics();
    }
};

?>

<x-slot name="title">{{ __('Wawasan') }}</x-slot>
<div wire:poll.900s id="content" class="py-12 text-neutral-800 dark:text-neutral-200">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="relative text-neutral h-32 sm:rounded-lg overflow-hidden mb-12">
            <img class="dark:invert absolute top-0 left-0 w-full h-full object-cover opacity-70" src="/insight-banner.jpg" />
            <div class="absolute top-0 left-0 flex h-full items-center px-4 lg:px-8 text-neutral-500">
                <div>
                    <div class="text-2xl mb-2 font-medium">{{ __('Wawasan') }}</div>
                    <div>{{ __('Platform analitik untuk proses manufaktur yang lebih terkendali.') }}</div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">{{ __('Sistem Rubber Terintegrasi') }}</h1>
                <div class="bg-white dark:bg-neutral-800 shadow overflow-hidden sm:rounded-lg divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
                    <a href="{{ route('insights.omv.index') }}" class="block hover:bg-caldy-500 hover:bg-opacity-10" wire:navigate>
                        <div class="flex items-center">
                            <div class="px-6 py-3">
                                <img src="/ink-omv.svg" class="w-16 h-16 dark:invert">
                            </div>
                            <div class="grow">
                                <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Pemantauan open mill') }}</div>
                                <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                                    <div class="flex items-center gap-x-2 text-xs uppercase text-neutral-500">
                                        <div class="w-2 h-2 {{ $omv_lines_recent > 0 ? 'bg-green-500' : 'bg-red-500' }} rounded-full"></div>
                                        <div class="">{{ $omv_lines_recent > 0 ? $omv_lines_recent . ' ' . __('line ') : __('luring') }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="px-6 py-3 text-lg">
                                <i class="fa fa-chevron-right"></i>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('insights.rtc.index') }}" class="block hover:bg-caldy-500 hover:bg-opacity-10" wire:navigate>
                        <div class="flex items-center">
                            <div class="px-6 py-3">
                                <img src="/ink-rtc.svg" class="w-16 h-16 dark:invert">
                            </div>
                            <div class="grow">
                                <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Kendali tebal calendar') }}</div>
                                <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                                    <div class="flex items-center gap-x-2 text-xs uppercase text-neutral-500">
                                        <div class="w-2 h-2 {{ $rtc_lines_recent > 0 ? 'bg-green-500' : 'bg-red-500' }} rounded-full"></div>
                                        <div class="">{{ $rtc_lines_recent > 0 ? $rtc_lines_recent . ' ' . __('line ') : __('luring') }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="px-6 py-3 text-lg">
                                <i class="fa fa-chevron-right"></i>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('insights.rdc.index') }}" class="block hover:bg-caldy-500 hover:bg-opacity-10" wire:navigate>
                        <div class="flex items-center">
                            <div class="px-6 py-3">
                                <img src="/ink-rdc.svg" class="w-16 h-16 dark:invert">
                            </div>
                            <div class="grow">
                                <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Sistem data rheometer') }}</div>
                                <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                                    <div class="flex items-center gap-x-2 text-xs uppercase text-neutral-500">
                                        <div class="w-2 h-2 {{ $rdc_machines_recent > 0 ? 'bg-green-500' : 'bg-red-500' }} rounded-full"></div>
                                        <div class="">{{ $rdc_machines_recent > 0 ? $rdc_machines_recent . ' ' . __('mesin ') : __('luring') }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="px-6 py-3 text-lg">
                                <i class="fa fa-chevron-right"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            <div class="flex flex-col gap-6">
                <div>
                    <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                        {{ __('Sistem Area IP') }}</h1>
                    <div class="bg-white dark:bg-neutral-800 shadow overflow-hidden sm:rounded-lg divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
                        <a href="{{ route('insights.stc.index') }}" class="block hover:bg-caldy-500 hover:bg-opacity-10" wire:navigate>
                            <div class="flex items-center">
                                <div class="px-6 py-3">
                                    <img src="/ink-stc.svg" class="w-16 h-16 dark:invert">
                                </div>
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Kendali chamber IP') }}</div>
                                    <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                                        <div class="flex items-center gap-x-2 text-xs uppercase text-neutral-500">
                                            <div class="w-2 h-2 {{ $stc_m_logs_recent > 0 ? 'bg-green-500' : 'bg-red-500' }} rounded-full"></div>
                                            <div>{{ $stc_m_logs_recent > 0 ? $stc_m_logs_recent . ' ' . __('line ') : __('luring') }}</div>
                                            <div>•</div>
                                            <div>{{ __('Data HB') . ': ' . $stc_d_sums_recent . ' ' . __('line ') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 text-lg">
                                    <i class="fa fa-chevron-right"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                <div>
                    <h1 class="uppercase text-sm text-neutral-500 mb-4 px-8">
                        {{ __('Sistem Area OKC') }}</h1>
                    <div class="bg-white dark:bg-neutral-800 shadow overflow-hidden sm:rounded-lg divide-y divide-neutral-200 dark:text-white dark:divide-neutral-700">
                        <a href="{{ route('insights.ldc.index') }}" class="block hover:bg-caldy-500 hover:bg-opacity-10" wire:navigate>
                            <div class="flex items-center">
                                <div class="px-6 py-3">
                                    <img src="/ink-ldc.svg" class="w-16 h-16 dark:invert">
                                </div>
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Sistem data kulit') }}</div>
                                    <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                                        <div class="flex items-center gap-x-2 text-xs uppercase text-neutral-500">
                                            <div class="w-2 h-2 {{ $ldc_codes_recent > 0 ? 'bg-green-500' : 'bg-red-500' }} rounded-full"></div>
                                            <div class="">{{ $ldc_codes_recent > 0 ? $ldc_codes_recent . ' ' . __('mesin ') : __('luring') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 text-lg">
                                    <i class="fa fa-chevron-right"></i>
                                </div>
                            </div>
                        </a>
                        <a href="https://taekwang-id.comelz.cloud/" class="block hover:bg-caldy-500 hover:bg-opacity-10" >
                            <div class="flex items-center">
                                <div class="px-6 py-3">
                                    <img src="/ink-aurelia.svg" class="w-16 h-16 dark:invert">
                                </div>
                                <div class="grow">
                                    <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">{{ __('Aurelia') }}</div>
                                    <div class="flex flex-col gap-y-2 text-neutral-600 dark:text-neutral-400">
                                        <div class="flex items-center gap-x-2 text-xs uppercase text-neutral-500">
                                            <div class="w-2 h-2 {{ $is_aurelia_up ? 'bg-green-500' : 'bg-red-500' }} rounded-full"></div>
                                            <div class="">{{ $is_aurelia_up ? __('Daring') : __('Luring') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 text-lg">
                                    <i class="fa fa-chevron-right"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>            
            </div>
        </div>  
    </div>     
</div>