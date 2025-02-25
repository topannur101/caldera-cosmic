<?php

use Livewire\Volt\Component;

new class extends Component
{
   public $stock_id = 0;

}

?>

<div>
   <div wire:key="circs-modal">
      <x-modal name="circ-edit">
         <div class="p-6">
            Editing...
         </div>
      </x-modal>
   </div>
   <table class="w-full [&_td]:py-2 [&_tr_td:first-child]:w-[1%] [&_tr_td:last-child]:w-[1%]">
      <x-inv-circ-tr 
      color="text-green-500" 
      icon="fa-plus" 
      qty_relative="30" 
      uom="EA" 
      user_name="Andi" 
      user_emp_id="TT17110594"
      is_delegated="false" 
      eval_user_name="Edwin" 
      eval_user_emp_id="XX17110594" 
      created_at_friendly="3 bulan yang lalu" 
      remarks="Deksripsi panjang mengapa sirkulasi ini di buat dan dimana dipakainya" 
      eval_icon="fa-hourglass"></x-inv-circ-tr>
   </table>
</div>