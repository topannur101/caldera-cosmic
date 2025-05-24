
   <div x-data="{ slideOverOpen: false }"
      class="relative w-auto h-auto">
      <x-primary-button type="button" @click="slideOverOpen=true">{{ __('Buat') }}</x-secondary-button>
      <template x-teleport="body">
         <div 
            x-show="slideOverOpen"
            @keydown.window.escape="slideOverOpen=false"
            class="relative z-50">
            <div x-show="slideOverOpen" class="fixed {{ session('mblur') ? 'backdrop-blur' : ''}}  inset-0 transform transition-all"
               x-on:click="slideOverOpen = false"
               x-transition:enter="ease-out duration-300"
               x-transition:enter-start="opacity-0"
               x-transition:enter-end="opacity-100"
               x-transition:leave="ease-in duration-200"
               x-transition:leave-start="opacity-100"
               x-transition:leave-end="opacity-0"
            >
               <div class="absolute inset-0 bg-neutral-500 dark:bg-neutral-900 opacity-75"></div>
            </div>
            <div class="fixed inset-0 overflow-hidden">
               <div class="absolute inset-0 overflow-hidden">
                  <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
                     <div 
                        x-show="slideOverOpen" 
                        @click.away="slideOverOpen = false"
                        x-transition:enter="transform transition ease-out duration-300" 
                        x-transition:enter-start="translate-x-full" 
                        x-transition:enter-end="translate-x-0" 
                        x-transition:leave="transform transition ease-in duration-200" 
                        x-transition:leave-start="translate-x-0" 
                        x-transition:leave-end="translate-x-full" 
                        class="w-screen max-w-md">
                        <div class="bg-white dark:bg-neutral-800 flex flex-col h-full text-neutral-900 dark:text-neutral-100">
                           {{ $slot }}
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </template>
   </div>