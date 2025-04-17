<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

use App\Models\InvItem;
use App\Models\InvArea;
use App\Models\User;
use App\Models\InvCurr;
use App\Models\InvTag;
use Illuminate\Support\Collection;
use Carbon\Carbon;

new #[Layout('layouts.app')]
class extends Component
{

   public int $area_id = 0;

   public array $tags = [];

   public array $areas = [];

   public Collection $agingData;

   public array $totals = [
       'gt_100_days' => 0,
       'gt_90_days' => 0,
       'gt_60_days' => 0,
       'gt_30_days' => 0,
       'lt_30_days' => 0,
       'total' => 0,
   ];

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

   }

   #[On('update')]
   public function update()
   {
        $this->agingTable();

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
                     'label' => '',
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
                       'barThickness' => 20
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
                   $totalValue += $stock->amount_main;
               }
           }
   
           // Add tag name and total value to the data
           $data['data']['labels'][] = $tag->name;
           $data['data']['datasets'][0]['data'][] = round($totalValue, 2);
       }
   
       // Calculate total value for items with no tags
       $noTagValue = 0;
       $noTagItems = InvItem::where('inv_area_id', $this->area_id)->whereDoesntHave('inv_tags')->get();
   
       foreach ($noTagItems as $item) {
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
               $noTagValue += $qty * $unitPrice;
           }
       }
   
       // Add no tag value to the data
       $data['data']['labels'][] = __('Tanpa tag');
       $data['data']['datasets'][0]['data'][] = round($noTagValue, 2);
   
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

   public function agingTable()
   {
        $now = Carbon::now();
        $gt_100_days = $now->copy()->subDays(100);
        $gt_90_days = $now->copy()->subDays(90);
        $gt_60_days = $now->copy()->subDays(60);
        $gt_30_days = $now->copy()->subDays(30);

        // Reset totals
        $this->totals = [
            'gt_100_days' => 0,
            'gt_90_days' => 0,
            'gt_60_days' => 0,
            'gt_30_days' => 0,
            'lt_30_days' => 0,
            'total' => 0,
        ];

        // Get all tags that have active items in the selected area
        $tags = InvTag::whereHas('inv_items', function($query) {
            $query->where('inv_area_id', $this->area_id)
                ->where('is_active', true);
        })->get();

        $this->agingData = collect();

        foreach ($tags as $tag) {
            $tagData = [
                'tag_name' => $tag->name, // Assuming the tag has a name field
                'gt_100_days' => 0,
                'gt_90_days' => 0,
                'gt_60_days' => 0,
                'gt_30_days' => 0,
                'lt_30_days' => 0,
                'total' => 0,
            ];

            // Get all active items with this tag and in the selected area
            $items = $tag->inv_items()
                ->where('inv_area_id', $this->area_id)
                ->where('is_active', true)
                ->get();

            foreach ($items as $item) {
                // For each item, get all stocks
                $stocks = $item->inv_stocks;

                foreach ($stocks as $stock) {
                    // Calculate the value in base currency
                    $value = $stock->qty * $stock->unit_price;
                    
                    // Convert currency if needed
                    if ($stock->inv_curr_id != 1) {
                        $value = $value / $stock->inv_curr->rate;
                    }

                    // Determine which aging bucket this item belongs to based on last_withdrawal
                    if ($item->last_withdrawal) {
                        $lastWithdrawal = Carbon::parse($item->last_withdrawal);
                        
                        if ($lastWithdrawal <= $gt_100_days) {
                            $tagData['gt_100_days'] += $value;
                            $this->totals['gt_100_days'] += $value;

                        } elseif ($lastWithdrawal <= $gt_90_days) {
                            $tagData['gt_90_days'] += $value;
                            $this->totals['gt_90_days'] += $value;

                        } elseif ($lastWithdrawal <= $gt_60_days) {
                            $tagData['gt_60_days'] += $value;
                            $this->totals['gt_60_days'] += $value;

                        } elseif ($lastWithdrawal <= $gt_30_days) {
                            $tagData['gt_30_days'] += $value;
                            $this->totals['gt_30_days'] += $value;
                            
                        } else {
                            $tagData['lt_30_days'] += $value;
                            $this->totals['lt_30_days'] += $value;
                        }
                    } else {
                        // If no last_withdrawal date, put in the oldest category
                        $tagData['gt_100_days'] += $value;
                        $this->totals['gt_100_days'] += $value;
                    }

                    // Add to total
                    $tagData['total'] += $value;
                    $this->totals['total'] += $value;
                }
            }

            // Only add tags that have items
            if ($tagData['total'] > 0) {
                $this->agingData->push($tagData);
            }
        }

        // First, create a row for items with no tags
        $noTagData = [
            'tag_name' => '',
            'gt_100_days' => 0,
            'gt_90_days' => 0,
            'gt_60_days' => 0,
            'gt_30_days' => 0,
            'lt_30_days' => 0,
            'total' => 0,
        ];

        // Get items with no tags
        $itemsWithNoTags = InvItem::where('inv_area_id', $this->area_id)
        ->where('is_active', true)
        ->whereDoesntHave('inv_tags')
        ->get();

        foreach ($itemsWithNoTags as $item) {
            // For each item, get all stocks
            $stocks = $item->inv_stocks;

            foreach ($stocks as $stock) {
                // Calculate the value in base currency
                $value = $stock->qty * $stock->unit_price;
                
                // Convert currency if needed
                if ($stock->inv_curr_id != 1) {
                    $value = $value / $stock->inv_curr->rate;
                }

                // Determine which aging bucket this item belongs to based on last_withdrawal
                if ($item->last_withdrawal) {
                    $lastWithdrawal = Carbon::parse($item->last_withdrawal);
                    
                    if ($lastWithdrawal <= $gt_100_days) {
                        $noTagData['gt_100_days'] += $value;
                        $this->totals['gt_100_days'] += $value;
                    } elseif ($lastWithdrawal <= $gt_90_days) {
                        $noTagData['gt_90_days'] += $value;
                        $this->totals['gt_90_days'] += $value;
                    } elseif ($lastWithdrawal <= $gt_60_days) {
                        $noTagData['gt_60_days'] += $value;
                        $this->totals['gt_60_days'] += $value;
                    } elseif ($lastWithdrawal <= $gt_30_days) {
                        $noTagData['gt_30_days'] += $value;
                        $this->totals['gt_30_days'] += $value;
                    } else {
                        $noTagData['lt_30_days'] += $value;
                        $this->totals['lt_30_days'] += $value;
                    }
                } else {
                    // If no last_withdrawal date, put in the oldest category
                    $noTagData['gt_100_days'] += $value;
                    $this->totals['gt_100_days'] += $value;
                }

                // Add to total
                $noTagData['total'] += $value;
                $this->totals['total'] += $value;
            }
        }

        // Only add no-tag row if there are actually items without tags
        if ($noTagData['total'] > 0) {
            $this->agingData->push($noTagData);
        }

   }

};

