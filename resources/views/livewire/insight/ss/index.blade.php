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
            <livewire:insight.ss.3 />
            @break
        @case(4)
            <livewire:insight.ss.4 />
            @break
        @case(5)
            <livewire:insight.ss.5 />
            @break
        @case(6)
            <livewire:insight.ss.6 />
            @break
    @endswitch
</div>
