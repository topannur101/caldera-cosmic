@props(['focus', 'icon'])

<div x-data="{
         popoverOpen: false,
         popoverArrow: true,
         popoverPosition: 'bottom',
         popoverHeight: 0,
         popoverOffset: 8,
         popoverHeightCalculate() {
            this.$refs.popover.classList.add('invisible'); 
            this.popoverOpen=true; 
            let that=this;
            $nextTick(function(){ 
                  that.popoverHeight = that.$refs.popover.offsetHeight;
                  that.popoverOpen=false; 
                  that.$refs.popover.classList.remove('invisible');
                  that.$refs.popoverInner.setAttribute('x-transition', '');
                  that.popoverPositionCalculate();
            });
         },
         popoverPositionCalculate(){
            if(window.innerHeight < (this.$refs.popoverButton.getBoundingClientRect().top + this.$refs.popoverButton.offsetHeight + this.popoverOffset + this.popoverHeight)){
                  this.popoverPosition = 'top';
            } else {
                  this.popoverPosition = 'bottom';
            }
         }
      }"
      x-init="
         that = this;
         window.addEventListener('resize', function(){
            popoverPositionCalculate();
         });
         $watch('popoverOpen', function(value){
            if(value) { popoverPositionCalculate(); setTimeout(() => document.getElementById('{{ $focus }}').focus(), 300);  }
         });
      "
      class="static sm:relative z-10">

   <button 
      type="button" 
      x-ref="popoverButton" 
      @click="popoverOpen=!popoverOpen" 
      :class="popoverOpen ? 'outline-none ring-2 ring-caldy-500 ring-offset-2 dark:ring-offset-neutral-800' : ''" 
      class="flex items-center justify-center w-10 h-10 cursor-pointer bg-white dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-500 rounded-full font-semibold text-neutral-700 dark:text-neutral-300 shadow-sm hover:bg-neutral-200 dark:hover:bg-neutral-700 disabled:opacity-25 transition ease-in-out duration-150">
      <i class="fa fa-fw {{ $icon }}"></i>
   </button>

   <div 
      x-ref="popover"
      x-cloak
      x-show="popoverOpen"
      x-init="setTimeout(function(){ popoverHeightCalculate(); }, 100);"
      x-trap.inert="popoverOpen"
      @click.away="popoverOpen=false;"
      @keydown.escape.window="popoverOpen=false"
      :class="{ 'top-0 mt-12' : popoverPosition == 'bottom', 'bottom-0 mb-12' : popoverPosition == 'top' }"
      class="absolute w-64 max-w-lg -translate-x-1/2 left-1/2">
      <div x-ref="popoverInner" x-show="popoverOpen" class="w-full p-4 bg-white dark:bg-neutral-800 shadow-lg rounded-lg border border-neutral-200/70 dark:border-neutral-700/70">
         <div x-show="popoverArrow && popoverPosition == 'bottom'" class="absolute top-0 hidden sm:inline-block w-5 mt-px overflow-hidden -translate-x-2 -translate-y-2.5 left-1/2"><div class="w-2.5 h-2.5 origin-bottom-left transform rotate-45 bg-white dark:bg-neutral-800 border-t border-l border-neutral-300 dark:border-neutral-700 rounded-sm"></div></div>
         <div x-show="popoverArrow  && popoverPosition == 'top'" class="absolute bottom-0 hidden sm:inline-block w-5 mb-px overflow-hidden -translate-x-2 translate-y-2.5 left-1/2"><div class="w-2.5 h-2.5 origin-top-left transform -rotate-45 bg-white dark:bg-neutral-800 border-b border-l border-neutral-300 dark:border-neutral-700 rounded-sm"></div></div>
         <div>{{ $slot }}</div>
      </div>
   </div>
</div>