<?php

use Livewire\Volt\Component;
use App\Models\InsBpmCount;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\Url;

new class extends Component {
    public $view = "summary-line";

    public function mount()
    {
        // Initialization logic if needed
        $this->dispatch('update-menu', 'summary-line');
    }
}; ?>

<div>
    <h1>Summary Line Component</h1>
</div>
