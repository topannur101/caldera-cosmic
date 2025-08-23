<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class InvAreaSelector extends Component
{
    public array $areas;

    /**
     * Create a new component instance.
     */
    public function __construct($areas = [])
    {
        $this->areas = $areas;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.inv-area-selector');
    }
}
