<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use App\Models\InvCirc;
use App\Models\InvStock;
use App\Models\InvCurr;
use App\Models\User;

new class extends Component
{
   public array $circ = [
      'id' => 0,
      'color' => '',
      'icon' => '',
      'qty_relative' => 0,
      'inv_stock' => [
         'uom' => '',
         'inv_item_id' => 0
      ],
      'inv_item' => [
         'name' => '',
         'desc' => '',
         'code' => '',
         'photo' => '',
      ],
      'eval_icon' => '',
      'user' => [
         'name' => '',
         'emp_id' => '',
         'photo' => '',
      ],
      'is_delegated' => '',
      'created_at' => '',
      'remarks' => '',
      'amount' => 0,
      'unit_price' => 0,
      'eval_user' => [
         'name' => '',
         'emp_id' => ''
      ],
      'eval_status'  => '',
      'eval_remarks' => '',
      'eval_friendly' => '',
      'type' => '',
      'type_friendly' => '',
   ];

   public string $curr_name = '';

   public bool $can_eval = false;
   public bool $is_evaluating = false;

   public bool $can_edit = false;
   public bool $is_editing = false;

   public int $qty_relative = 0;
   public string $remarks = '';
   public string $eval_remarks = '';

   public string $userq = '';
   public int $user_id = 0;

   #[Renderless]
   public function updatedUserq()
   {
       $this->dispatch('userq-updated', $this->userq);
   }

   public function mount()
   {
      $this->curr_name = InvCurr::find(1)->name;
   }

   #[On('circ-show')]
   public function loadCirc(int $id)
   {
       $circ = InvCirc::with(['user', 'inv_stock', 'inv_item', 'eval_user'])->find($id);
       if ($circ) {
            $this->can_eval = Gate::inspect('eval', $circ)->allowed();
            $this->can_edit = Gate::inspect('edit', $circ)->allowed();

            $this->circ['color'] = $circ->type_color();
            $this->circ['icon']  = $circ->type_icon();
            $this->circ['eval_icon'] = $circ->eval_icon();
            $this->circ['eval_friendly'] = $circ->eval_friendly();
            $this->circ['type_friendly'] = $circ->type_friendly();

            $this->qty_relative  = $circ->qty_relative;
            $this->remarks       = $circ->remarks;
            $this->userq         = $circ->user->emp_id;
            $this->dispatch('userq-updated', $this->userq);

            $circ = $circ->toArray();
            $this->circ = array_merge($this->circ, $circ);

            $this->resetValidation();
       } else {
           $this->handleNotFound();
       }
       $this->reset(['is_evaluating', 'is_editing', 'eval_remarks']);
   }

   public function with(): array
   {
       return [
           'is_superuser' => Gate::allows('superuser'),
       ];
   }

   public function evaluate($eval)
   {
      // caldera: validate first
      $stock = InvStock::find($this->circ['inv_stock_id']);
      
      $result = $stock->updateByCirc($eval, $this->circ, $this->eval_remarks);

      if($result['success']) {
         $this->dispatch('circ-evaluated');
         $this->js('$dispatch("close")');
         $this->js('toast("' . $result['message'] . '", { type: "success" })');

      } else {
         $this->js('toast("' . $result['message'] . '", { type: "danger" })');

      }

   }

   public function update()
   {
      $this->userq         = trim($this->userq);
      $this->remarks       = trim($this->remarks);

      $this->validate([
         'userq'        => ['required', 'exists:users,emp_id'],
         'qty_relative' => ['required', 'gte:0', 'lte:100000'],
         'remarks'      => ['required', 'string', 'max:256'],
      ]);

      $circ = InvCirc::find($this->circ['id']);

      if (!$circ) {
         $this->handleNotFound();
      }

      $user = $this->userq ? User::where('emp_id', $this->userq)->first() : null;
      $user_id = (int) ($user ? $user->id : Auth::user()->id);
      $auth_id = (int) Auth::user()->id;
      
      $is_delegated = false;
      if ($user_id !== $auth_id) {
         $is_delegated = true;
      } 

      // withdrawal and capture qty_relative cannot be 0
      if ($circ->type == 'deposit' || $circ->type == 'withdrawal') {
         if (!$this->qty_relative > 0) {
            $this->js('toast("' . __('Qty tidak boleh 0') . '", { type: "danger" } )');
            return;
         }
      }

      if ($circ->eval_status == 'approved' || $circ->eval_status == 'rejected') {
         $this->js('toast("' . __('Sirkulasi yang telah dievaluasi tidak dapat diedit') . '", { type: "danger" } )');
         return;
      }

      $amount = 0;
      $amount = $circ->unit_price * $this->qty_relative;

      $circ->amount       = $amount; 

      $circ->qty_relative = $this->qty_relative;
      $circ->remarks      = $this->remarks;

      $circ->user_id      = $user_id;
      $circ->is_delegated = $is_delegated;
      

      $response = Gate::inspect('edit', $circ);
 
      if ($response->denied()) {
         $this->js('toast("' . $response->message() . '", { type: "danger" })');
         return;
      }

      $circ->save();
      
      $this->dispatch('circ-show', $circ->id);
      $this->dispatch('circ-updated', $circ->id);
      $this->js('toast("' . __('Sirkulasi diperbarui') . '", { type: "success" } )');
      $this->reset(['is_editing']);
   }

   public function customReset()
   {
       $this->reset(['circ', 'is_evaluating', 'remarks']);
   }

   public function handleNotFound()
   {
       $this->js('$dispatch("close")');
       $this->js('toast("' . __('Tidak ditemukan') . '", { type: "danger" })');
       $this->dispatch('updated');
   }

   public function canRevert(): bool
   {
       // Check if this circulation can be reverted (is approved and is latest for the stock)
       if ($this->circ['eval_status'] !== 'approved') {
           return false;
       }

       // Check if user can evaluate circulations (same permission as revert)
       $circ = InvCirc::find($this->circ['id']);
       if (!$circ || Gate::inspect('eval', $circ)->denied()) {
           return false;
       }

       // Check if this is the latest approved circulation for this stock
       $latestApproved = InvCirc::where('inv_stock_id', $this->circ['inv_stock_id'])
           ->where('eval_status', 'approved')
           ->orderByDesc('updated_at')
           ->first();

       return $latestApproved && $latestApproved->id === $this->circ['id'];
   }

   public function revert()
   {
       if (!$this->canRevert()) {
           $this->js('toast("' . __('Sirkulasi ini tidak dapat diurungkan') . '", { type: "danger" })');
           return;
       }

       $circ = InvCirc::find($this->circ['id']);
       $stock = $circ->inv_stock;
       $item = $stock->inv_item;

       try {
           // Reverse the quantity changes
           $qty_current = $stock->qty;
           $qty_relative = $circ->qty_relative;
           $qty_reverted = null;

           switch ($circ->type) {
               case 'deposit':
                   // Reverse deposit: subtract the amount that was added
                   $qty_reverted = $qty_current - $qty_relative;
                   break;
               
               case 'withdrawal':
                   // Reverse withdrawal: add back the amount that was taken
                   $qty_reverted = $qty_current + $qty_relative;
                   break;
           }

           if ($qty_reverted === null && ($circ->type === 'deposit' || $circ->type === 'withdrawal')) {
               throw new \Exception(__('Terjadi galat ketika menghitung qty pemulihan'));
           }

           if ($qty_reverted < 0) {
               throw new \Exception(__('Pemulihan akan menghasilkan qty negatif'));
           }

           // Calculate new amount_main
           $amount_main = 0;
           if ($qty_reverted > 0 && $stock->unit_price > 0 && $stock->inv_curr->rate > 0) {
               $amount_main = max(
                   ($stock->inv_curr_id === 1) 
                   ? $qty_reverted * $stock->unit_price
                   : $qty_reverted * $stock->unit_price / $stock->inv_curr->rate
                   , 0);
           }

           // Revert circulation to pending
           $circ->update([
               'eval_user_id'  => null,
               'eval_status'   => 'pending', 
               'eval_remarks'  => null
           ]);

           // Update stock quantity only for deposit/withdrawal
           if ($circ->type === 'deposit' || $circ->type === 'withdrawal') {
               $stock->update([
                   'wf'            => $stock->calculateWf(),
                   'qty'           => $qty_reverted,
                   'amount_main'   => $amount_main
               ]);
           }

           $this->dispatch('circ-show', $circ->id);
           $this->dispatch('circ-updated', $circ->id);
           $this->js('toast("' . __('Sirkulasi berhasil diurungkan ke tertunda') . '", { type: "success" })');

       } catch (\Exception $e) {
           $this->js('toast("' . $e->getMessage() . '", { type: "danger" })');
       }
   }
}

