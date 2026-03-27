<?php

use Livewire\Volt\Component;
use App\Services\InsPpmExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $importFile = null;
    public $importing = false;
    public $result = null;
    public $errorMessages = [];

    public function rules()
    {
        return [
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    public function import()
    {
        $this->validate();

        $this->importing = true;
        $this->errorMessages = [];
        $this->result = null;

        try {
            // Store the file temporarily
            $path = $this->importFile->store('temp', 'local');
            
            // Get full path
            $fullPath = storage_path('app/' . $path);
            
            // Use the import service
            $service = new InsPpmExcelImportService();
            $importResult = $service->import($fullPath);
            
            // Delete the temp file
            unlink($fullPath);

            if ($importResult['success']) {
                $this->result = $importResult;
                $this->js('toast("' . $importResult['message'] . '", { type: "success" })');
                $this->dispatch('updated');
            } else {
                $this->errorMessages = $importResult['summary']['errors'] ?? [];
                $this->js('toast("' . $importResult['message'] . '", { type: "error" })');
            }

        } catch (\Exception $e) {
            Log::error('PPM Import Error', ['error' => $e->getMessage()]);
            $this->errorMessages[] = $e->getMessage();
            $this->js('toast("Error: ' . $e->getMessage() . '", { type: "error" })');
        } finally {
            $this->importing = false;
        }
    }

    public function downloadTemplate()
    {
        return redirect()->route('download.ins-ppm-process-template');
    }

    public function resetForm()
    {
        $this->reset(['importFile', 'result']);
        $this->errorMessages = [];
    }
};
?>

<div>
    <form wire:submit="import" class="p-6">
        <div class="flex justify-between items-start pb-4 border-b border-neutral-200 dark:border-neutral-700">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __("Import Proses") }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-text-button>
        </div>

        <!-- Template Download -->
        <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        {{ __("Unduh template untuk melihat format yang benar") }}
                    </p>
                </div>
                <x-secondary-button type="button" wire:click="downloadTemplate">
                    <i class="icon-download mr-1"></i> {{ __("Template") }}
                </x-secondary-button>
            </div>
        </div>

        <!-- File Upload -->
        <div class="mt-4">
            <label for="import-file" class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                {{ __("Pilih File Excel") }} *
            </label>
            <input 
                type="file" 
                id="import-file" 
                wire:model="importFile" 
                accept=".xlsx,.xls,.csv"
                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
            @error('importFile')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- File Info -->
        @if ($importFile)
            <div class="mt-3 p-3 bg-neutral-50 dark:bg-neutral-900 rounded-lg">
                <div class="flex items-center gap-2">
                    <i class="icon-file text-neutral-500"></i>
                    <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ $importFile->getClientOriginalName() }}</span>
                    <span class="text-xs text-neutral-500">({{ round($importFile->getSize() / 1024) }} KB)</span>
                </div>
            </div>
        @endif

        <!-- Errors -->
        @if (!empty($errorMessages))
            <div class="mt-4 p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
                <h4 class="text-sm font-medium text-red-800 dark:text-red-200">{{ __("Errors") }}:</h4>
                <ul class="mt-2 list-disc list-inside text-sm text-red-700 dark:text-red-300">
                    @foreach ($errorMessages as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Result -->
        @if ($result && $result['success'])
            <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/30 rounded-lg">
                <h4 class="text-sm font-medium text-green-800 dark:text-green-200">{{ __("Import Successful") }}:</h4>
                <ul class="mt-2 list-disc list-inside text-sm text-green-700 dark:text-green-300">
                    <li>{{ __("Products Created") }}: {{ $result['summary']['products_created'] }}</li>
                    <li>{{ __("Products Updated") }}: {{ $result['summary']['products_updated'] }}</li>
                    <li>{{ __("Components Created") }}: {{ $result['summary']['components_created'] }}</li>
                    <li>{{ __("Components Updated") }}: {{ $result['summary']['components_updated'] }}</li>
                    <li>{{ __("Processes Created") }}: {{ $result['summary']['processes_created'] }}</li>
                    <li>{{ __("Processes Updated") }}: {{ $result['summary']['processes_updated'] }}</li>
                </ul>
            </div>
        @endif

        <div class="mt-6 flex justify-end gap-3">
            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                {{ __("Batal") }}
            </x-secondary-button>
            <x-primary-button type="submit" wire:disabled="importing">
                @if ($importing)
                    <i class="icon-loader animate-spin mr-1"></i> {{ __("Mengimport...") }}
                @else
                    <i class="icon-file mr-1"></i> {{ __("Import") }}
                @endif
            </x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
