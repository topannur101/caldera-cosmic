<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component {
   

};

?>

<x-slot name="title">{{ __('Iklim') }}</x-slot>

<x-slot name="header">
    
</x-slot>

<div id="content" class="py-12 max-w-5xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   {{ __('Riwayat iklim belum tersedia') }}
</div>
