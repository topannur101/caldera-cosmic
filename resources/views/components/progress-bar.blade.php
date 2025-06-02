<div class="w-72" x-data="{ ...progressApp(), progress: @entangle('progress') }" x-init="observeProgress()">
   <div class="flex justify-between text-sm">
         <div>
            {{ $slot }}
         </div>
         <div><span wire:stream="progress">{{ $progress }}</span>%</div>
   </div>
   <div class="cal-shimmer mt-1 relative w-full bg-neutral-200 rounded-full h-1.5 dark:bg-neutral-700">
         <div 
            class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500 transition-all duration-200"
            :style="'width:' + progress + '%'" 
            style="width:0%;">
         </div>
   </div>
</div>