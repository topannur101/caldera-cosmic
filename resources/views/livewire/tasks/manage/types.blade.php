<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\TskType;

use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component {
    
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public bool $isEditing = false;
    public int $editingId = 0;
    
    // Form fields
    public string $name = '';
    public bool $is_active = true;

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->reset(['name', 'is_active', 'editingId', 'isEditing']);
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEditModal($typeId)
    {
        $type = TskType::findOrFail($typeId);
        
        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->is_active = $type->is_active;
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:tsk_types,name,' . ($this->editingId ?: 'NULL'),
            'is_active' => 'boolean',
        ], [
            'name.required' => 'Nama tipe harus diisi.',
            'name.unique' => 'Nama tipe sudah digunakan.',
        ]);

        try {
            if ($this->isEditing) {
                $type = TskType::findOrFail($this->editingId);
                $type->update([
                    'name' => $this->name,
                    'is_active' => $this->is_active,
                ]);
                session()->flash('success', 'Tipe tugas berhasil diperbarui.');
            } else {
                TskType::create([
                    'name' => $this->name,
                    'is_active' => $this->is_active,
                ]);
                session()->flash('success', 'Tipe tugas berhasil dibuat.');
            }

            $this->closeModal();
        } catch (\Exception $e) {
            $this->addError('general', 'Terjadi kesalahan. Silakan coba lagi.');
        }
    }

    public function toggleStatus($typeId)
    {
        try {
            $type = TskType::findOrFail($typeId);
            $type->update(['is_active' => !$type->is_active]);
            
            $status = $type->is_active ? 'diaktifkan' : 'dinonaktifkan';
            session()->flash('success', "Tipe tugas berhasil {$status}.");
        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan saat mengubah status.');
        }
    }

    public function delete($typeId)
    {
        try {
            $type = TskType::findOrFail($typeId);
            
            if (!$type->canBeDeleted()) {
                session()->flash('error', 'Tipe tugas tidak dapat dihapus karena masih digunakan oleh tugas.');
                return;
            }

            $type->delete();
            session()->flash('success', 'Tipe tugas berhasil dihapus.');
        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan saat menghapus tipe tugas.');
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['name', 'is_active', 'editingId', 'isEditing']);
        $this->resetValidation();
    }

    public function with(): array
    {
        $query = TskType::withTasksCount();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        return [
            'types' => $query->orderBy('name')->paginate(15)
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Kelola Tipe Tugas</h1>
            <p class="mt-1 text-sm text-gray-600">
                Kelola tipe-tipe tugas yang dapat digunakan dalam sistem.
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button type="button" 
                    wire:click="openCreateModal"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Tambah Tipe
            </button>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-6">
        <div class="max-w-md">
            <input type="text" 
                   wire:model.live.debounce.300ms="search"
                   placeholder="Cari tipe tugas..."
                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
            <div class="flex">
                <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="ml-3 text-sm text-green-700">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
            <div class="flex">
                <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="ml-3 text-sm text-red-700">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Types Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Nama Tipe
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Jumlah Tugas
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Dibuat
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Aksi
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($types as $type)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $type->name }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($type->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    Nonaktif
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div class="flex items-center space-x-2">
                                <span>{{ $type->tsk_items_count ?? 0 }} total</span>
                                @if(($type->active_tasks_count ?? 0) > 0)
                                    <span class="text-blue-600">({{ $type->active_tasks_count }} aktif)</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $type->created_at->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <!-- Edit Button -->
                                <button type="button"
                                        wire:click="openEditModal({{ $type->id }})"
                                        class="text-blue-600 hover:text-blue-900 text-sm">
                                    Edit
                                </button>

                                <!-- Toggle Status Button -->
                                <button type="button"
                                        wire:click="toggleStatus({{ $type->id }})"
                                        class="text-gray-600 hover:text-gray-900 text-sm">
                                    {{ $type->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>

                                <!-- Delete Button -->
                                @if($type->canBeDeleted())
                                    <button type="button"
                                            wire:click="delete({{ $type->id }})"
                                            wire:confirm="Apakah Anda yakin ingin menghapus tipe tugas ini?"
                                            class="text-red-600 hover:text-red-900 text-sm">
                                        Hapus
                                    </button>
                                @else
                                    <span class="text-gray-400 text-sm cursor-not-allowed" title="Tidak dapat dihapus karena masih digunakan">
                                        Hapus
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            @if($search)
                                Tidak ada tipe tugas yang ditemukan untuk pencarian "{{ $search }}".
                            @else
                                Belum ada tipe tugas yang dibuat.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Pagination -->
        @if($types->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $types->links() }}
            </div>
        @endif
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit.prevent="save">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="w-full mt-3 text-center sm:mt-0 sm:text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                        {{ $isEditing ? 'Edit Tipe Tugas' : 'Tambah Tipe Tugas' }}
                                    </h3>

                                    <!-- Name Field -->
                                    <div class="mb-4">
                                        <label for="modal-name" class="block text-sm font-medium text-gray-700 mb-1">
                                            Nama Tipe *
                                        </label>
                                        <input type="text" 
                                               id="modal-name"
                                               wire:model="name"
                                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                               placeholder="Masukkan nama tipe tugas">
                                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <!-- Status Field -->
                                    <div class="mb-4">
                                        <label class="flex items-center">
                                            <input type="checkbox" 
                                                   wire:model="is_active"
                                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-700">Aktif</span>
                                        </label>
                                    </div>

                                    @error('general') 
                                        <div class="mb-4 bg-red-50 border border-red-200 rounded-md p-3">
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                {{ $isEditing ? 'Perbarui' : 'Simpan' }}
                            </button>
                            <button type="button"
                                    wire:click="closeModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>