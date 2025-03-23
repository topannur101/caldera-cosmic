<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

use App\Models\InvItem;
use App\Models\InvArea;
use App\Models\User;
use App\Models\InvCurr;
use App\Models\InvTag;

new #[Layout('layouts.app')]
class extends Component
{

   public int $area_id = 0;

   public array $tags = [];

   public array $areas = [];

   public function mount()
   {
       $user_id = Auth::user()->id;

       if ($user_id === 1) {
           $areas = InvArea::all();
       } else {
           $user = User::find($user_id);
           $areas = $user->inv_areas;
       }

       $this->areas = $areas->toArray();
       $this->area_id = $areas->first()->id;

   }

   #[On('update')]
   public function update()
   {
      $this->js("
      let container = '';
      let canvas = '';

      const incompleteBasics = " . json_encode($this->incompleteBasics()) . ";
      container = \$wire.\$el.querySelector('#incomplete-basics-container');
      container.innerHTML = '';
      canvas = document.createElement('canvas');
      canvas.id = 'incomplete-basics';
      container.appendChild(canvas);
      new Chart(canvas, incompleteBasics);

      const status = " . json_encode($this->status()) . ";
      container = \$wire.\$el.querySelector('#status-container');
      container.innerHTML = '';
      canvas = document.createElement('canvas');
      canvas.id = 'status';
      container.appendChild(canvas);
      new Chart(canvas, status);

      const value = " . json_encode($this->value()) . ";
      container = \$wire.\$el.querySelector('#value-container');
      container.innerHTML = '';
      canvas = document.createElement('canvas');
      canvas.id = 'value';
      container.appendChild(canvas);
      new Chart(canvas, value);

      const aging = " . json_encode($this->aging()) . ";
      container = \$wire.\$el.querySelector('#aging-container');
      container.innerHTML = '';
      canvas = document.createElement('canvas');
      canvas.id = 'aging';
      container.appendChild(canvas);
      new Chart(canvas, aging);
      ");

   }

   public function updated()
   {
       $this->update();
   }

   public function incompleteBasics()
   {
      $noPhotoCount = InvItem::where(function($query) {
         $query->whereNull('photo')->orWhere('photo', '');
     })->where('inv_area_id', $this->area_id)->count();
     
     $noCodeCount = InvItem::where(function($query) {
         $query->whereNull('code')->orWhere('code', '');
     })->where('inv_area_id', $this->area_id)->count();

      $noLocCount    = InvItem::whereNull('inv_loc_id')
         ->where('inv_area_id', $this->area_id)
         ->count();

      $noTagCount    = InvItem::doesntHave('inv_tags')
         ->where('inv_area_id', $this->area_id)
         ->count();

      $totalCount = $noPhotoCount + $noCodeCount + $noLocCount + $noTagCount;

      $data = [
         'type' => 'bar',
         'data' => [
             'labels' => [''],
             'datasets' => [
                 [
                     'label' => __('Tanpa foto'),
                     'data' => [$noPhotoCount],
                     'backgroundColor' => '#FF6384',
                 ],
                 [
                     'label' => __('Tanpa kode'),
                     'data' => [$noCodeCount],
                     'backgroundColor' => '#36A2EB',
                 ],
                 [
                     'label' => __('Tanpa lokasi'),
                     'data' => [$noLocCount],
                     'backgroundColor' => '#FFCE56',
                 ],
                 [
                     'label' => __('Tanpa tag'),
                     'data' => [$noTagCount],
                     'backgroundColor' => '#4BC0C0',
                 ],
             ],
         ],
         'options' => [
             'responsive' => true,
             'maintainAspectRatio' => false,
             'indexAxis' => 'y', // This makes the chart horizontal
             'scales' => [
                 'x' => [
                     'stacked' => true,
                     'ticks' => [
                         'beginAtZero' => true,
                         'max' => $totalCount,
                     ],
                 ],
                 'y' => [
                     'stacked' => true,
                 ],
             ],
             'plugins' => [
                 'legend' => [
                     'display' => true, // This shows the legend
                 ],
                 'datalabels' => [
                     'anchor' => 'center',
                     'align' => 'center',
                     'color' => 'white',
                     'font' => [
                         'weight' => 'bold',
                     ],
                     'formatter' => function($value) {
                         return $value;
                     },
                 ],
             ],
         ],
     ];
     
     return $data;
   }

   public function status()
   {
      $activeCount = InvItem::where('is_active', true)
      ->where('inv_area_id', $this->area_id)
      ->count();

      $inactiveCount = InvItem::where('is_active', false)
      ->where('inv_area_id', $this->area_id)
      ->count();

      $data = [
         'type' => 'doughnut',
         'data' => [
               'labels' => [__('Aktif'), __('Nonaktif')],
               'datasets' => [
                  [
                     'data' => [$activeCount, $inactiveCount],
                     'backgroundColor' => ['#36A2EB', '#FF6384'],
                  ],
               ],
         ],
         'options' => [
               'responsive' => true,
               'maintainAspectRatio' => false,
               'plugins' => [
                  'legend' => [
                     'display' => true,
                     'position' => 'right',
                  ],
                  'datalabels' => [
                     'anchor' => 'center',
                     'align' => 'center',
                     'color' => 'white',
                     'font' => [
                           'weight' => 'bold',
                     ],
                     'formatter' => function($value) {
                           return $value;
                     },
                  ],
               ],
         ],
      ];

      return $data;
   }

   public function value()
   {
      // Get the main currency rate (assuming the first entry is the main currency)
      $mainCurrency = InvCurr::first();
      $mainCurrencyId = $mainCurrency->id;

      // Get all tags related to the selected area
      $tags = InvTag::whereHas('inv_items', function($query) {
         $query->where('inv_area_id', $this->area_id);
      })->get();

      $data = [
         'type' => 'bar',
         'data' => [
               'labels' => [],
               'datasets' => [
                  [
                     'label' => 'Total Value',
                     'data' => [],
                     'backgroundColor' => [],
                  ],
               ],
         ],
         'options' => [
               'responsive' => true,
               'maintainAspectRatio' => false,
               'indexAxis' => 'y', // This makes the chart horizontal
               'scales' => [
                  'x' => [
                     'beginAtZero' => true,
                  ],
                  'y' => [
                     'beginAtZero' => true,
                  ],
               ],
               'plugins' => [
                  'legend' => [
                     'display' => false, // This shows the legend
                  ],
                  'datalabels' => [
                     'anchor' => 'center',
                     'align' => 'center',
                     'color' => 'white',
                     'font' => [
                           'weight' => 'bold',
                     ],
                     'formatter' => function($value) {
                           return $value;
                     },
                  ],
               ],
         ],
      ];

      foreach ($tags as $tag) {
         $totalValue = 0;

         // Get all items with this tag and in the selected area
         $items = $tag->inv_items()->where('inv_area_id', $this->area_id)->get();

         foreach ($items as $item) {
               // Get all stocks for this item
               $stocks = $item->inv_stocks;

               foreach ($stocks as $stock) {
                  $unitPrice = $stock->unit_price;
                  $qty = $stock->qty;
                  $currencyRate = $stock->inv_curr->rate;

                  // Convert unit price to main currency if necessary
                  if ($stock->inv_curr_id != $mainCurrencyId) {
                     $unitPrice /= $currencyRate;
                  }

                  // Calculate total value
                  $totalValue += $qty * $unitPrice;
               }
         }

         // Add tag name and total value to the data
         $data['data']['labels'][] = $tag->name;
         $data['data']['datasets'][0]['data'][] = $totalValue;
         $data['data']['datasets'][0]['backgroundColor'][] = '#' . substr(md5(rand()), 0, 6); // Random color
      }

      return $data;
   }

   public function aging()
   {
      $now = now();

      $oneWeekAgo       = $now->copy()->subDays(7);
      $twoWeeksAgo      = $now->copy()->subDays(14);
      $threeWeeksAgo    = $now->copy()->subDays(21);
      $oneMonthAgo      = $now->copy()->subDays(28);
      $twoMonthsAgo     = $now->copy()->subDays(58);
      $threeMonthsAgo   = $now->copy()->subDays(88);

      $lessthanOneWeekCount = InvItem::where('last_withdrawal', '>', $oneWeekAgo)
      ->where('inv_area_id', $this->area_id)
      ->count();
  
      $oneWeekCount     = InvItem::whereBetween('last_withdrawal', [$oneWeekAgo, $twoWeeksAgo])
      ->where('inv_area_id', $this->area_id)
      ->count();

      $twoWeeksCount    = InvItem::whereBetween('last_withdrawal', [$threeWeeksAgo, $twoWeeksAgo])
      ->where('inv_area_id', $this->area_id)
      ->count();

      $threeWeeksCount  = InvItem::whereBetween('last_withdrawal', [$oneMonthAgo, $threeWeeksAgo])
      ->where('inv_area_id', $this->area_id)
      ->count();

      $oneMonthCount    = InvItem::whereBetween('last_withdrawal', [$twoMonthsAgo, $oneMonthAgo])
      ->where('inv_area_id', $this->area_id)
      ->count();

      $twoMonthsCount   = InvItem::whereBetween('last_withdrawal', [$threeMonthsAgo, $twoMonthsAgo])
      ->where('inv_area_id', $this->area_id)
      ->count();

      $threeMonthsCount = InvItem::where('last_withdrawal', '<', $threeMonthsAgo)
      ->where('inv_area_id', $this->area_id)
      ->count();

      $neverCount = InvItem::whereNull('last_withdrawal')->where('inv_area_id', $this->area_id)
      ->count();
  
      $data = [
         'type' => 'bar',
         'data' => [
         'labels' => [ '> ' . __('1 minggu'), __('1 minggu'), __('2 minggu'), __('3 minggu'), __('1 bulan'), __('2 bulan'), __('3 bulan'), __('Tak pernah diambil')],
              'datasets' => [
                  [
                      'label' => 'Aging',
                      'data' => [$lessthanOneWeekCount, $oneWeekCount, $twoWeeksCount, $threeWeeksCount, $oneMonthCount, $twoMonthsCount, $threeMonthsCount, $neverCount],
                      'backgroundColor' => ['#666666', '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF'],
                  ],
              ],
          ],
          'options' => [
              'responsive' => true,
              'maintainAspectRatio' => false,
              'indexAxis' => 'y', // This makes the chart horizontal
              'scales' => [
                  'x' => [
                      'beginAtZero' => true,
                  ],
                  'y' => [
                      'beginAtZero' => true,
                  ],
              ],
              'plugins' => [
                  'legend' => [
                      'display' => false, // This shows the legend
                  ],
                  'datalabels' => [
                      'anchor' => 'center',
                      'align' => 'center',
                      'color' => 'white',
                      'font' => [
                          'weight' => 'bold',
                      ],
                      'formatter' => function($value) {
                          return $value;
                      },
                  ],
              ],
          ],
      ];
  
      return $data;
   }

};

?>

<x-slot name="title">{{ __('Ringkasan barang') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Ringkasan barang') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-700 dark:text-neutral-200">
   @vite(['resources/js/apexcharts.js'])
   <x-select wire:model.live="area_id" class="mb-6">
      @foreach ($areas as $area)
         <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
      @endforeach
   </x-select>
   <div class="grid grid-cols-3 gap-4">
      <div class="col-span-2 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
         <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Ketidaklengkapan info dasar') }}</label>
         <div 
            wire:ignore
            id="incomplete-basics-container" 
            class="h-32 overflow-hidden"
            wire:key="incomplete-basics-container">
         </div>  
      </div>
      <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
         <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Status barang') }}</label>
         <div 
            wire:ignore
            id="status-container" 
            class="h-32 overflow-hidden"
            wire:key="status-container">
         </div>  
      </div>
      <div class="col-span-3 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
         <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Nilai barang berdasarkan tag') . ' (' . InvCurr::find(1)->name . ')'}}</label>
         <div 
            wire:ignore
            id="value-container" 
            class="overflow-hidden"
            wire:key="value-container">
         </div>  
      </div>
      <div class="col-span-3 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
         <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Barang yang menua') }}</label>
         <div 
            wire:ignore
            id="aging-container" 
            class="h-64 overflow-hidden"
            wire:key="aging-container">
         </div>  
      </div>
   </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript