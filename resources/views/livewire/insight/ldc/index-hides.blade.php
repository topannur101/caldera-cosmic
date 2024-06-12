<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsLdcHide;
use Carbon\Carbon;

new class extends Component {

    public $shid = 0;

    #[On('hide-saved')]
    public function with(): array
    {
        $hides = InsLdcHide::where('user_id', Auth::user()->id)->where('created_at', '>=', Carbon::now()->subDay())
                     ->orderBy('updated_at', 'desc')->limit(5)->get();

        return [
            'hides' => $hides
        ];
    }

    public function updated($property)
    {
        if ($property == 'shid') {

            $hide = InsLdcHide::find($this->shid);

            if ($hide) {
                $data = [
                    'area_vn'   => $hide->area_vn,
                    'area_ab'   => $hide->area_ab,
                    'area_qt'   => $hide->area_qt,
                    'grade'     => $hide->grade,
                    'code'      => $hide->code,
                    'ins_ldc_group_id'  => $hide->ins_ldc_group_id
                ];
                
            } else {
                $data = [
                    'area_vn'   => null,
                    'area_ab'   => null,
                    'area_qt'   => null,
                    'grade'     => null,
                    'code'      => null,
                    'ins_ldc_group_id'  => null
                ];
            }

            $this->dispatch('hide-load', data: $data);           
        }
    }

    #[On('hide-saved')]
    public function customReset()
    {
        $this->reset(['shid']);
    }
};

?>

<ul
class="w-64 text-lg font-medium text-neutral-900 bg-white border border-neutral-200 rounded-lg dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
<li class="w-full border-b border-neutral-200 rounded-t-lg dark:border-neutral-700">
    <div class="flex items-center ps-3">
        <input id="hs-new" type="radio" value="0" name="shid" wire:model.live="shid"
            class="w-4 h-4 text-caldy-600 bg-neutral-100 border-neutral-300 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-neutral-700 dark:focus:ring-offset-neutral-700 focus:ring-2 dark:bg-neutral-600 dark:border-neutral-500">
        <label for="hs-new"
            class="w-full py-3 ms-3 font-medium text-neutral-900 dark:text-neutral-300">{{ __('Kulit baru') }}</label>
    </div>
</li>
@foreach ($hides as $hide)
<li class="w-full border-b border-neutral-200 rounded-t-lg dark:border-neutral-700">
    <div class="flex items-center ps-3">
        <input id="hs-id-{{ $loop->iteration }}" type="radio" value="{{ $hide->id }}" name="shid" wire:model.live="shid"
            class="w-4 h-4 text-caldy-600 bg-neutral-100 border-neutral-300 focus:ring-caldy-500 dark:focus:ring-caldy-600 dark:ring-offset-neutral-700 dark:focus:ring-offset-neutral-700 focus:ring-2 dark:bg-neutral-600 dark:border-neutral-500">
        <label for="hs-id-{{ $loop->iteration }}" class="w-full py-3 ms-3 font-medium text-neutral-900 dark:text-neutral-300">
            <div>{{ $hide->code }}</div>
            <div class="text-xs text-neutral-500">VN: {{ $hide->area_vn }}<span class="mx-2">|</span>AB: {{ $hide->area_ab }}<span class="mx-2">|</span>D: {{ ($hide->area_vn > 0 && $hide->area_qt > 0) ? ((round((($hide->area_vn - $hide->area_qt) / $hide->area_vn * 100), 2)) . ' %') : '?' }}</div>
        </label>
    </div>
</li>    
@endforeach
</ul>