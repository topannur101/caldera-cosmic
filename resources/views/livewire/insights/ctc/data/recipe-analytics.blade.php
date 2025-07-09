<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\InsCtcRecipe;

new #[Layout('layouts.app')] class extends Component {
    public array $recipes = [];

    public function mount()
    {
        $this->recipes = InsCtcRecipe::active(30)
            ->orderBy('name')
            ->get()
            ->map(function($r){
                return array_merge($r->getSummaryStats(), $r->getRecentPerformance());
            })
            ->toArray();
    }
};


<div class="overflow-x-auto">
    <table class="table table-sm text-sm w-full">
        <thead>
            <tr class="text-xs uppercase text-neutral-500 border-b">
                <th class="px-4 py-2">{{ __('Resep') }}</th>
                <th class="px-4 py-2">{{ __('Batch') }}</th>
                <th class="px-4 py-2">MAE</th>
                <th class="px-4 py-2">SSD</th>
                <th class="px-4 py-2">{{ __('Skor') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($recipes as $r)
            <tr class="border-b border-neutral-100 dark:border-neutral-700">
                <td class="px-4 py-2">{{ $r['name'] }}</td>
                <td class="px-4 py-2">{{ $r['batch_count'] }}</td>
                <td class="px-4 py-2">{{ number_format($r['avg_mae'],2) }}</td>
                <td class="px-4 py-2">{{ number_format($r['avg_ssd'],2) }}</td>
                <td class="px-4 py-2">{{ $r['quality_score'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>


