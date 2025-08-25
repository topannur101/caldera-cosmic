<a
   href="{{ $url }}"
   @click="open = false"
   class="block w-full @if($presentation === 'page') p-4 @else px-4 py-2 @endif text-sm leading-5 transition duration-150 ease-in-out
   text-neutral-700 dark:text-neutral-300
   @if($presentation === 'page') hover:bg-caldy-500 hover:bg-opacity-10 @else hover:bg-neutral-100 dark:hover:bg-neutral-800 @endif
   focus:outline-none focus:bg-neutral-100 dark:focus:bg-neutral-800"
   wire:navigate>
   <div class="flex">
      <div>
         <div class="mt-1 flex justify-center items-center @if($presentation === 'page') w-6 h-6 @else w-4 h-4 @endif bg-caldy-600 rounded-full overflow-hidden">
            <i class="{{ $icon }} text-white" @if($presentation !== 'page') style="font-size:0.5rem" @endif></i>
         </div>
      </div>
      <div class="grow ml-2">
         <div>
            <span class="font-bold">{{ __('Fitur baru') }}</span>{{ ': ' . $content }}
         </div>
         <div class="text-xs text-neutral-500">
            {{ $notification->created_at->diffForHumans() }}
         </div>
      </div>
      @if(!$notification->read_at)
      <div>
         <div class="w-2 h-2 mt-2 rounded-full bg-caldy-500"></div>
      </div>
      @endif
   </div>
</a>
