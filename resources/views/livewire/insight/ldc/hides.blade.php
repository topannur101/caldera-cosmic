<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {


};

?>

<x-slot name="title">{{ __('Leather Data Collection') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-ldc></x-nav-insights-ldc>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">

</div>
