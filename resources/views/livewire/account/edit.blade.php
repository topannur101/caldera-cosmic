<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {

};

?>
<x-slot name="title">{{ __('Info akun') }}</x-slot>
<x-slot name="header">
    <header class="bg-white dark:bg-neutral-800 shadow">
        <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div>
                <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                    <x-link wire:navigate href="{{ route('account') }}" class="inline-block py-6"><i
                            class="fa fa-arrow-left"></i></x-link><span class="ml-4">{{ __('Info akun') }}</span>
                </h2>
            </div>
        </div>
    </header>
</x-slot>
<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
      No matter what the people say
    </div>
</div>
