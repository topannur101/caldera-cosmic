<?php

// app/View/Components/DropdownCheckbox.php
namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DropdownCheckbox extends Component
{
    public string $wireModel;
    public string $value;
    public string $id;

    /**
     * Create a new component instance.
     */
    public function __construct(string $wireModel, string $value, string $id)
    {
        $this->wireModel = $wireModel;
        $this->value = $value;
        $this->id = $id;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view("components.dropdown-checkbox");
    }
}

// resources/views/components/dropdown-checkbox.blade.php

?>

<div class="flex items-center px-4 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-600">
    <input
        type="checkbox"
        id="{{ $id }}"
        wire:model.live="{{ $wireModel }}"
        value="{{ $value }}"
        class="h-4 w-4 text-caldy-600 focus:ring-caldy-500 border-neutral-300 dark:border-neutral-600 rounded dark:bg-neutral-700 dark:focus:ring-caldy-600 dark:ring-offset-neutral-800"
    />
    <label for="{{ $id }}" class="ml-3 block text-sm font-medium cursor-pointer flex-1">
        {{ $slot }}
    </label>
</div>
