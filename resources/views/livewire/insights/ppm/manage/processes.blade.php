<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\InsPpmProduct;
use App\Models\InsPpmComponent;
use App\Models\InsPpmComponentsProcess;

new #[Layout("layouts.app")] class extends Component {
    public $products;

    public function mount()
    {
        $this->loadProducts();
    }

    #[On("updated")]
    public function loadProducts()
    {
        $this->products = InsPpmProduct::with('components.processes')
            ->orderBy('production_date', 'desc')
            ->get();
    }

};

?>

<x-slot name="title">{{ __("Kelola") . " — " . __("Pemantauan Printing Process") }}</x-slot>

<x-slot name="header">
    <x-nav-insights-ppm></x-nav-insights-ppm>
</x-slot>

<div id="content" class="py-12 max-w-5xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <!-- Flash Messages -->
        @if (session()->has('message'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-y-6 justify-between px-6">
            <h1 class="text-2xl text-neutral-900 dark:text-neutral-100">{{ __("Proses Komponen") }}</h1>
            <div class="flex justify-end gap-x-2">
                <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'process-import')">
                    <i class="icon-file mr-1"></i> {{ __("Import") }}
                </x-secondary-button>
                <x-secondary-button type="button" x-on:click.prevent="$dispatch('open-modal', 'process-create')"><i class="icon-plus"></i></x-secondary-button>
            </div>
        </div>
        
        <div class="overflow-auto w-full my-8">
            <div class="p-0 sm:p-1">
                <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
                    <table wire:key="products-table" class="table">
                        <tr>
                            <th>{{ __("Product Code") }}</th>
                            <th>{{ __("Dev Style") }}</th>
                            <th>{{ __("Color Way") }}</th>
                            <th>{{ __("Production Date") }}</th>
                            <th>{{ __("Component") }}</th>
                            <th>{{ __("Processes") }}</th>
                        </tr>
                        @forelse ($products as $product)
                            @foreach ($product->components as $component)
                                <tr
                                    wire:key="product-tr-{{ $product->id }}-{{ $component->id }}"
                                    tabindex="0"
                                    x-on:click="
                                        $dispatch('open-modal', 'process-edit')
                                        $dispatch('process-edit', { productId: {{ $product->id }}, componentId: {{ $component->id }} })
                                    "
                                >
                                    <td>
                                        {{ $product->product_code }}
                                    </td>
                                    <td>
                                        {{ $product->dev_style }}
                                    </td>
                                    <td>
                                        {{ $product->color_way }}
                                    </td>
                                    <td>
                                        {{ $product->production_date?->format('d/m/Y') }}
                                    </td>
                                    <td>
                                        {{ $component->part_name }}
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            {{ $component->processes->count() }} {{ __("Process") }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-12">
                                    {{ __("Tak ada data ditemukan") }}
                                </td>
                            </tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>

        <div wire:key="process-create-modal-wrapper">
            <x-modal name="process-create" maxWidth="xl">
                <livewire:insights.ppm.manage.process-create wire:key="ppm-process-create-modal" lazy />
            </x-modal>
        </div>
        <div wire:key="process-import-modal-wrapper">
            <x-modal name="process-import" maxWidth="lg">
                <livewire:insights.ppm.manage.process-import wire:key="ppm-process-import-modal" lazy />
            </x-modal>
        </div>
        <div wire:key="process-edit-modal-wrapper">
            <x-modal name="process-edit" maxWidth="xl">
                <livewire:insights.ppm.manage.process-edit wire:key="ppm-process-edit-modal" lazy />
            </x-modal>
        </div>
    </div>
</div>