<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class InvLocSelector extends Component
{
    public array $loc_parents;
    public array $loc_bins;
    /**
     * Create a new component instance.
     */
    public function __construct($loc_parents = [], $loc_bins = [])
    {
        $this->loc_parents = $loc_parents;
        $this->loc_bins = $loc_bins;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.inv-loc-selector');
    }
}
