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

   public array $types = [];
   
   public string $type = '';
   
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

      $this->types = [
         'deposit' => [
            'icon'   => 'fa-plus',
            'color'  => 'text-green-500',
            'text'   => __('Tambah')
         ],
         'capture' => [
            'icon'   => 'fa-code-commit',
            'color'  => 'text-yellow-600',
            'text'   => __('Catat')
         ],
         'withdrawal' => [
            'icon'   => 'fa-minus',
            'color'  => 'text-red-500',
            'text'   => __('Ambil')
         ]
      ];
   }

   // #[On('stock-switched')]
   // public function switchStock(array $stock)
   // {
   //    $this->stock_id   = $stock['id'];
   //    $this->uom        = $stock['uom'];
   // }

   public function save()
   {
      $this->remarks = trim($this->remarks);

      $this->validate([
         'type'         => ['required', 'in:deposit,withdrawal,capture'],
         'stock_id'     => ['required', 'exists:inv_stocks,id'],
         'qty_relative' => ['required', 'min:0', 'max:100000'],
         'remarks'      => ['required', 'string', 'max:256'],
         'user_id'      => ['required', 'exists:users,id'],

         // 'amount'       => ['required', 'min:0', 'max:1000000000'],
         // 'unit_price'   => ['required', 'min:0', 'max:1000000000'],
         // 'eval_status'  => ['required', 'in:pending,approved,rejected'],
         // 'eval_user_id' => ['nullable', 'exists:users,id'],
         // 'eval_remarks' => ['nullable', 'string', 'max:256'],
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

      // amount should always be main currency (USD)
      if($amount > 0 && $this->curr_id !== 1) {
         $amount = $amount / $this->curr_rate;
      }
      
      $user = $this->userq ? User::where('emp_id', $this->userq)->first() : null;
      $user_id = $user ? $user->id : Auth::user()->id;
      
      $is_delegated = false;
      if($user_id !== Auth::user()->id && !$this->can_eval) {
         $this->js('toast("' . __('Kamu tidak dapat mendelegasikan sirkulasi di area ini') . '", { type: "danger" } )');
         return;
      } else {
         $is_delegated = true;
      }
   

      $circ = InvCirc::create([
         'amount'       => $amount,
         'unit_price'   => $this->unit_price, 

         'type'         => $this->type,
         'stock_id'     => $this->stock_id,
         'qty_relative' => $this->qty_relative,
         'remarks'      => $this->remarks,

         'user_id'      => $user_id,
         'is_delegated' => $is_delegated,
      ]);
      
      $this->dispatch('close-popover');
   }
}

?>

<x-popover-button focus="{{ 'circ-' . $type . (($type == 'deposit' || $type == 'withdrawal') ? '-qty' : '-remarks') }}" icon="{{ $types[$type]['icon'] . ' ' . $types[$type]['color'] }}">
   <form  wire:submit="save" class="grid grid-cols-1 gap-y-4">
      @if($type == 'deposit' || $type == 'withdrawal')
         <div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-{{ $type }}-qty"><span>{{ __('Jumlah') }}</span></label>
            <x-text-input-suffix wire:model="qty_relative" suffix="{{ $stock_uom }}" id="circ-{{ $type }}-qty" class="text-center" name="circ-{{ $type }}-qty"
            type="number" value="" min="1" placeholder="Qty" />
         </div>
      @endif
      <div>
         <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-{{ $type }}-remarks">{{ __('Keterangan') }}</label>
         <x-text-input wire:model="remarks" id="circ-{{ $type }}-remarks" />
      </div>
      @if($can_eval)
      <div
         x-data="{ 'open': false, 'userq': @entangle('userq').live }" 
         x-on:click.away="open = false"
         x-on:user-selected="userq = $event.detail.user_emp_id; open = false">
         <label for="circ-{{ $type }}-user"
            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pengguna') }}</label>
         <x-text-input-icon
            x-ref="userq" 
            x-model="userq"             
            x-on:focus="open = true"
            x-on:change="open = true"
            icon="fa fa-fw fa-user"
            id="circ-{{ $type }}-user" 
            type="text"
            autocomplete="off"
            placeholder="{{ __('Pengguna') }}" />
            <div class="relative" x-show="open" x-cloak>
               <div class="absolute top-1 left-0 w-full z-10">
                  <livewire:layout.user-select />
               </div>
            </div>
      </div>
      @endif
      @if ($errors->any())
         <div>
               <x-input-error :messages="$errors->first()" />
         </div>
      @endif
      <div class="text-right">
         <x-secondary-button type="submit">
            <span class="{{ $types[$type]['color'] }}"><i class="fa fa-fw {{ $types[$type]['icon'] }} mr-2"></i>{{ $types[$type]['text'] }}</span>
         </x-secondary-button>
      </div>
   </form>
   <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</x-popover-button>