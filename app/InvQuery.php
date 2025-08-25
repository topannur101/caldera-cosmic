<?php

namespace App;

use App\Models\InvItem;
use App\Models\InvStock;
use Carbon\Carbon;

class InvQuery
{
    private $params;

    public function __construct(array $params = [])
    {
        $this->params = array_merge($this->getDefaults(), $this->validateParams($params));

        // Require type parameter
        if (! in_array($this->params['type'], ['stocks', 'items'])) {
            throw new \InvalidArgumentException("Type must be 'stocks' or 'items'");
        }
    }

    public function build()
    {
        $type = $this->params['type'];

        if ($type === 'stocks') {
            return $this->buildStocksQuery();
        }

        return $this->buildItemsQuery();
    }

    /**
     * Build query optimized for export/chunking
     */
    public function buildForExport()
    {
        return $this->build();
    }

    private function buildStocksQuery()
    {
        $query = InvStock::with([
            'inv_item',
            'inv_curr',
            'inv_item.inv_loc',
            'inv_item.inv_area',
            'inv_item.inv_tags',
        ])
            ->whereHas('inv_item', function ($itemQuery) {
                $this->applyFilters($itemQuery);
            });

        // Active inv_stocks only
        $query->where('is_active', true);
        $this->applySorting($query);

        return $query;
    }

    private function buildItemsQuery()
    {
        $query = InvItem::with([
            'inv_loc',
            'inv_tags',
            'inv_stocks',
            'inv_stocks.inv_curr',
            'inv_area',
        ]);

        $this->applyFilters($query);
        $this->applySorting($query);

        return $query;
    }

    private function applyFilters($query)
    {
        $this->applySearchFilters($query);
        $this->applyAreaFilter($query);
        $this->applyLocationFilters($query);
        $this->applyUomFilters($query);
        $this->applyTagFilters($query);
        $this->applyGeneralFilters($query);
        $this->applyAgingFilters($query);
        $this->applyLimitFilters($query);
    }

