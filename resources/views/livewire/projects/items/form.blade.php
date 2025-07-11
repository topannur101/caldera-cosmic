<?php

// resources/livewire/projects/items/form.blade.php
use Livewire\Volt\Component;
use App\Models\PjtTeam;
use App\Models\PjtItem;
use App\Models\PjtMember;
use App\Models\User;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\On;
use Carbon\Carbon;

new class extends Component
{
    public int $id_new = 0;
    public bool $is_editing = false;
    
    public array $teams = [];
    public array $users = [];
    
    // Form fields
    public string $name = '';
    public string $desc = '';
    public string $location = '';
    public string $photo = '';
    public int $team_id = 0;
    public string $status = 'active';
    public array $member_ids = [];
    
    public function mount($teams = [])
    {
        $this->teams = $teams;
        
        // Load all users for member selection
        $this->users = User::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'emp_id' => $user->emp_id,
                    'photo' => $user->photo
                ];
            })
            ->toArray();
    }

    public function save()
    {
        // Clean up inputs
        $this->name = trim($this->name);
        $this->desc = trim($this->desc);
        $this->location = trim($this->location);

        $this->validate([
            'name' => ['required', 'max:128'],
            'desc' => ['required', 'max:256'],
            'team_id' => ['required', 'exists:pjt_teams,id'],
            'location' => ['nullable', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
            'member_ids' => ['array', 'max:20'],
            'member_ids.*' => ['exists:users,id'],
            'photo' => ['nullable'],
        ]);

        try {
            // Create project
            $project = PjtItem::create([
                'name' => $this->name,
                'desc' => $this->desc,
                'pjt_team_id' => $this->team_id,
                'user_id' => Auth::user()->id,
                'location' => $this->location ?: null,
                'photo' => $this->photo,
                'status' => $this->status,
            ]);

            // Add project members
            foreach ($this->member_ids as $userId) {
                PjtMember::create([
                    'pjt_project_id' => $project->id,
                    'user_id' => $userId,
                ]);
            }

            // Add project owner as member if not already included
            if (!in_array(Auth::user()->id, $this->member_ids)) {
                PjtMember::create([
                    'pjt_project_id' => $project->id,
                    'user_id' => Auth::user()->id,
                ]);
            }

            $this->id_new = $project->id;
            $this->reset(['name', 'desc', 'location', 'photo', 'team_id', 'status', 'member_ids']);
            $this->dispatch('remove-photo');   
            $this->js('$dispatch("close")');
            $this->js('toast("' . __('Proyek berhasil dibuat') . '", { type: "success" })');

        } catch (\Exception $e) {
            $this->js('toast("' . __('Terjadi kesalahan saat menyimpan') . '", { type: "danger" })');
        }
    }

    #[Renderless] 
    #[On('photo-updated')] 
    public function insertPhoto($photo)
    {
        $this->photo = $photo;
    }

    public function getSelectedTeamName(): string
    {
        if ($this->team_id) {
            $team = collect($this->teams)->firstWhere('id', $this->team_id);
            return $team ? $team['name'] : '';
        }
        return '';
    }

    public function getSelectedMembersCount(): int
    {
        return count($this->member_ids);
    }
};

?>

