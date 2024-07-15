<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {};

?>

<x-slot name="title">{{ __('Open Mill Validator') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-omv></x-nav-insights-omv>
</x-slot>

<div id="content" class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   <div x-data="{  }">
      <div class="bg-white dark:bg-neutral-800 shadow rounded-lg mb-3 px-6 py-4">Batch information here</div>
      <div class="grid grid-cols-4 gap-x-3 h-96">
         <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4">
            <div class="mb-3">Step 1</div>
            <div class="w-full bg-gray-200 rounded-full h-1.5 mb-4 dark:bg-gray-700">
            <div class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500" style="width: 45%"></div>
            </div>
         </div>
         <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4">
            Step 2
         </div>
         <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4">
            Step 3
         </div>
         <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4">
            Step 4
         </div>
      </div>
   </div>
</div>
