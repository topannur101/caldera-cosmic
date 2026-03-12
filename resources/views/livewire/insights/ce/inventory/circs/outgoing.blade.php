<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Models\User;
use App\Models\InvCeAuth;


new #[Layout("layouts.app")] class extends Component {} ?>

<x-slot name="title">{{ __("Cari") . " — " . __("Inventaris") }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-ce></x-nav-inventory-ce>
</x-slot>

<div>
    <h1>Outgoing Circs</h1>
</div>