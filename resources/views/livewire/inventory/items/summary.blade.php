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

   public float $progress = 0;

   public float $aging_tag_highest = 0;

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
        $this->js('updateTable(' . json_encode($this->agingData) . ')');

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

   public function agingTable()
   {
        $now = Carbon::now();
        $sub_100_days = $now->copy()->subDays(100);
        $sub_90_days = $now->copy()->subDays(90);
        $sub_60_days = $now->copy()->subDays(60);
        $sub_30_days = $now->copy()->subDays(30);

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
        })->orderBy('name')->get();

        $this->agingData = collect();

        foreach ($tags as $key => $tag) {
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
                    $value = $stock->amount_main;

                    // Determine which aging bucket this item belongs to based on last_withdrawal
                    if ($item->last_withdrawal) {
                        $lastWithdrawal = Carbon::parse($item->last_withdrawal);
                        
                        if ($lastWithdrawal <= $sub_100_days) {
                            $tagData['gt_100_days'] += $value;
                            $this->totals['gt_100_days'] += $value;

                        } elseif ($lastWithdrawal <= $sub_90_days) {
                            $tagData['gt_90_days'] += $value;
                            $this->totals['gt_90_days'] += $value;

                        } elseif ($lastWithdrawal <= $sub_60_days) {
                            $tagData['gt_60_days'] += $value;
                            $this->totals['gt_60_days'] += $value;

                        } elseif ($lastWithdrawal <= $sub_30_days) {
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

            $this->progress += (($key + 1) / $tags->count() / 2.80);
            $this->stream(
               to: 'progress',
               content: min(floor($this->progress / 10), 100),
               replace: true
            );      
        } 

        if (!$tags->count()) {
            $this->progress += 2.80;
            $this->stream(
                to: 'progress',
                content: min(floor($this->progress / 10), 100),
                replace: true
            ); 
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

        foreach ($itemsWithNoTags as $key => $item) {
            // For each item, get all stocks
            $stocks = $item->inv_stocks;

            foreach ($stocks as $stock) {
                // Calculate the value in base currency
                $value = $stock->amount_main;

                // Determine which aging bucket this item belongs to based on last_withdrawal
                if ($item->last_withdrawal) {
                    $lastWithdrawal = Carbon::parse($item->last_withdrawal);
                    
                    if ($lastWithdrawal <= $sub_100_days) {
                        $noTagData['gt_100_days'] += $value;
                        $this->totals['gt_100_days'] += $value;
                    } elseif ($lastWithdrawal <= $sub_90_days) {
                        $noTagData['gt_90_days'] += $value;
                        $this->totals['gt_90_days'] += $value;
                    } elseif ($lastWithdrawal <= $sub_60_days) {
                        $noTagData['gt_60_days'] += $value;
                        $this->totals['gt_60_days'] += $value;
                    } elseif ($lastWithdrawal <= $sub_30_days) {
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

            $this->progress += ($key + 1) / $itemsWithNoTags->count() / 2.80;
            $this->stream(
                to: 'progress',
                content: min(floor($this->progress / 10), 100),
                replace: true
             );   
             
        }  

        if (!$itemsWithNoTags->count()) {
            $this->progress += 2.80;
            $this->stream(
                to: 'progress',
                content: min(floor($this->progress / 10), 100),
                replace: true
            ); 
        }

        // Only add no-tag row if there are actually items without tags
        if ($noTagData['total'] > 0) {
            $this->agingData->push($noTagData);
        }

        $this->aging_tag_highest = $this->agingData->max('total');

   }

   public function incompleteBasics()
   {
        $noPhotoCount = InvItem::where(function($query) {
            $query->whereNull('photo')->orWhere('photo', '');
        })->where('inv_area_id', $this->area_id)->count();

        $this->progress += 2.80;
        $this->stream(
            to: 'progress',
            content: min(floor($this->progress / 10), 100),
            replace: true
        ); 
        
        $noCodeCount = InvItem::where(function($query) {
            $query->whereNull('code')->orWhere('code', '');
        })->where('inv_area_id', $this->area_id)->count();

        $this->progress += 2.80;
        $this->stream(
            to: 'progress',
            content: min(floor($this->progress / 10), 100),
            replace: true
        ); 

        $noLocCount    = InvItem::whereNull('inv_loc_id')
            ->where('inv_area_id', $this->area_id)
            ->count();

        $this->progress += 2.80;
        $this->stream(
            to: 'progress',
            content: min(floor($this->progress / 10), 100),
            replace: true
        ); 

        $noTagCount    = InvItem::doesntHave('inv_tags')
            ->where('inv_area_id', $this->area_id)
            ->count();

        $this->progress += 2.80;
        $this->stream(
            to: 'progress',
            content: min(floor($this->progress / 10), 100),
            replace: true
        ); 

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

      $this->progress += 2.80;
      $this->stream(
          to: 'progress',
          content: min(floor($this->progress / 10), 100),
          replace: true
       ); 

      $inactiveCount = InvItem::where('is_active', false)
      ->where('inv_area_id', $this->area_id)
      ->count();

      $this->progress += 2.80;
      $this->stream(
          to: 'progress',
          content: min(floor($this->progress / 10), 100),
          replace: true
       ); 

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

   public function aging()
   {
      $now = now();

      $sub_100_days = $now->copy()->subDays(100);
      $sub_90_days  = $now->copy()->subDays(90);
      $sub_60_days  = $now->copy()->subDays(60);
      $sub_30_days  = $now->copy()->subDays(30);

      $lt30DaysCount = InvItem::where('last_withdrawal', '>', $sub_30_days)
        ->where('inv_area_id', $this->area_id)
        ->count();

        $this->progress += 2.80;
        $this->stream(
            to: 'progress',
            content: min(floor($this->progress / 10), 100),
            replace: true
        ); 
  
      $gt30DaysCount = InvItem::whereBetween('last_withdrawal', [$sub_60_days, $sub_30_days])
        ->where('inv_area_id', $this->area_id)
        ->count();

      $this->progress += 2.80;
      $this->stream(
          to: 'progress',
          content: min(floor($this->progress / 10), 100),
          replace: true
      );

      $gt60DaysCount= InvItem::whereBetween('last_withdrawal', [$sub_90_days, $sub_60_days])
        ->where('inv_area_id', $this->area_id)
        ->count();

      $this->progress += 2.80;
      $this->stream(
          to: 'progress',
          content: min(floor($this->progress / 10), 100),
          replace: true
      );

      $gt90DaysCount = InvItem::whereBetween('last_withdrawal', [$sub_100_days, $sub_90_days])
        ->where('inv_area_id', $this->area_id)
        ->count();

      $this->progress += 2.80;
      $this->stream(
          to: 'progress',
          content: min(floor($this->progress / 10), 100),
          replace: true
      );

      $gt100DaysCount = InvItem::where(function ($q) use ($sub_100_days) {
            $q->where('last_withdrawal', '<', $sub_100_days)
            ->orWhereNull('last_withdrawal');
        })
        ->where('inv_area_id', $this->area_id)
        ->count();

      $this->progress += 2.80;
      $this->stream(
          to: 'progress',
          content: min(floor($this->progress / 10), 100),
          replace: true
      );
  
      $data = [
         'type' => 'bar',
         'data' => [
         'labels' => [ '> ' . __('100 hari'), '> ' . __('90 hari'), '> ' . __('60 hari'), '> ' . __('30 hari'), '< ' . __('30 hari')],
              'datasets' => [
                  [
                      'label' => 'Aging',
                      'data' => [$gt100DaysCount, $gt90DaysCount, $gt60DaysCount, $gt30DaysCount, $lt30DaysCount],
                      'backgroundColor' => ['#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF'],
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

   public function redirectToItems($tag, $agingBucket)
   {
        session()->forget('inv_search_params');

        $inv_search_params = [
            'tags'          => [$tag],
            'area_ids'      => [$this->area_id],
            'view'          => 'list',
            'sort'          => 'amt-high'
        ];

        if ($agingBucket) {
            $inv_search_params['filter'] = $agingBucket;
        }
        
        session(['inv_search_params' => $inv_search_params]);

        $this->redirect(route('inventory.items.index'), navigate: true);
   }

};

?>

<x-slot name="title">{{ __('Ringkasan barang') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
    <x-nav-inventory-sub>{{ __('Ringkasan barang') }}</x-nav-inventory-sub>
</x-slot>

<div 
    x-data="{ ...app(), areas: @entangle('areas'), area_id:@entangle('area_id'), progress: @entangle('progress'), aging_tag_highest: @entangle('aging_tag_highest') }" x-init="observeProgress()" 
    class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-700 dark:text-neutral-200">
    @vite(['resources/js/apexcharts.js'])
    <div class="flex gap-x-6 items-center mb-6">
        <x-select x-model="area_id" x-on:change="progress = 0; $wire.$dispatch('update');">
            <option value="0"></option>
            <template x-for="area in areas">
                <option :value="area.id" x-text="area.name"></option>
            </template>
        </x-select>
        <div wire:loading.class.remove="hidden" class="hidden">
            <div class="w-72">
                <div class="flex justify-between text-sm">
                    <div>
                        {{ __('Melakukan kalkulasi...') }}
                    </div>
                    <div><span wire:stream="progress">{{ round($progress / 10, 0)  }}</span>%</div>
                </div>
                <div class="cal-shimmer mt-1 relative w-full bg-neutral-200 rounded-full h-1.5 dark:bg-neutral-700">
                    <div 
                        class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500 transition-all duration-200"
                        :style="'width:' + progress + '%'" 
                        style="width:0%;">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div :class="area_id ? 'hidden' : ''" class="py-12">
        <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
            <i class="fa fa-tent relative"><i class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
        </div>
        <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Pilih area') }}</div>
    </div>
    <div x-cloak :class="area_id ? '' : 'hidden'">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-3 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
                <label class="mb-6 block uppercase text-xs text-neutral-500">{{ __('Ketidaklengkapan info dasar') }}</label>
                <div 
                    wire:ignore
                    id="incomplete-basics-container" 
                    class="h-56 overflow-hidden"
                    wire:key="incomplete-basics-container">
                </div>  
            </div>
            <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
                <label class="mb-6 block uppercase text-xs text-neutral-500">{{ __('Status barang') }}</label>
                <div 
                    wire:ignore
                    id="status-container" 
                    class="h-56 overflow-hidden"
                    wire:key="status-container">
                </div>  
            </div>
            <div class="col-span-2 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4">
                <label class="mb-6 block uppercase text-xs text-neutral-500">{{ __('Jumlah barang yang menua') }}</label>
                <div 
                    wire:ignore
                    id="aging-container" 
                    class="h-56 overflow-hidden"
                    wire:key="aging-container">
                </div>  
            </div>
        </div>
        <div class="mt-4">
            <div class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 min-h-56">                
                <label class="mb-6 block uppercase text-xs text-neutral-500">{{ __('Barang yang menua berdasarkan tag') . ' (' . InvCurr::find(1)->name . ')'}}</label>
                <div id="aging-data-table"></div>             
            </div>
        </div>
    </div>
    <script>
      function app() {
         return {
            observeProgress() {               
               const streamElement = document.querySelector('[wire\\:stream="progress"]');
               
               if (streamElement) {
                  const observer = new MutationObserver((mutations) => {
                        mutations.forEach(mutation => {
                           if (mutation.type === 'characterData' || mutation.type === 'childList') {
                              const currentValue = streamElement.textContent;
                              console.log('Stream value updated:', currentValue);
                              
                              // Do something with the captured value
                              this.handleProgress(currentValue);
                           }
                        });
                  });
                  
                  observer.observe(streamElement, { 
                     characterData: true, 
                     childList: true,
                     subtree: true 
                  });
               }

            },

            handleProgress(value) {
               this.progress = value;
            },

            agingTable: null,
            updateTable(tableData) {
                const that = this;
                // Create Tabulator
                this.agingTable = new Tabulator("#aging-data-table", {
                    data: tableData,
                    layout: "fitColumns",
                    responsiveLayout: "collapse",
                    columns: [
                        {
                            title: "{{ __('Nama tag') }}", 
                            field: "tag_name", 
                            sorter: "string",
                            formatter: function(cell) {
                                let value = cell.getValue();
                                let tag = value ? value : "";
                                let areaParam = that.area_id ? "&area_ids[0]=" + that.area_id : "";

                                let url = tag ? 
                                    "{{ url('/inventory/items') }}?tags[0]=" + encodeURIComponent(tag) + "&ignore_params=true&sort=amt_high&view=list" : 
                                    "{{ url('/inventory/items') }}?filter=no-tags" + areaParam + "&ignore_params=true&sort=amt_high&view=list";
                                return "<a href='" + url + "' wire:navigate>" + value + "</a>";
                            },
                            formatterParams: {
                                allowHTML: true
                            }
                        },
                        {
                            title: "{{ __('Total') }}", 
                            field: "total", 
                            sorter: "number", 
                            formatter: function(cell) {
                                let value = cell.getValue();
                                let tag = cell.getRow().getData().tag_name;
                                let areaParam = that.area_id ? "&area_ids[0]=" + that.area_id : "";
                                
                                let url = tag ? 
                                    "{{ url('/inventory/items') }}?tags[0]=" + encodeURIComponent(tag) + areaParam + "&ignore_params=true&sort=amt_high&view=list" : 
                                    "{{ url('/inventory/items') }}?filter=no-tags" + areaParam + "&ignore_params=true&sort=amt_high&view=list";
                                return "<a href='" + url + "' wire:navigate>" + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + "</a>";
                            },
                            formatterParams: {
                                allowHTML: true
                            },
                            bottomCalc: "sum", 
                            bottomCalcFormatter: "money", 
                            bottomCalcFormatterParams: {precision: 2}
                        },
                        {
                            title: "{{ __('Proportion') }}",
                            field: "proportion",
                            sorter: "number",
                            hozAlign: "left",
                            formatter: "progress",
                            width: 200,
                            formatterParams: {
                                min: 0,
                                max: 100,
                            },
                            mutator: function(value, data, type, params, component) {
                                // The table data might not be fully loaded during the first mutations
                                // So we recalculate the max value each time to be safe
                                let tableData = component.getTable().getData();
                                let maxTotal = that.aging_tag_highest; 
                                
                                // Calculate percentage (0-100)
                                let percentage = maxTotal > 0 ? (parseFloat(data.total) / maxTotal) * 100 : 0;
                                
                                // Round to 2 decimal places for cleaner display
                                return Math.round(percentage * 100) / 100;
                            }
                        },
                        {
                            title: "{{ '> '. __('100 hari') }}", 
                            field: "gt_100_days", 
                            sorter: "number", 
                            formatter: function(cell) {
                                let value = cell.getValue();
                                let tag = cell.getRow().getData().tag_name;
                                let areaParam = that.area_id ? "&area_ids[0]=" + that.area_id : "";

                                let url = tag ? 
                                    "{{ url('/inventory/items') }}?tags[0]=" + encodeURIComponent(tag) + "&aging=gt-100-days" + areaParam + "&ignore_params=true&sort=amt_high&view=list" : 
                                    "{{ url('/inventory/items') }}?aging=gt-100-days&filter=no-tags" + areaParam + "&ignore_params=true&sort=amt_high&view=list";
                                return "<a href='" + url + "' wire:navigate>" + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + "</a>";
                            },
                            formatterParams: {
                                allowHTML: true
                            },
                            headerSortStartingDir: "desc", 
                            bottomCalc: "sum", 
                            bottomCalcFormatter: "money", 
                            bottomCalcFormatterParams: {precision: 2}
                        },
                        {
                            title: "{{ '> '. __('90 hari') }}", 
                            field: "gt_90_days", 
                            sorter: "number", 
                            formatter: function(cell) {
                                let value = cell.getValue();
                                let tag = cell.getRow().getData().tag_name;
                                let areaParam = that.area_id ? "&area_ids[0]=" + that.area_id : "";

                                let url = tag ? 
                                    "{{ url('/inventory/items') }}?tags[0]=" + encodeURIComponent(tag) + "&aging=gt-90-days" + areaParam + "&ignore_params=true&sort=amt_high&view=list" : 
                                    "{{ url('/inventory/items') }}?aging=gt-90-days&filter=no-tags" + areaParam + "&ignore_params=true&sort=amt_high&view=list";
                                return "<a href='" + url + "' wire:navigate>" + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + "</a>";
                            },
                            formatterParams: {
                                allowHTML: true
                            },
                            bottomCalc: "sum", 
                            bottomCalcFormatter: "money", 
                            bottomCalcFormatterParams: {precision: 2}
                        },
                        {
                            title: "{{ '> '. __('60 hari') }}", 
                            field: "gt_60_days", 
                            sorter: "number", 
                            formatter: function(cell) {
                                let value = cell.getValue();
                                let tag = cell.getRow().getData().tag_name;
                                let areaParam = that.area_id ? "&area_ids[0]=" + that.area_id : "";

                                let url = tag ? 
                                    "{{ url('/inventory/items') }}?tags[0]=" + encodeURIComponent(tag) + "&aging=gt-60-days" + areaParam + "&ignore_params=true&sort=amt_high&view=list" : 
                                    "{{ url('/inventory/items') }}?aging=gt-60-days&filter=no-tags" + areaParam + "&ignore_params=true&sort=amt_high&view=list";
                                return "<a href='" + url + "' wire:navigate>" + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + "</a>";
                            },
                            formatterParams: {
                                allowHTML: true
                            },
                            bottomCalc: "sum", 
                            bottomCalcFormatter: "money", 
                            bottomCalcFormatterParams: {precision: 2}
                        },
                        {
                            title: "{{ '> '. __('30 hari') }}", 
                            field: "gt_30_days", 
                            sorter: "number", 
                            formatter: function(cell) {
                                let value = cell.getValue();
                                let tag = cell.getRow().getData().tag_name;
                                let areaParam = that.area_id ? "&area_ids[0]=" + that.area_id : "";

                                let url = tag ? 
                                    "{{ url('/inventory/items') }}?tags[0]=" + encodeURIComponent(tag) + "&aging=gt-30-days" + areaParam + "&ignore_params=true&sort=amt_high&view=list" : 
                                    "{{ url('/inventory/items') }}?aging=gt-30-days&filter=no-tags" + areaParam + "&ignore_params=true&sort=amt_high&view=list";
                                return "<a href='" + url + "' wire:navigate>" + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + "</a>";
                            },
                            formatterParams: {
                                allowHTML: true
                            },
                            bottomCalc: "sum", 
                            bottomCalcFormatter: "money", 
                            bottomCalcFormatterParams: {precision: 2}
                        },
                        {
                            title: "{{ '< '. __('30 hari') }}", 
                            field: "lt_30_days", 
                            sorter: "number", 
                            formatter: function(cell) {
                                let value = cell.getValue();
                                let tag = cell.getRow().getData().tag_name;
                                let areaParam = that.area_id ? "&area_ids[0]=" + that.area_id : "";

                                let url = tag ? 
                                    "{{ url('/inventory/items') }}?tags[0]=" + encodeURIComponent(tag) + "&aging=lt-30-days" + areaParam + "&ignore_params=true&sort=amt_high&view=list" : 
                                    "{{ url('/inventory/items') }}?aging=lt-30-days&filter=no-tags" + areaParam + "&ignore_params=true&sort=amt_high&view=list";
                                return "<a href='" + url + "' wire:navigate>" + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + "</a>";
                            },
                            formatterParams: {
                                allowHTML: true
                            },
                            bottomCalc: "sum", 
                            bottomCalcFormatter: "money", 
                            bottomCalcFormatterParams: {precision: 2}
                        }
                    ],
                    rowHeader:{resizable: false, frozen: true, width:40, hozAlign:"center", formatter: "rownum", cssClass:"range-header-col", editor:false},
                    initialSort: [
                        {column: "tag_name", dir: "asc"}
                    ],
                });
            },
         };
      }
   </script>
</div>