?>

<div x-data="{ is_evaluating: @entangle('is_evaluating'), is_editing: @entangle('is_editing') }">

   <div class="p-6 flex justify-between">
      <x-text-button type="button" x-show="is_editing" x-on:click="is_editing = !is_editing">
         <div class="flex gap-x-3 items-center">
            <i class="icon-arrow-left"></i>
            <h2 class="text-lg font-medium">
               {{ __('Edit') }}
            </h2>
         </div>
      </x-text-button>
      <div x-show="!is_editing" class="flex items-center text-xl">
         <span class="{{ $circ['color'] }}">
            <i class="{{ $circ['icon'] }} mr-1"></i>{{ $circ['qty_relative'] . ' ' . $circ['inv_stock']['uom'] }}
         </span>
      </div>
      <div>
         <x-text-button type="button" x-on:click="$dispatch('close')"><i class="icon-x"></i></x-button>
      </div>
   </div>

   <div class="px-6 pb-6 flex gap-x-3 text-sm">
      <div>
         <div class="rounded-sm overflow-hidden relative flex w-10 h-10 bg-neutral-200 dark:bg-neutral-700">
            <div class="m-auto">
               <svg xmlns="http://www.w3.org/2000/svg"  class="block w-6 h-6 fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793"><path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" /></svg>
            </div>
            @if($circ['inv_item']['photo'])
               <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ '/storage/inv-items/' . $circ['inv_item']['photo'] }}" />
            @endif
         </div> 
      </div>
      <div class="grow truncate">
         <div class="truncate">{{ $circ['inv_item']['name'] }}</div>
         <div class="flex gap-x-3 text-neutral-500">
            <div class="grow truncate">{{ $circ['inv_item']['desc'] }}</div>
            <div>{{ $circ['inv_item']['code'] }}</div>
         </div>
      </div>
   </div>   

   <hr class="border-neutral-300 dark:border-neutral-700" />
   <div x-show="!is_editing" class="p-6 flex gap-x-3">
      <div>
         <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
         @if ($circ['user']['photo'])
            <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/' . $circ['user']['photo'] }}" />
         @else
            <svg xmlns="http://www.w3.org/2000/svg"
                  class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                  viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
               <path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
            </svg>
         @endif
         </div>
      </div>
      <div class="grow">
         <div class="text-xs text-neutral-500">
            <span>{{ $circ['user']['name'] . ' - ' . $circ['user']['emp_id'] }}</span>
            @if($circ['is_delegated'])
               <span title="Didelegasikan"><i class="icon-handshake"></i></span>
            @endif
         </div>
         <div class="text-wrap">
            {{ $circ['remarks'] }}
         </div>
      </div>
   </div>
   <div x-show="is_editing" class="p-6 flex flex-col gap-y-4">
      <div 
         x-data="{ open: false, userq: @entangle('userq').live }" 
         x-on:user-selected="userq = $event.detail.user_emp_id; open = false">
         <div x-on:click.away="open = false">
            <label for="circ-user" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pengguna') }}</label>
            <x-text-input-icon x-model="userq" icon="icon-user"
               x-on:change="open = true" x-ref="userq" x-on:focus="open = true"
               id="circ-user" type="text" autocomplete="off"
               placeholder="{{ __('Pengguna') }}" />
            <div class="relative" x-show="open" x-cloak>
               <div class="absolute top-1 left-0 w-full z-10">
                  <livewire:layout.user-select />
               </div>
            </div>
         </div>
      </div>
      <div>
         <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-remarks"><span>{{ __('Keterangan') }}</span></label>
         <x-text-input wire:model="remarks" id="circ-remarks" autocomplete="circ-remarks" />
      </div>
      @if($circ['type'] == 'deposit' || $circ['type'] == 'withdrawal')
         <div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-qty"><span>{{ __('Jumlah') }}</span></label>
            <x-text-input-suffix wire:model="qty_relative" suffix="{{ $circ['inv_stock']['uom'] }}" id="circ-qty" name="circ-qty"
            type="number" value="" min="1" placeholder="Qty" />
         </div>
      @endif
      @if ($errors->any())
         <div>
               <x-input-error :messages="$errors->first()" />
         </div>
      @endif
   </div>
   
   <div x-show="is_editing" class="px-6 pb-6 flex items-center justify-between">
      <div class="{{ $circ['color'] }} text-sm font-bold uppercase">
         <i class="{{ $circ['icon'] }} me-2"></i>{{ $circ['type_friendly'] }}
      </div>
      <x-primary-button type="button" wire:click="update">{{ __('Perbarui') }}</x-secondary-button>
   </div>

   <hr x-show="!is_editing" class="border-neutral-300 dark:border-neutral-700" />
   <div x-show="!is_editing" class="p-6 flex flex-col gap-y-2 text-sm">
      <div class="flex items-center gap-x-4 w-full">
         <div>
            <span class="text-neutral-500 hidden sm:inline">{{ __('Dibuat') . ': ' }}</span>
            <span class="font-medium">{{ $circ['created_at'] }}</span>
         </div>
         <div class="grow flex items-center justify-between">
            <div>
               <span class="text-neutral-500">{{ __('Status') . ': ' }}</span>
               <span class="font-medium">{{ $circ['eval_friendly'] ?? 'Disetujui' }}</span>
            </div>
            <div>
               <i class="{{ $circ['eval_icon'] }}"></i>
            </div>
         </div>
      </div>
   </div>
   
   <!-- amount $circ['amount'] and unit_price info $circ['unit_price'] and currency $circ['inv_curr']['name'] info -->
   <hr x-show="!is_editing" class="border-neutral-300 dark:border-neutral-700" />
   <div x-show="!is_editing" class="p-6 grid grid-cols-2 gap-4">
      <div class="flex flex-col">
         <span class="text-sm text-neutral-500">{{ __('Harga satuan') }}</span>
         <span class="text-base">{{ $circ['unit_price'] }} {{ $curr_name }}</span>
      </div>
      <div class="flex flex-col">
         <span class="text-sm text-neutral-500">{{ __('Amount') }}</span>
         <span class="text-base">{{ $circ['amount'] }} {{ $curr_name }}</span>
      </div>
   </div>

   <!-- evaluation info evaluator and their remarks $circ['eval_user']['name'] and $circ['eval_remarks'] -->
   @if($circ['eval_status'] !== 'pending')
   <hr class="border-neutral-300 dark:border-neutral-700" />
   <div class="p-6 flex flex-col gap-y-2 text-sm">
      <div class="flex items-center justify-between">
         <div>
            <div>
               <span class="text-neutral-500">{{ __('Evaluator') . ': ' }}</span>
               <span class="font-medium" title="{{ $circ['eval_user']['emp_id'] ?? '' }}">{{ $circ['eval_user']['name'] ?? 'Edwin' }}</span>
            </div>
            <div>
               <span>{{  $circ['eval_remarks'] ?? '' }}</span>
            </div>
         </div>
         @if($this->canRevert())
            <div>
               <x-text-button type="button" wire:click="revert" title="{{ __('Urungkan ke tertunda') }}">
                  <i class="icon-undo-2"></i>
               </x-text-button>
            </div>
         @endif
      </div>
   </div>
   @endif

   <div class="p-6 bg-neutral-100 dark:bg-neutral-900 flex flex-col gap-y-4" x-show="is_evaluating">
      <div>
         <x-text-button type="button" x-on:click="is_evaluating = !is_evaluating">
            <div class="flex gap-x-3 items-center">
               <i class="icon-arrow-left"></i>
               <h2 class="text-lg font-medium">
                  {{ __('Evaluasi') }}
               </h2>
            </div>
         </x-text-button>
      </div>
      <div>
         <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="evalRemarks">{{ __('Keterangan evaluasi') }}</label>
         <x-text-input wire:model="eval_remarks" id="eval-remarks" autocomplete="eval-remarks" x-ref="evalRemarks" />
      </div>
      <div class="px-3 text-neutral-500 text-sm text-wrap"><i class="icon-triangle-alert mr-2"></i>{{ __('Sirkulasi yang telah dievaluasi tidak dapat diedit') }}</div>
      <div class="flex justify-end">
         <div class="btn-group">
            <x-secondary-button type="button" wire:click="evaluate('approve')"><i class="icon-thumbs-up mr-2"></i>{{ __('Setujui') }}</x-secondary-button>
            <x-secondary-button type="button" wire:click="evaluate('reject')" class="h-full"><i class="icon-thumbs-down"></i></x-secondary-button>
         </div>
      </div>
   </div>

   @if($circ['eval_status'] === 'pending')
   <!-- Buttons -->
   <div x-show="!is_editing && !is_evaluating" class="px-6 pb-6 flex justify-between">
      <div>
         @if($can_edit)
            <x-secondary-button type="button" x-on:click="is_editing = !is_editing;">{{ __('Edit') }}</x-secondary-button>
         @else
            <x-secondary-button type="button" disabled>{{ __('Edit') }}</x-secondary-button>
         @endif
      </div>
      <div>
         @if($can_eval)
            <x-secondary-button type="button" x-on:click="is_evaluating = !is_evaluating; setTimeout(function(){ $refs.evalRemarks.focus()}, 100)"><i class="icon-gavel mr-2"></i>{{ __('Evaluasi') }}</x-secondary-button>
         @else
            <x-secondary-button type="button" disabled><i class="icon-gavel mr-2"></i>{{ __('Evaluasi') }}</x-secondary-button>
         @endif
      </div>
   </div>
   @endif

   <x-spinner-bg wire:loading.class.remove="hidden" wire:target.except="userq"></x-spinner-bg>
   <x-spinner wire:loading.class.remove="hidden" wire:target.except="userq" class="hidden"></x-spinner>
</div>