<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Renderless;

use App\Models\InvCirc;
use App\Models\User;

new class extends Component {

   #[Reactive]
   public int $stock_id = 0;

   #[Reactive]
   public string $stock_uom = '';

   #[Reactive]
   public int $curr_id = 1;

   #[Reactive]
   public float $curr_rate = 1;

   #[Reactive]
   public float $unit_price = 0;

   #[Reactive]
   public bool $can_eval = false;

   
   public int $qty_relative = 0;

   public float $amount = 0;

   public string $remarks = '';

   public int $user_id = 0;

   public string $userq = '';

   #[Renderless]
   public function updatedUserq()
   {
       $this->dispatch('userq-updated', $this->userq);
   }

   public function mount()
   {
      $this->user_id = Auth::user()->id;

   }

   public function save()
   {
      $this->remarks = trim($this->remarks);

      $this->validate([
         'type'         => ['required', 'in:deposit,capture,withdrawal'],
         'stock_id'     => ['required', 'exists:inv_stocks,id'],
         'qty_relative' => ['required', 'gte:0', 'lte:100000'],
         'remarks'      => ['required', 'string', 'max:256'],
      ]);

      // withdrawal and capture qty_relative cannot be 0
      if ($this->type == 'deposit' || $this->type == 'withdrawal') {
         if (!$this->qty_relative > 0) {
            $this->js('toast("' . __('Qty tidak boleh 0') . '", { type: "danger" } )');
            return;
         }
      }

      $amount = 0;
      $amount = $this->unit_price * $this->qty_relative;
      $unit_price = $this->unit_price;

      // amount should always be main currency (USD)
      if($amount > 0 && $this->curr_id !== 1) {
         $amount /= $this->curr_rate;
         $unit_price /= $this->curr_rate;
      } 
      
      $user = $this->userq ? User::where('emp_id', $this->userq)->first() : null;
      $user_id = (int) ($user ? $user->id : Auth::user()->id);
      $auth_id = (int) Auth::user()->id;
      
      $is_delegated = false;
      if($user_id !== $auth_id && !$this->can_eval) {
         $this->js('toast("' . __('Kamu tidak dapat mendelegasikan sirkulasi di area ini') . '", { type: "danger" } )');
         return;
      } else if ($user_id !== $auth_id) {
         $is_delegated = true;
      } 

      $circ = new InvCirc();
      $circ->amount       = $amount;
      $circ->unit_price   = $unit_price; 

      $circ->type         = $this->type;
      $circ->inv_stock_id = $this->stock_id;
      $circ->qty_relative = $this->qty_relative;
      $circ->remarks      = $this->remarks;

      $circ->user_id      = $user_id;
      $circ->is_delegated = $is_delegated;
      

      $response = Gate::inspect('create', $circ);
 
      if ($response->denied()) {
         $this->js('toast("' . $response->message() . '", { type: "danger" })');
         return;
      }

      $circ->save();
      
      $this->dispatch('close-popover');
      $this->dispatch('circ-created');
      $this->js('toast("' . __('Sirkulasi dibuat') . '", { type: "success" } )');
      $this->reset(['qty_relative', 'amount', 'remarks', 'userq', 'user_id']);
   }
}

?>

<x-popover-button focus="" icon="icon-shopping-cart">
   <div class="text-center text-neutral-500 py-4">
      <i class="icon-construction text-3xl"></i>
      <div class="text-sm">{{ __('Dalam tahap pengembangan') }}</div>
   </div>
   <form  wire:submit="save" class="grid grid-cols-1 gap-y-4">

   </form>
   <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</x-popover-button>