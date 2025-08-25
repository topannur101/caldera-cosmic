<div {{ $attributes->merge([ 'class' => 'spinner z-20']) }} data-layer="4">
     <div class="spinner-container">
       <div class="spinner-rotator">
         <div class="spinner-left">
           <div {{ $attributes->merge([ 'class' => 'spinner-circle']) }}></div>
         </div>
         <div class="spinner-right">
           <div {{ $attributes->merge([ 'class' => 'spinner-circle']) }}></div>
         </div>
       </div>
     </div>
 </div>