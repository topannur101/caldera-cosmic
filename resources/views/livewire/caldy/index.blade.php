<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {


};
?>
<x-slot name="title">{{ __('Caldy AI') }}</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
  <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
    <div class="text-center w-72 py-20 mx-auto">
      <i class="fa fa-fw text-5xl fa-splotch text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500"></i>
      <div class="text-xl mt-3">{{ __('Hai, aku Caldy!') }}</div>
      <div class="text-neutral-500">{{ __('Mau tanya apa hari ini?')  }}</div>
    </div>
  </div>
</div>
