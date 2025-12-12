<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

new #[Layout("layouts.app")] class extends Component {
    //
}; ?>
<x-slot name="title">{{ __("Data - Pemantauan back part mold") }}</x-slot>

<x-slot name="header">
    <x-nav-insights-bpm></x-nav-insights-bpm>
</x-slot>
<div>
    <h1>Haloo</h1>
</div>