<div class="relative flex flex-col h-full">
    <div class="flex justify-between items-start pt-6 pb-3 px-6">
        <h2 class="text-lg font-medium">
            {{ __('Proyek baru') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')">
            <i class="icon-x"></i>
        </x-text-button>
    </div>

    <div class="grow overflow-y-auto">      
        <div class="flex flex-col gap-y-6 py-4 px-6">
            
            {{-- Photo upload --}}
            <div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Foto') }}</label>
                <livewire:projects.items.photo size="sm" :id="0" :is_editing="true" :photo_url="$photo ? ('/storage/pjt-items/' . $photo) : ''" />
            </div>

            {{-- Basic Info --}}
            <div class="grid grid-cols-1 gap-y-4">
                <div>
                    <label for="form-name" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Nama project') }}</label>
                    <x-text-input id="form-name" wire:model="name" type="text" class="w-full" maxlength="128" />
                </div>
                <div>
                    <label for="form-desc" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Deskripsi') }}</label>
                    <x-text-input id="form-desc" wire:model="desc" type="text" class="w-full" maxlength="256" />
                </div>
                <div>
                    <label for="form-location" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Lokasi') }}</label>
                    <x-text-input id="form-location" wire:model="location" type="text" class="w-full" maxlength="100" />
                </div>
            </div>

            {{-- Team Selection --}}
            <div>
                <label for="form-team" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tim') }}</label>
                <x-select id="form-team" wire:model.change="team_id" class="w-full">
                    <option value="">{{ __('Pilih team...') }}</option>
                    @foreach($teams as $team)
                        <option value="{{ $team['id'] }}">{{ $team['name'] }}</option>
                    @endforeach
                </x-select>
            </div>

            {{-- Status Selection --}}
            <div>
                <label for="form-status" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Status') }}</label>
                <div class="mx-3">
                    <x-radio wire:model="status" id="status-active" name="status" value="active">
                        {{ __('Aktif') }}
                    </x-radio>
                    <x-radio wire:model="status" id="status-inactive" name="status" value="inactive">
                        {{ __('Nonaktif') }}
                    </x-radio>
                </div>
            </div>

            {{-- Member Selection --}}
            <div x-data="{
                members: @entangle('member_ids'),
                users: @entangle('users'),
                showDropdown: false,
                searchTerm: '',
                
                get filteredUsers() {
                    if (!this.searchTerm) return this.users;
                    return this.users.filter(user => 
                        user.name.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
                        user.emp_id.toLowerCase().includes(this.searchTerm.toLowerCase())
                    );
                },
                
                toggleMember(userId) {
                    if (this.members.includes(userId)) {
                        this.members = this.members.filter(id => id !== userId);
                    } else {
                        this.members.push(userId);
                    }
                },
                
                isMemberSelected(userId) {
                    return this.members.includes(userId);
                },
                
                getSelectedUsers() {
                    return this.users.filter(user => this.members.includes(user.id));
                }
            }">
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Anggota team') }}</label>
                
                {{-- Selected members display --}}
                <div class="flex flex-wrap gap-2 mb-3 px-3" x-show="members.length > 0">
                    <template x-for="user in getSelectedUsers()" :key="user.id">
                        <div class="flex items-center gap-x-2 bg-caldy-100 dark:bg-caldy-900 text-caldy-800 dark:text-caldy-200 px-3 py-1 rounded-full text-sm">
                            <div class="w-5 h-5 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden" x-show="user.photo">
                                <img :src="'/storage/users/' + user.photo" class="w-full h-full object-cover" x-show="user.photo" />
                            </div>
                            <div class="w-5 h-5 bg-neutral-300 dark:bg-neutral-600 rounded-full flex items-center justify-center text-xs" x-show="!user.photo">
                                <span x-text="user.name.charAt(0)"></span>
                            </div>
                            <span x-text="user.name"></span>
                            <button type="button" @click="toggleMember(user.id)" class="ml-1 hover:text-caldy-600">
                                <i class="icon-x text-xs"></i>
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Member selector dropdown --}}
                <div class="relative">
                    <x-text-button type="button" @click="showDropdown = !showDropdown" class="w-full justify-between border border-neutral-300 dark:border-neutral-700 px-3 py-2 rounded-md bg-white dark:bg-neutral-900">
                        <span x-text="members.length > 0 ? members.length + ' {{ __('anggota dipilih') }}' : '{{ __('Pilih anggota...') }}'"></span>
                        <i class="icon-chevron-down" :class="{'rotate-180': showDropdown}"></i>
                    </x-text-button>

                    <div x-show="showDropdown" x-cloak @click.away="showDropdown = false" 
                         class="absolute z-10 w-full mt-1 bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-md shadow-lg max-h-60 overflow-auto">
                        
                        {{-- Search input --}}
                        <div class="p-3 border-b border-neutral-200 dark:border-neutral-700">
                            <x-text-input x-model="searchTerm" type="text" placeholder="{{ __('Cari anggota...') }}" class="w-full" />
                        </div>

                        {{-- User list --}}
                        <div class="max-h-48 overflow-y-auto">
                            <template x-for="user in filteredUsers" :key="user.id">
                                <div @click="toggleMember(user.id)" 
                                     class="flex items-center gap-x-3 px-4 py-2 hover:bg-neutral-50 dark:hover:bg-neutral-700 cursor-pointer">
                                    <input type="checkbox" :checked="isMemberSelected(user.id)" class="h-4 w-4 text-caldy-600 rounded" />
                                    <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                        <img :src="'/storage/users/' + user.photo" class="w-full h-full object-cover" x-show="user.photo" />
                                        <div x-show="!user.photo" class="w-full h-full flex items-center justify-center text-sm text-neutral-600 dark:text-neutral-400">
                                            <span x-text="user.name.charAt(0)"></span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium text-sm" x-text="user.name"></div>
                                        <div class="text-xs text-neutral-500" x-text="user.emp_id"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Summary --}}
            @if($team_id || count($member_ids) > 0)
                <div class="bg-neutral-50 dark:bg-neutral-900 rounded-lg p-4 space-y-2 text-sm">
                    <h4 class="font-medium mb-2">{{ __('Ringkasan') }}</h4>
                    @if($team_id)
                        <div class="flex justify-between">
                            <span>{{ __('Tim') }}:</span>
                            <span class="font-medium">{{ $this->getSelectedTeamName() }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span>{{ __('Anggota') }}:</span>
                        <span class="font-medium">{{ $this->getSelectedMembersCount() }} {{ __('orang') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ __('Status') }}:</span>
                        <span class="font-medium">{{ $status === 'active' ? __('Aktif') : __('Nonaktif') }}</span>
                    </div>
                </div>
            @endif

            {{-- Error message --}}
            @if ($errors->any())
                <div class="text-center">
                    <x-input-error :messages="$errors->first()" />
                </div>
            @endif

            {{-- Save button --}}
            <div class="flex justify-end">
                <div wire:loading>
                    <x-primary-button type="button" disabled>
                        <i class="icon-save mr-2"></i>{{ __('Simpan') }}
                    </x-primary-button>
                </div>
                <div wire:loading.remove>
                    <x-primary-button type="button" wire:click="save">
                        <i class="icon-save mr-2"></i>{{ __('Simpan') }}
                    </x-primary-button>
                </div>
            </div>
        </div>
    </div>

    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>