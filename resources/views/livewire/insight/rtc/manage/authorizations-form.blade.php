<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

use App\Models\User;
use App\Models\InsRtcAuth;
use Livewire\Attributes\Renderless;
use Illuminate\Support\Facades\Gate;

new #[Layout('layouts.app')] class extends Component {
    public InsRtcAuth $auth;
    public $auth_id = '';
    public $userq;
    public $user_id;
    public $actions = [];
    public $is_superuser = false;

    public function rules()
    {
        return [
            'user_id' => ['required', 'integer', 'exists:App\Models\User,id'],
        ];
    }

    public function placeholder()
    {
        return view('livewire.layout.modal-placeholder');
    }

    public function mount(InsRtcAuth $auth)
    {
        if ($auth->id) {
            $this->auth_id = $auth->id;
            $this->userq = $auth->user->emp_id;
            $this->actions = json_decode($auth->actions, true);
        }
        $this->is_superuser = Gate::allows('superuser');
    }

    public function save()
    {
        Gate::authorize('superuser');
       
        $this->userq = trim($this->userq);
        // delegate to...
        if ($this->userq) {
            $user = User::where('emp_id', $this->userq)->first();
            $this->user_id = $user ? $user->id : '';
        }
        // VALIDATE 
        $this->validate();
        if ($this->user_id == 1) {
            $this->js('notyf.error("'.__('Superuser sudah memiliki wewenang penuh').'")'); 
        } else {
            $auth = InsRtcAuth::updateOrCreate(
                ['user_id' => $this->user_id],
                ['actions' => json_encode($this->actions)]
            ); 
            $this->js('notyf.success("'.__('Wewenang diperbarui').'")'); 
            $this->dispatch('updated');
        }
        !$this->auth_id ? $this->reset(['userq', 'user_id', 'actions']) : false;
        $this->js('window.dispatchEvent(escKey)'); 
    }

    public function delete()
    {
        $this->auth->delete();
        $this->js('window.dispatchEvent(escKey)'); 
        $this->js('notyf.success("'.__('Wewenang dicabut').'")'); 
        $this->dispatch('updated');
    }
    
    #[Renderless]
    public function updatedUserq()
    {
        $this->dispatch('userq-updated', $this->userq);
    }


};

?>
<div>
   <form wire:submit="save" class="p-6">
       <div class="flex justify-between items-start">
           <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
               {{ __('Wewenang') }}
           </h2>
           <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
       </div>
       <div class="grid grid-cols-1 gap-y-3 mt-3">
           @if($auth_id)
           <div class="grid gap-3 grid-cols-1">
               <div class="flex p-4 border border-neutral-200 dark:border-neutral-700 rounded-lg">
                   <div>
                       <div class="w-8 h-8 my-auto mr-3 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                           @if($auth->user->photo)
                           <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$auth->user->photo }}" />
                           @else
                           <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                           @endif
                       </div>
                   </div>
                   <div class="truncate">
                       <div class="truncate">{{ $auth->user->name }}</div>
                       <div class="truncate text-xs text-neutral-400 dark:text-neutral-600" >{{ $auth->user->emp_id }}</div>
                   </div>            
               </div>
           </div>
           @else
               <div x-data="{ open: false, userq: @entangle('userq').live }" x-on:user-selected="userq = $event.detail; open = false">
                   <div x-on:click.away="open = false">
                       <x-text-input-icon x-model="userq" icon="fa fa-fw fa-user" x-on:change="open = true"
                           x-ref="userq" x-on:focus="open = true" id="inv-user" class="mt-3" type="text" autocomplete="off"
                           placeholder="{{ __('Pengguna') }}" />
                       <div class="relative" x-show="open" x-cloak>
                           <div class="absolute top-1 left-0 w-full">
                               <livewire:layout.user-select wire:key="user-select" />
                           </div>
                       </div>
                   </div>
                   <div wire:key="error-user_id">
                       @error('user_id')
                           <x-input-error messages="{{ $message }}" class="mt-2" />
                       @enderror
                   </div>
               </div>           
           @endif
       </div>
       <div class="grid grid-cols-1 gap-y-3 mt-6">
         <x-checkbox id="{{ $auth->id ?? 'new'}}-device-manage" :disabled="!$is_superuser" wire:model="actions" value="device-manage">{{ __('Kelola perangkat ') }}</x-checkbox>
         <x-checkbox id="{{ $auth->id ?? 'new'}}-recipe-manage" :disabled="!$is_superuser" wire:model="actions" value="recipe-manage">{{ __('Kelola resep ') }}</x-checkbox>  
       </div>
       @can('superuser')
       <div class="mt-6 flex justify-between items-end">
           <x-secondary-button type="submit">
               <i class="fa fa-save me-2"></i>
               {{ __('Simpan') }}
           </x-secondary-button>
           <div>
               @if($auth_id)
               <x-text-button type="button" class="uppercase text-xs text-red-500" wire:click="delete">
                   {{ __('Cabut') }}
               </x-text-button>
               @endif
           </div>
       </div>
       @endcan
   </form>
   <x-spinner-bg wire:loading.class.remove="hidden" wire:target="delete"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" wire:target="delete" class="hidden"></x-spinner>
   <x-spinner-bg wire:loading.class.remove="hidden" wire:target="save"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" wire:target="save" class="hidden"></x-spinner>
</div>
