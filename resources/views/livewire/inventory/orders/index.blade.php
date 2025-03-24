<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

new #[Layout('layouts.app')] 
class extends Component {
    
    #[Url]
    public $view = 'daily-flow';
    public array $view_titles = [];

    public function mount()
    {
        $this->view_titles = [        
            'daily-flow'    => __('Arus harian'),
            'monthly-flow'  => __('Arus bulanan'),  
            'monthly-summary' => __('Ringkasan bulanan'), 
        ];
    }

    public function getViewTitle(): string
    {
        return $this->view_titles[$this->view] ?? '';
    }
};

?>

<x-slot name="title">{{ __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory></x-nav-inventory>
</x-slot>

<div id="content" class="relative py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    @vite(['resources/js/apexcharts.js'])
    <div class="text-center">
        <div class="text-xl mb-3">{{ __('Sepi juga ya...') }}</div>
        <div class="text-sm">{{ __('Fitur pesanan PR/Memo sedang dalam tahap pengembangan.') }}</div>
    </div>
</div>
