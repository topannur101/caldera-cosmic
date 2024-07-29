<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\InsOmvMetric;
use App\Models\InsOMvCapture;
use Carbon\Carbon;

new class extends Component {
    
    public int $metric_id = 0;

    #[On('captures-load')]
    public function clumpLoad($metric_id)
    {
        $this->metric_id = $metric_id;
    }

    public function with(): array
    {
        
        $metric = InsOmvMetric::find($this->metric_id);
        $captures = [];

        if ($metric) {
            $captures  = InsOmvCapture::where('ins_omv_metric_id', $this->metric_id)->get();
        }
        return [
            'captures' => $captures,
    ];
    }
};
?>


<div class="p-6">
    <div class="flex justify-between items-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Tangkapan foto') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
    </div>
    <div class="grid grid-cols-1 gap-6 mt-6">
      @foreach($captures as $capture)
      <div>
         <img src="/storage/omv-captures/{{ $capture->file_name }}" class="rounded mx-auto" />
         <div class="text-center text-xs mt-3">{{ $capture->file_name }}</div>
      </div>
      @endforeach
    </div>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
