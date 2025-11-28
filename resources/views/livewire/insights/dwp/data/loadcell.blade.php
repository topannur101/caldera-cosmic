<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Models\InsDwpCount;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;

new #[Layout("layouts.app")] class extends Component {
    use WithPagination;
    use HasDateRangeFilter;

    #[Url]
    public string $start_at = "";

    #[Url]
    public string $end_at = "";

    #[Url]
    public string $line = "";

    #[Url]
    public string $mechine = "";

    public string $view = "loadcell";

    public function mount()
    {
        if (! $this->start_at || ! $this->end_at) {
            $this->setThisWeek();
        }

        // update menu
        $this->dispatch("update-menu", $this->view);
    }
}; ?>

<div>
    <h1>
        Ini Halaman Loadcell DWP
    </h1>
</div>
