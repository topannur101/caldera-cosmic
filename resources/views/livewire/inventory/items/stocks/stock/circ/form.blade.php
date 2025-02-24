<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Reactive;

new class extends Component {

   #[Reactive]
   public int $stock_id = 0;

   #[Reactive]
   public string $uom = '';

   public string $type = '';

   public array $types = [];

   public function mount()
   {
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

   public function save()
   {
      //
   }
   
}

?>

<x-popover-button focus="{{ 'circ-' . $type . (($type == 'deposit' || $type == 'withdrawal') ? '-qty' : '-remarks') }}" icon="{{ $types[$type]['icon'] . ' ' . $types[$type]['color'] }}">
   <form wire:submit.prevent="save" class="grid grid-cols-1 gap-y-4">
      @if($type == 'deposit' || $type == 'withdrawal')
         <div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-{{ $type }}-qty"><span>{{ __('Jumlah') }}</span></label>
            <x-text-input-suffix :suffix="$uom" id="circ-{{ $type }}-qty" class="text-center" name="circ-{{ $type }}-qty"
            type="number" value="" min="1" placeholder="Qty" />
         </div>
      @endif
      <div>
         <label class="block px-3 mb-2 uppercase text-xs text-neutral-500" for="circ-{{ $type }}-remarks">{{ __('Keterangan') }}</label>
         <x-text-input id="circ-{{ $type }}-remarks" />
      </div>
      <div>
         <label for="circ-{{ $type }}-user"
            class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Pengguna') }}</label>
         <x-text-input-icon id="circ-{{ $type }}-user" icon="fa fa-fw fa-user" type="text" autocomplete="off"
            placeholder="{{ __('Pengguna') }}" />
      </div>
      <div class="text-right">
         <x-secondary-button type="submit">
            <span class="{{ $types[$type]['color'] }}"><i class="fa fa-fw {{ $types[$type]['icon'] }} mr-2"></i>{{ $types[$type]['text'] }}</span>
         </x-secondary-button>
      </div>
   </form>
</x-popover-button>