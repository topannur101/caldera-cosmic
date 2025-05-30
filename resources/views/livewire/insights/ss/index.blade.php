<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

new #[Layout('layouts.ss')] class extends Component {

    #[Url]
    public int $id;
}

?>

<div>
    @vite(['resources/js/apexcharts.js'])
    @switch($id)
        @case(3)
            <livewire:insights.ss.3 />
            @break
        @case(4)
            <livewire:insights.ss.4 />
            @break
        @case(5)
            <livewire:insights.ss.5 />
            @break
        @case(6)
            <livewire:insights.ss.6 />
            @break
        @case(7)
            <livewire:insights.ss.7 />
            @break
    @endswitch
</div>
