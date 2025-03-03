@props(['isQuery' => false ])

<div x-data="{ 
         user_id: @entangle('user_id').live,
         users: @entangle('users').live,
         get user_name() {
            if (!this.user_id) {
                  return '';
            }
            const user = this.users.find(user => user.id === this.user_id);
            if (user) {
                  return user.name;
            } else {
                  this.user_id = 0;
                  return '';
            }
         }
      }" class="flex items-center {{ $isQuery ? 'px-4' : '' }}">
      <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'user-selector')" ::class="user_name ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'"><i class="fa fa-fw fa-user me-3"></i><span x-text="user_name ? user_name : '{{ $isQuery ? __('Pengguna') : __('Filter pengguna') }}'"></span></x-text-button>
      <x-modal name="user-selector" maxWidth="sm">
         <div class="grid grid-cols-1 gap-y-6 p-6">
            <div class="flex justify-between items-start">
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    <i class="fa fa-fw fa-user me-3"></i>{{ __('Pengguna') }}
                </h2>
                <x-text-button type="button" x-on:click="$dispatch('close')">
                    <i class="fa fa-times"></i>
                </x-text-button>
            </div> 
            <div class="flex flex-wrap gap-2">
               <template x-for="user in users" :key="user.id">
                <x-secondary-button size="sm" type="button" x-on:click="user_id = user.id; $dispatch('close')">
                  <div class="flex justify-between gap-x-2">
                     <div class="w-4 h-4 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">                        
                        <img x-show="user.photo" class="w-full h-full object-cover dark:brightness-75"
                           :src="'/storage/users/' + user.photo" />                        
                        <svg x-show="!user.photo" xmlns="http://www.w3.org/2000/svg"
                           class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                           viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                           <path
                                 d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                        </svg>                        
                     </div>
                     <div class="max-w-16 truncate" x-text="user.name"></div>
                  </div>
                </x-secondary-button>
               </template>
               <x-secondary-button size="sm" type="button" x-on:click="user_id = 0;$dispatch('close');" x-show="user_id"><span class="text-red-500"><div class="px-1">{{ __('Hapus filter pengguna') }}</div></span></x-secondary-button>
               <div x-show="!users.length" class="text-sm">{{ __('Tak ada pengguna yang bisa dipilih') }}</div>
            </div>
        </div>
    </x-modal>   
</div>