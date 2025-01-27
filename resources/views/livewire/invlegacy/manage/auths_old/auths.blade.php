<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Database\Eloquent\Builder;

use App\Models\InvAuth;

new #[Layout('layouts.app')] 
class extends Component {

    #[Url]
    public $q;
    public $perPage = 10;

    #[On('updated')]
    public function with(): array
    {
        $q = trim($this->q);
        $auths = InvAuth::join('users', 'inv_auths.user_id', '=', 'users.id')
        ->join('inv_areas', 'inv_auths.inv_area_id', '=', 'inv_areas.id')
        ->select('inv_auths.*', 'users.name as user_name', 'users.emp_id as user_emp_id', 'users.photo as user_photo', 'inv_areas.name as inv_area_name')
        
        ->orderBy('inv_auths.user_id', 'desc');
        
        if ($q) {
            $auths->where(function (Builder $query) use ($q) {
                $query->orWhere('users.name', 'LIKE', '%'.$q.'%')
                      ->orWhere('users.emp_id', 'LIKE', '%'.$q.'%')
                      ->orWhere('inv_areas.name','LIKE', '%'.$q.'%');
            });
        }

        $auths = $auths->paginate($this->perPage);

        return [
         'auths' => $auths
      ];
    }

}

?>

<x-slot name="title">{{ __('Wewenang') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
   <header class="bg-white dark:bg-neutral-800 shadow">
      <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div>  
              <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                  <x-link href="{{ route('invlegacy.manage.index', ['view' => 'administration']) }}" class="inline-block py-6" wire:navigate><i class="fa fa-arrow-left"></i></x-link><span class="ml-4"><span class="hidden sm:inline">{{ __('Wewenang') }}</span></span>
              </h2>
          </div>
      </div>
  </header>
</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
   <div>
      <div class="w-full mt-5">
          <div class="flex justify-between px-6 sm:px-0">
              <div>
                  {{ count($auths) . ' ' . __('wewenang') }}
              </div>
              <div>
                  @can('superuser')
                  <x-secondary-button type="button" class="my-auto" x-data=""
                  x-on:click.prevent="$dispatch('open-modal', 'create-auth')">{{ __('Beri wewenang') }}</x-secondary-button>
                  @endcan
              </div>
          
          </div>
          <x-modal name="create-auth">
              <livewire:layout.inv-auths-form wire:key="auths-create" lazy />
          </x-modal>
          <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg mt-5">          
              <div class="flex items-center justify-between px-6 py-3">
                  <div class="text-xs font-bold uppercase">{{ __('Pengguna') }}</div>
                  <div class="w-40">
                      <x-text-input-search wire:model.live="q" id="inv-q" placeholder="{{ __('CARI') }}"></x-text-input-search>
                  </div>
              </div>  
              <hr class="border-neutral-200 dark:border-neutral-700" />
              <table wire:key="auths-table" class="table">
                  @foreach($auths as $auth)
                  <tr wire:key="auth-tr-{{ $auth->id . $loop->index }}" tabindex="0" x-on:click="$dispatch('open-modal', 'edit-auth-{{ $auth->id }}')">
                      <td>
                          <div class="flex">
                              <div>
                                  <div class="w-8 h-8 my-auto mr-3 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                      @if($auth->user_photo)
                                      <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$auth->user_photo }}" />
                                      @else
                                      <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                      @endif
                                  </div>
                              </div>
                              <div>
                                  <div>{{ $auth->user_name }}</div>
                                  <div class="text-xs text-neutral-400 dark:text-neutral-600" >{{ $auth->user_emp_id }}</div>
                              </div>            
                          </div>
                      </td> 
                      <td>
                          {{ $auth->inv_area_name }}
                      </td>
                      <td>
                          {{ $auth->countActions() .' '.__('tindakan') }}
                      </td>
                  </tr>
                  <x-modal :name="'edit-auth-'.$auth->id">
                      <livewire:layout.inv-auths-form wire:key="auth-lw-{{ $auth->id . $loop->index }}" :auth="$auth" lazy />                    
                  </x-modal> 
                  @endforeach
              </table>
              <div wire:key="auths-none">
                  @if(!$auths->count())
                      <div class="text-center py-12">
                          {{ __('Kosong') }}
                      </div>
                  @endif
              </div>
          </div>
      </div>  
      <div wire:key="observer" class="flex items-center relative h-16">
          @if(!$auths->isEmpty())
          @if($auths->hasMorePages())
              <div wire:key="more" x-data="{
                  observe(){
                      const observer = new IntersectionObserver((auths) => {
                          auths.forEach(auth => {
                              if(auth.isIntersecting) {
                                  @this.loadMore()
                              }
                          })
                      })
                      observer.observe(this.$el)
                  }
              }" x-init="observe"></div>
              <x-spinner class="sm" />
          @else
              <div class="mx-auto">{{__('Tidak ada lagi')}}</div>
          @endif
          @endif
      </div>
  </div>
</div>
