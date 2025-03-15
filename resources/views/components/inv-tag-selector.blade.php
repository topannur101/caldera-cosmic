@props(['isQuery' => false ])

<div x-data="{
      tags: @entangle('tags').live,
      tag_input: '',
      tag_hints: [],
      get tag_list() {
         return this.tags.join(', ');
      },
      async updateHints() {
         if (this.tag_input.trim() === '') {
            this.tag_hints = [];
            return;
         }
         
         try {
            const query = this.tag_input.toLowerCase().trim();
            const response = await fetch(`/api/inv-tags?q=${encodeURIComponent(query)}`);
            
            if (response.ok) {
               const data = await response.json();
               // Filter out tags that are already selected and add space after each tag
               this.tag_hints = data
                  .filter(tag => !this.tags.includes(tag))
                  .map(tag => tag + ' '); // Add space at the end for each hint
            } else {
               console.error('Failed to fetch tag suggestions');
               this.tag_hints = [];
            }
         } catch (error) {
            console.error('Error fetching tag suggestions:', error);
            this.tag_hints = [];
         }
      },
      watchTagInput() {
         // Check if input contains a space or a comma
         if (this.tag_input.includes(' ') || this.tag_input.includes(',')) {
            // Remove the space or comma from the input
            this.tag_input = this.tag_input.replace(/[ ,]/g, '');

            // Process the tag if it's not empty
            if (this.tag_input) {
               this.addTag(this.tag_input);
            }
         }
      },
      addTag() { 
         let tag = this.tag_input.trim().toLowerCase();

         if (this.tags.includes(tag)) {
            toast('{{ __('Tag sudah ada') }}', { type: 'danger' });
            return;
         }

         if (this.tags.length >= 3) {
            toast('{{ __('Hanya maksimal 3 tag diperbolehkan') }}', { type: 'danger' });
            return;
         }

         if (!tag) {
            this.$dispatch('close');            
            return;

         } else {
            this.tags.push(tag);
            this.tag_input = '';

         }
      },
      removeTag(tag) {
         this.tags = this.tags.filter(t => t !== tag);
      }
   }" class="flex items-center {{ $isQuery ? 'px-4' : '' }}">
   <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'tag-selector')" ::class="tag_list ? 'text-neutral-800 dark:text-white' : 'text-neutral-400 dark:text-neutral-600'">
      <i class="fa fa-fw fa-tag me-3"></i><span x-text="tag_list ? tag_list : '{{ __('Tag') }}'"></span>
   </x-text-button>
   
   <x-modal name="tag-selector" maxWidth="sm" focusable>
      <div>
         <div class="p-6 flex flex-col gap-y-6">
            <div class="flex justify-between items-start">
               <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                  <i class="fa fa-fw fa-tag me-3"></i>{{ __('Tag') }}
               </h2>
               <x-text-button type="button" x-on:click="$dispatch('close')">
                  <i class="fa fa-times"></i>
               </x-text-button>
            </div>
            <div class="grid grid-cols-1 gap-y-6">
               <div class="flex flex-wrap gap-2 text-sm">
                  <div x-show="tags.length === 0" class="text-neutral-500 italic py-1">{{ __('Tak ada tag') }}</div>
                  <template x-for="tag in tags" :key="tag">
                     <div class="bg-neutral-200 dark:bg-neutral-900 rounded-full px-3 py-1">
                        <span x-text="tag"></span>
                        <x-text-button type="button" x-on:click="removeTag(tag)" class="ml-2">
                           <i class="fa fa-times"></i>
                        </x-text-button>
                     </div>
                  </template>
               </div>
               <x-text-input-icon
                  id="tag-search"
                  list="tag_hints"
                  icon="fa fa-fw fa-search"
                  type="text"
                  x-model="tag_input"
                  maxlength="20"
                  placeholder="{{ __('Cari tag') }}"
                  x-on:input="updateHints(); watchTagInput()"
                  x-on:keydown.enter="addTag" />
               <datalist id="tag_hints">
                  <template x-for="hint in tag_hints">
                        <option :value="hint"></option>
                  </template>
               </datalist>
            </div>            
            <div class="flex justify-end">
               <x-text-button class="text-xs uppercase font-semibold" type="button" x-on:click="tags = []; $dispatch('close');" x-show="tags.length"><span class="text-red-500"><div class="px-1">{{ $isQuery ? __('Hapus filter tag') : __('Hapus tag') }}</div></span></x-text-button>
            </div>
         </div>
      </div>
   </x-modal>                          
</div>