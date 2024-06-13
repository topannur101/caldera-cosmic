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
class="w-64 text-lg bg-white dark:bg-neutral-800 shadow overflow-hidden rounded-lg">
<li class="w-full hover:bg-caldy-500 hover:bg-opacity-10">
    <div class="flex items-center">
        <input id="hs-new" type="radio" value="0" name="shid" wire:model.live="shid"
        class="peer hidden">
        <label for="hs-new"
        class="w-full cursor-pointer px-6 py-3 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 peer-checked:bg-opacity-100 peer-checked:text-white hover:bg-opacity-10"><i class="fa fa-plus mr-3"></i>{{ __('Kulit baru') }}</label>
    </div>
</li>
@foreach ($hides as $hide)
<li class="w-full hover:bg-caldy-500 hover:bg-opacity-10">
    <div class="flex items-center">
        <input id="hs-id-{{ $loop->iteration }}" type="radio" value="{{ $hide->id }}" name="shid" wire:model.live="shid"
            class="peer hidden">
        <label for="hs-id-{{ $loop->iteration }}" class="w-full cursor-pointer px-6 py-3 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 peer-checked:bg-opacity-100 peer-checked:text-white hover:bg-opacity-10">
            <div>{{ $hide->code }}</div>
            <div class="text-xs">VN: {{ $hide->area_vn }}<span class="mx-2">|</span>AB: {{ $hide->area_ab }}<span class="mx-2">|</span>D: {{ ($hide->area_vn > 0 && $hide->area_qt > 0) ? ((round((($hide->area_vn - $hide->area_qt) / $hide->area_vn * 100), 2)) . ' %') : '?' }}</div>
        </label>
    </div>
</li>    
@endforeach
</ul>