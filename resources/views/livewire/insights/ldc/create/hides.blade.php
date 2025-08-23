<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsLdcHide;
use Carbon\Carbon;

new class extends Component {
    public $shid = 0;

    #[On("hide-saved")]
    public function with(): array
    {
        $hides = InsLdcHide::where("updated_at", ">=", Carbon::now()->subDay())->where("user_id", Auth::user()->id ?? null);

        $hides = $hides
            ->orderBy("updated_at", "desc")
            ->limit(6)
            ->get();

        return [
            "hides" => $hides,
        ];
    }

    #[On("hide-saved")]
    public function customReset()
    {
        $this->reset(["shid"]);
    }
};

?>

<div class="py-8 text-center text-sm">
    <div class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Riwayat") }}</div>
    @if ($hides->isEmpty())
        <div class="flex min-h-20">
            <div class="my-auto px-6 text-center w-full">
                {{ __("Tak ada riwayat terakhir") }}
            </div>
        </div>
    @else
        <ul class="font-mono">
            {{--
                <li class="w-full hover:bg-caldy-500 hover:bg-opacity-10">
                <div class="flex items-center">
                <input id="hs-new" type="radio" value="0" name="shid" wire:model.live="shid"
                @click="$dispatch('set-hide', { is_editing:  false, group_id: 0, material: '', area_vn: '', area_ab: '', area_qt: '', grade: '', quota_id: '', code: '' });"
                class="peer hidden">
                <label for="hs-new"
                class="w-full cursor-pointer px-4 py-2 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 peer-checked:bg-opacity-100 peer-checked:text-white hover:bg-opacity-10">{{ __('Kulit baru') }}</label>
                </div>
                </li>
            --}}
            @foreach ($hides as $hide)
                <li class="w-full hover:bg-caldy-500 hover:bg-opacity-10">
                    <div class="flex items-center">
                        <input id="hs-id-{{ $loop->iteration }}" type="radio" value="{{ $hide->id }}" name="shid" wire:model.live="shid" class="peer hidden" />
                        <label
                            for="hs-id-{{ $loop->iteration }}"
                            class="w-full cursor-pointer px-4 py-2 bg-caldy-400 dark:bg-caldy-700 bg-opacity-0 dark:bg-opacity-0 peer-checked:bg-opacity-100 peer-checked:text-white hover:bg-opacity-10"
                            @click="$dispatch('set-hide', { is_editing: true, group_id: {{ $hide->ins_ldc_group_id ?: "null" }}, material: '{{ $hide->ins_ldc_group->material }}', area_vn: '{{ $hide->area_vn }}', area_ab: '{{ $hide->area_ab }}', area_qt: '{{ $hide->area_qt }}', grade: '{{ $hide->grade }}', quota_id: '{{ $hide->ins_ldc_quota_id }}', code: '{{ $hide->code }}' })"
                        >
                            <div>{{ $hide->code }}</div>
                            {{-- <div class="text-xs">{{ __('Selisih') . ': ' . ($hide->area_vn > 0 && $hide->area_ab > 0 ? round((($hide->area_vn - $hide->area_ab) / $hide->area_vn) * 100, 1) . ' %' : '?') . ', ' . __('Defect') . ': ' . ($hide->area_vn > 0 && $hide->area_qt > 0 ? round((($hide->area_vn - $hide->area_qt) / $hide->area_vn) * 100, 1) . ' %' : '?') }} --}}
                            {{-- </div> --}}
                        </label>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