    private function applySearchFilters($query)
    {
        $q = $this->params['search'];
        $name = $this->params['name'];
        $desc = $this->params['desc'];
        $code = $this->params['code'];
        $isLinked = $this->params['is_linked'];

        // Items (if linked)
        if ($isLinked && $q) {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%$q%")
                    ->orWhere('code', 'like', "%$q%")
                    ->orWhere('desc', 'like', "%$q%");
            });
        } else {
            if ($name) {
                $query->where('name', 'like', "%$name%");
            }

            if ($desc) {
                $query->where('desc', 'like', "%$desc%");
            }

            if ($code) {
                $query->where('code', 'like', "%$code%");
            }
        }
    }

    private function applyAreaFilter($query)
    {
        $areaIds = $this->params['area_ids'];
        if (! empty($areaIds)) {
            $query->whereIn('inv_area_id', $areaIds);
        }
    }

    private function applyLocationFilters($query)
    {
        $locParent = $this->params['loc_parent'];
        $locBin = $this->params['loc_bin'];

        $query->where(function ($subQuery) use ($locParent, $locBin) {
            if ($locParent || $locBin) {
                $subQuery->whereHas('inv_loc', function ($subSubQuery) use ($locParent, $locBin) {
                    if ($locParent) {
                        $subSubQuery->where('parent', 'like', "%$locParent%");
                    }
                    if ($locBin) {
                        $subSubQuery->where('bin', 'like', "%$locBin%");
                    }
                });
            }
        });
    }

    private function applyUomFilters($query)
    {
        $uom = $this->params['uom'];

        if ($uom) {
            // For stocks query, filter directly on inv_stocks.uom
            if ($this->params['type'] === 'stocks') {
                $query->where('uom', 'like', "%$uom%");
            } else {
                // For items query, filter through inv_stocks relationship
                $query->whereHas('inv_stocks', function ($stockQuery) use ($uom) {
                    $stockQuery->where('uom', 'like', "%$uom%")
                        ->where('is_active', true);
                });
            }
        }
    }

    private function applyTagFilters($query)
    {
        $tags = $this->params['tags'];

        $query->where(function ($subQuery) use ($tags) {
            if (count($tags)) {
                $subQuery->whereHas('inv_tags', function ($subSubQuery) use ($tags) {
                    $subSubQuery->whereIn('name', $tags);
                });
            }
        });
    }

    private function applyLimitFilters($query)
    {
        $limit = $this->params['limit'];
        
        if (!$limit) {
            return;
        }

        if ($this->params['type'] === 'stocks') {
            switch ($limit) {
                case 'under-qty-limit':
                    $query->whereColumn('qty', '<', 'qty_min')
                          ->where('qty_min', '>', 0);
                    break;

                case 'over-qty-limit':
                    $query->whereColumn('qty', '>', 'qty_max')
                          ->where('qty_max', '>', 0);
                    break;

                case 'outside-qty-limit':
                    $query->where(function ($subQuery) {
                        $subQuery->where(function ($q) {
                            $q->whereColumn('qty', '<', 'qty_min')
                              ->where('qty_min', '>', 0);
                        })->orWhere(function ($q) {
                            $q->whereColumn('qty', '>', 'qty_max')
                              ->where('qty_max', '>', 0);
                        });
                    });
                    break;

                case 'inside-qty-limit':
                    $query->whereColumn('qty', '>=', 'qty_min')
                          ->whereColumn('qty', '<=', 'qty_max')
                          ->where('qty_min', '>', 0)
                          ->where('qty_max', '>', 0);
                    break;

                case 'no-qty-limit':
                    $query->where(function ($subQuery) {
                        $subQuery->where('qty_min', 0)
                                 ->where('qty_max', 0);
                    });
                    break;
            }
        } else {
            // For items query, filter through inv_stocks relationship
            switch ($limit) {
                case 'under-qty-limit':
                    $query->whereHas('inv_stocks', function ($stockQuery) {
                        $stockQuery->where('is_active', true)
                                   ->whereColumn('qty', '<', 'qty_min')
                                   ->where('qty_min', '>', 0);
                    });
                    break;

                case 'over-qty-limit':
                    $query->whereHas('inv_stocks', function ($stockQuery) {
                        $stockQuery->where('is_active', true)
                                   ->whereColumn('qty', '>', 'qty_max')
                                   ->where('qty_max', '>', 0);
                    });
                    break;

                case 'outside-qty-limit':
                    $query->whereHas('inv_stocks', function ($stockQuery) {
                        $stockQuery->where('is_active', true)
                                   ->where(function ($subQuery) {
                                       $subQuery->where(function ($q) {
                                           $q->whereColumn('qty', '<', 'qty_min')
                                             ->where('qty_min', '>', 0);
                                       })->orWhere(function ($q) {
                                           $q->whereColumn('qty', '>', 'qty_max')
                                             ->where('qty_max', '>', 0);
                                       });
                                   });
                    });
                    break;

                case 'inside-qty-limit':
                    $query->whereHas('inv_stocks', function ($stockQuery) {
                        $stockQuery->where('is_active', true)
                                   ->whereColumn('qty', '>=', 'qty_min')
                                   ->whereColumn('qty', '<=', 'qty_max')
                                   ->where('qty_min', '>', 0)
                                   ->where('qty_max', '>', 0);
                    });
                    break;

                case 'no-qty-limit':
                    $query->whereHas('inv_stocks', function ($stockQuery) {
                        $stockQuery->where('is_active', true)
                                   ->where('qty_min', 0)
                                   ->where('qty_max', 0);
                    });
                    break;
            }
        }
    }

    private function applyGeneralFilters($query)
    {
        $filter = $this->params['filter'];

        switch ($filter) {
            case 'no-code':
                $query->whereNull('code');
                break;

            case 'no-photo':
                $query->whereNull('photo');
                break;

            case 'no-location':
                $query->whereNull('inv_loc_id');
                break;

            case 'no-tags':
                $query->whereDoesntHave('inv_tags');
                break;

            case 'inactive':
                $query->where('is_active', false);
                break;

            default:
                $query->where('is_active', true);
                break;
        }
    }

    private function applyAgingFilters($query)
    {
        $aging = $this->params['aging'];

        if (! $aging) {
            return;
        }

        $now = Carbon::now();
        $sub_100_days = $now->copy()->subDays(100);
        $sub_90_days = $now->copy()->subDays(90);
        $sub_60_days = $now->copy()->subDays(60);
        $sub_30_days = $now->copy()->subDays(30);

        switch ($aging) {
            case 'gt-100-days':
                $query->where(function ($q) use ($sub_100_days) {
                    $q->where('last_withdrawal', '<', $sub_100_days)
                        ->orWhereNull('last_withdrawal');
                });
                break;

            case 'gt-90-days':
                $query->whereBetween('last_withdrawal', [$sub_100_days, $sub_90_days]);
                break;

            case 'gt-60-days':
                $query->whereBetween('last_withdrawal', [$sub_90_days, $sub_60_days]);
                break;

            case 'gt-30-days':
                $query->whereBetween('last_withdrawal', [$sub_60_days, $sub_30_days]);
                break;

            case 'lt-30-days':
                $query->where('last_withdrawal', '>', $sub_30_days);
                break;
        }
    }

    private function applySorting($query)
    {
        $sort = $this->params['sort'];
        $type = $this->params['type'];

        if ($type === 'stocks') {
            $this->applyStocksSorting($query, $sort);
        } else {
            $this->applyItemsSorting($query, $sort);
        }
    }

    private function applyStocksSorting($query, $sort)
    {
        switch ($sort) {
            case 'updated':
                $query->orderByDesc('updated_at')->orderBy('inv_item_id');
                break;
            case 'created':
                $query->orderByDesc('created_at')->orderBy('inv_item_id');
                break;

            case 'loc':
                $query->whereHas('inv_item.inv_loc');
                $query->orderByRaw('
                    (SELECT parent FROM inv_locs WHERE 
                    inv_locs.id = (SELECT inv_loc_id FROM inv_items 
                    WHERE inv_items.id = inv_stocks.inv_item_id)) ASC
                    ,
                    (SELECT bin FROM inv_locs WHERE 
                    inv_locs.id = (SELECT inv_loc_id FROM inv_items 
                    WHERE inv_items.id = inv_stocks.inv_item_id)) ASC
                ');
                break;

            case 'last_deposit':
                $query->orderByRaw('
                    (SELECT last_deposit FROM inv_items
                    WHERE inv_items.id = inv_stocks.inv_item_id) DESC,
                    inv_stocks.uom ASC,
                    inv_stocks.inv_item_id ASC');
                break;

            case 'last_withdrawal':
                $query->orderByRaw('
                    (SELECT last_withdrawal FROM inv_items
                    WHERE inv_items.id = inv_stocks.inv_item_id) DESC,
                    inv_stocks.uom ASC,
                    inv_stocks.inv_item_id ASC');
                break;

            case 'qty_low':
                $query->orderBy('qty');
                break;

            case 'qty_high':
                $query->orderByDesc('qty');
                break;

            case 'amt_low':
                $query->orderBy('amount_main');
                break;

            case 'amt_high':
                $query->orderByDesc('amount_main');
                break;

            case 'wf_low':
                $query->orderBy('wf')->where('wf', '>', 0);
                break;

            case 'wf_high':
                $query->orderByDesc('wf')->where('wf', '>', 0);
                break;

            case 'alpha':
                $query->orderByRaw('
                    (SELECT name FROM inv_items 
                    WHERE inv_items.id = inv_stocks.inv_item_id) ASC');
                break;
        }
    }

    private function applyItemsSorting($query, $sort)
    {
        switch ($sort) {
            case 'updated':
                $query->orderByDesc('updated_at');
                break;

            case 'created':
                $query->orderByDesc('created_at');
                break;

            case 'loc':
                $query->whereHas('inv_loc');
                $query->orderByRaw('
                    (SELECT parent FROM inv_locs WHERE inv_locs.id = inv_items.inv_loc_id) ASC,
                    (SELECT bin FROM inv_locs WHERE inv_locs.id = inv_items.inv_loc_id) ASC
                ');
                break;

            case 'last_deposit':
                $query->orderByDesc('last_deposit');
                break;

            case 'last_withdrawal':
                $query->orderByDesc('last_withdrawal');
                break;

            case 'alpha':
                $query->orderBy('name');
                break;

                // Items don't have qty/amt/wf directly, so we skip those
        }
    }

    private function getDefaults()
    {
        return [
            'type' => null, // Required
            'search' => null,
            'name' => null,
            'desc' => null,
            'code' => null,
            'loc_parent' => null,
            'loc_bin' => null,
            'uom' => null,
            'tags' => [],
            'is_linked' => false,
            'area_ids' => [],
            'filter' => null,
            'aging' => null,
            'limit' => null,
            'sort' => null,
        ];
    }

    /**
     * Validate and sanitize input parameters
     */
    private function validateParams(array $params)
    {
        // Ensure arrays are actually arrays
        if (isset($params['tags']) && ! is_array($params['tags'])) {
            $params['tags'] = [];
        }

        if (isset($params['area_ids']) && ! is_array($params['area_ids'])) {
            $params['area_ids'] = [];
        }

        return $params;
    }

    /**
     * Create InvQuery from session parameters (for controller usage)
     */
    public static function fromSessionParams(array $sessionParams = [], ?string $type = null)
    {
        if (! $type) {
            throw new \InvalidArgumentException('Type parameter is required');
        }

        return new static([
            'type' => $type,
            'search' => $sessionParams['q'] ?? null,
            'name' => $sessionParams['name'] ?? null,
            'desc' => $sessionParams['desc'] ?? null,
            'code' => $sessionParams['code'] ?? null,
            'loc_parent' => $sessionParams['loc_parent'] ?? null,
            'loc_bin' => $sessionParams['loc_bin'] ?? null,
            'uom' => $sessionParams['uom'] ?? null,
            'tags' => $sessionParams['tags'] ?? [],
            'area_ids' => $sessionParams['area_ids'] ?? [],
            'filter' => $sessionParams['filter'] ?? null,
            'aging' => $sessionParams['aging'] ?? null,
            'limit' => $sessionParams['limit'] ?? null,
            'sort' => $sessionParams['sort'] ?? 'updated',
            'is_linked' => $sessionParams['is_linked'] ?? false,
        ]);
    }

    /**
     * Static factory method for fluent interface
     */
    public static function create(array $params = [])
    {
        return new static($params);
    }
}
