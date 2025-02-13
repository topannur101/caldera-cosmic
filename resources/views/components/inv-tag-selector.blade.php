@props(['isQuery' => false ])

<div x-data="{
      tags: @entangle('tags'),
      tag_input: '',
      get tag_list() {
         return this.tags.join(', ');
      },
      addTag() {
         let tag = this.tag_input.trim().toLowerCase();
         if (!tag) return;
         
         if (this.tags.length >= 5) {
               toast('{{ __('Hanya maksimal 5 tag diperbolehkan') }}', { type: 'danger' });
               return;
         }
         
         if (this.tags.includes(tag)) {
               toast('{{ __('Tag sudah ada') }}', { type: 'danger' });
         } else {
               this.tags.push(tag);
         }
         
         this.tag_input = '';
      },
      removeTag(tag) {
         this.tags = this.tags.filter(t => t !== tag);
      }
   }" class="flex items-center {{ $isQuery ? 'px-4' : '' }}">
   <x-text-button {{ $attributes->merge(['class' => '']) }} type="button" x-on:click.prevent="$dispatch('open-modal', 'tag-selector')">
      <i class="fa fa-fw fa-tag me-3"></i><span x-text="tag_list ? tag_list : '{{ $isQuery ? __('Tag') : __('Tak ada tag') }}'"></span>
   </x-text-button>
   
   <x-modal name="tag-selector" maxWidth="sm" focusable>
      <div>
         <form wire:submit.prevent="save" class="p-6">
            <div class="flex justify-between items-start">
               <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                  <i class="fa fa-fw fa-tag me-3"></i>{{ __('Pilih tag') }}
               </h2>
               <x-text-button type="button" x-on:click="$dispatch('close')">
                  <i class="fa fa-times"></i>
               </x-text-button>
            </div>
            <div class="grid grid-cols-1 gap-y-6 mt-6">
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
               <x-text-input-icon id="tag-search" icon="fa fa-fw fa-search" type="text" x-model="tag_input" maxlength="20" placeholder="{{ __('Cari tag') }}" x-on:keydown.enter.prevent="addTag" />
               <datalist id="tags">
                  @foreach($tags as $tag)
                     <option value="{{ $tag }}"></option>
                  @endforeach
               </datalist>
            </div>  
         </form>
      </div>
   </x-modal>                           
</div>