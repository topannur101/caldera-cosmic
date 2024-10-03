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
        $hides = InsLdcHide::where('updated_at', '>=', Carbon::now()->subDay())->where('user_id', Auth::user()->id ?? null);
        $hides = $hides->orderBy('updated_at', 'desc')->limit(5)->get();

        return [
            'hides' => $hides,
        ];
    }

    #[On('hide-saved')]
    public function customReset()
    {
        $this->reset(['shid']);
    }
};

?>

<div class="w-64 bg-white dark:bg-neutral-800 shadow overflow-hidden rounded-lg">
    @if($hides->isEmpty())
        <div class="flex min-h-20">
            <div class="my-auto px-6 text-sm text-center w-full">
                {{ __('Tak ada riwayat terakhir') }}
            </div>
        </div>
    @else
        <ul class="text-lg">
            <li class="w-full hover:bg-caldy-500 hover:bg-opacity-10">
                <div class="flex items-center">
                    <input id="hs-new" type="radio" value="0" name="shid" wire:model.live="shid"
                        @click="$dispatch('set-hide', { is_editing:  false, line: '', workdate: '', style: '', material: '', area_vn: '', area_ab: '', area_qt: '', grade: '', code: '' });"
                        class="peer hidden">
                    <label for="hs-new"
                        class="w-full cursor-pointer px-6 py-3 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 peer-checked:bg-opacity-100 peer-checked:text-white hover:bg-opacity-10"><i
                            class="fa fa-plus mr-3"></i>{{ __('Kulit baru') }}</label>
                </div>
            </li>
            @foreach ($hides as $hide)
                <li class="w-full hover:bg-caldy-500 hover:bg-opacity-10">
                    <div class="flex items-center">
                        <input id="hs-id-{{ $loop->iteration }}" type="radio" value="{{ $hide->id }}"
                            name="shid" wire:model.live="shid" class="peer hidden">
                        <label for="hs-id-{{ $loop->iteration }}"
                            class="w-full cursor-pointer px-6 py-3 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 peer-checked:bg-opacity-100 peer-checked:text-white hover:bg-opacity-10"
                            @click="$dispatch('set-hide', { is_editing: true, line: '{{ $hide->ins_ldc_group->line }}', workdate: '{{ $hide->ins_ldc_group->workdate }}', style: '{{ $hide->ins_ldc_group->style }}', material: '{{ $hide->ins_ldc_group->material }}', area_vn: '{{ $hide->area_vn }}', area_ab: '{{ $hide->area_ab }}', area_qt: '{{ $hide->area_qt }}', grade: '{{ $hide->grade }}', code: '{{ $hide->code }}' })">
                            <div>{{ $hide->code }}</div>
                            <div class="text-xs">VN: {{ $hide->area_vn }}<span class="mx-2">|</span>AB:
                                {{ $hide->area_ab }}<span class="mx-2">|</span>D:
                                {{ $hide->area_vn > 0 && $hide->area_qt > 0 ? round((($hide->area_vn - $hide->area_qt) / $hide->area_vn) * 100, 2) . ' %' : '?' }}
                            </div>
                        </label>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