?>

<x-slot name="title">{{ __('Ringkasan barang') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Ringkasan barang') }}</x-nav-inventory-sub>
</x-slot>

<div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-700 dark:text-neutral-200">
   @vite(['resources/js/apexcharts.js'])
   <div class="flex gap-x-6 items-center mb-6">
      <x-select wire:model.live="area_id">
         <option value="0"></option>
         @foreach ($areas as $area)
            <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
         @endforeach
      </x-select>
      <div wire:loading.class.remove="hidden" class="flex gap-3 hidden">
         <div class="relative w-3">
            <x-spinner class="sm mono"></x-spinner>
         </div>
         <div>
            {{ __('Melakukan kalkulasi...') }}
         </div>
      </div>
   </div>
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
      <div class="col-span-3 grid grid-cols-2 gap-4">
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
            <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Nilai barang berdasarkan tag') . ' (' . InvCurr::find(1)->name . ')'}}</label>
            <div 
                wire:ignore
                id="value-container" 
                class="overflow-hidden "
                wire:key="value-container">
            </div>  
        </div>
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
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
   <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 mt-4">
   <label class="mb-2 uppercase text-xs text-neutral-500">{{ __('Barang yang menua berdasarkan tag') . ' (' . InvCurr::find(1)->name . ')'}}</label>

        @if ($area_id == 0)
            <div class="py-4 text-neutral-500 text-center">
                {{ __('Pilih area untuk melihat data aging') }}
            </div>
        @elseif ($agingData->isEmpty())
            <div class="py-4 text-neutral-500 text-center">
            {{ __('Tidak ada data aging untuk area yang dipilih') }}
            </div>
        @else
            <table class="table table-sm text-sm mt-4">
                <thead>
                    <tr>
                        <th>{{ __('Tag')}}</th>
                        <th>{{ '> 100' . __(' hari')}}</th>
                        <th>{{ '> 90' . __(' hari')}}</th>
                        <th>{{ '> 60' . __(' hari')}}</th>
                        <th>{{ '> 30' . __(' hari')}}</th>
                        <th>{{ '< 30' . __(' hari')}}</th>
                        <th>{{ __('Total tag')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($agingData as $tag)
                        <tr>
                            <td>{{ $tag['tag_name'] ?: __('Tanpa tag') }}</td>
                            <td>
                                <a href="{{ route('inventory.items.index', ['tags' => [$tag['tag_name']], 'filter' => 'gt-100-days' ]) }}" 
                                wire:navigate>{{ number_format($tag['gt_100_days'], 2) }}</a>
                            </td>
                            <td>
                                <a href="{{ route('inventory.items.index', ['tags' => [$tag['tag_name']], 'filter' => 'gt-90-days' ]) }}" 
                                wire:navigate>{{ number_format($tag['gt_90_days'], 2) }}</a>
                            </td>                            
                            <td>
                                <a href="{{ route('inventory.items.index', ['tags' => [$tag['tag_name']], 'filter' => 'gt-60-days' ]) }}" 
                                wire:navigate>{{ number_format($tag['gt_60_days'], 2) }}</a>
                            </td>
                            <td>
                                <a href="{{ route('inventory.items.index', ['tags' => [$tag['tag_name']], 'filter' => 'gt-30-days' ]) }}" 
                                wire:navigate>{{ number_format($tag['gt_30_days'], 2) }}</a>
                            </td>
                            <td>
                                <a href="{{ route('inventory.items.index', ['tags' => [$tag['tag_name']], 'filter' => 'lt-30-days' ]) }}" 
                                wire:navigate>{{ number_format($tag['lt_30_days'], 2) }}</a>
                            </td>
                            <td class="font-weight-bold">
                                <a href="{{ route('inventory.items.index', ['tags' => [$tag['tag_name']] ]) }}" 
                                wire:navigate>{{ number_format($tag['total'], 2) }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-light">
                        <td class="font-weight-bold border-t border-neutral-300 dark:border-neutral-700">{{ __('Total aging')}}</td>
                        <td class="font-weight-bold border-t border-neutral-300 dark:border-neutral-700">{{ number_format($totals['gt_100_days'], 2) }}</td>
                        <td class="font-weight-bold border-t border-neutral-300 dark:border-neutral-700">{{ number_format($totals['gt_90_days'], 2) }}</td>
                        <td class="font-weight-bold border-t border-neutral-300 dark:border-neutral-700">{{ number_format($totals['gt_60_days'], 2) }}</td>
                        <td class="font-weight-bold border-t border-neutral-300 dark:border-neutral-700">{{ number_format($totals['gt_30_days'], 2) }}</td>
                        <td class="font-weight-bold border-t border-neutral-300 dark:border-neutral-700">{{ number_format($totals['lt_30_days'], 2) }}</td>
                        <td class="font-weight-bold border-t border-neutral-300 dark:border-neutral-700">{{ number_format($totals['total'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
</div>

@script
<script>
    $wire.$dispatch('update');
</script>
@endscript