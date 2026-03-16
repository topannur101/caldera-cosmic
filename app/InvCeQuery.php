<?php

namespace App;

use App\Models\InvCeArea;
use App\Models\InvCeAuth;
use App\Models\InvCeChemical;
use App\Models\InvCeCirc;
use App\Models\InvCeStock;
use App\Models\InvCeVendor;
use Carbon\Carbon;

class InvCeQuery
{
    private $params;

    public function __construct(array $params = [])
    {
        $this->params = array_merge($this->getDefaults(), $this->validateParams($params));

        // Require type parameter
        if (! in_array($this->params['type'], ['stocks', 'chemicals'])) {
            throw new \InvalidArgumentException("Type must be 'stocks' or 'chemicals'");
        }
    }

    public function build()
    {
        $type = $this->params['type'];

        if ($type === 'stocks') {
            return $this->buildStocksQuery();
        }

        return $this->buildChemicalsQuery();
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
        $query = InvCeStock::with([
            'inv_ce_chemical',
            'inv_ce_chemical.inv_ce_location',
            'inv_ce_chemical.inv_ce_area',
            'inv_ce_chemical.inv_ce_vendor',
            'inv_ce_circ',
        ])
            ->whereHas('inv_ce_chemical', function ($chemicalQuery) {
                $this->applyFilters($chemicalQuery);
            });

        $this->applySorting($query);

        return $query;
    }

    private function buildChemicalsQuery()
    {
        $query = InvCeChemical::with([
            'inv_ce_location',
            'inv_ce_stocks',
            'inv_ce_area',
            'inv_ce_vendor',
        ])->where('category_chemical', 'single');

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

        // Chemicals (if linked)
        if ($isLinked && $q) {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%$q%")
                    ->orWhere('item_code', 'like', "%$q%")
                    ->orWhere('category_chemical', 'like', "%$q%");
            });
        } else {
            if ($name) {
                $query->where('name', 'like', "%$name%");
            }

            if ($desc) {
                $query->where('category_chemical', 'like', "%$desc%");
            }

            if ($code) {
                $query->where('item_code', 'like', "%$code%");
            }
        }
    }

    private function applyAreaFilter($query)
    {
        $areaIds = $this->params['area_ids'];
        if (! empty($areaIds)) {
            $query->whereIn('area_id', $areaIds);
        }
    }

    private function applyLocationFilters($query)
    {
        $locParent = $this->params['loc_parent'];
        $locBin = $this->params['loc_bin'];

        if (! $locParent && ! $locBin) {
            return;
        }

        $query->whereHas('inv_ce_location', function ($locationQuery) use ($locParent, $locBin) {
            if ($locParent) {
                $locationQuery->where('parent', 'like', "%$locParent%");
            }
            if ($locBin) {
                $locationQuery->where('bin', 'like', "%$locBin%");
            }
        });
    }

    private function applyUomFilters($query)
    {
        $uom = $this->params['uom'];

        if ($uom) {
            if ($this->params['type'] === 'stocks') {
                $query->whereHas('inv_ce_chemical', function ($chemicalQuery) use ($uom) {
                    $chemicalQuery->where('uom', 'like', "%$uom%");
                });
            } else {
                $query->where('uom', 'like', "%$uom%");
            }
        }
    }

    private function applyTagFilters($query)
    {
        $tags = $this->params['tags'];
        if (! count($tags)) {
            return;
        }

        // CE schema does not currently define tags relation.
        // Keep this method as a safe no-op until tags are implemented.
    }

    private function applyLimitFilters($query)
    {
        $limit = $this->params['limit'];

        if (! $limit) {
            return;
        }

        if ($this->params['type'] === 'stocks') {
            switch ($limit) {
                case 'under-qty-limit':
                    $query->where('quantity', '<=', 0);
                    break;

                case 'over-qty-limit':
                    $query->where('quantity', '>', 0);
                    break;

                case 'outside-qty-limit':
                    $query->where('quantity', '<=', 0);
                    break;

                case 'inside-qty-limit':
                    $query->where('quantity', '>', 0);
                    break;

                case 'no-qty-limit':
                    $query->where('quantity', 0);
                    break;
            }
        } else {
            // For chemicals query, filter through inv_ce_stocks relationship.
            switch ($limit) {
                case 'under-qty-limit':
                    $query->whereHas('inv_ce_stock', function ($stockQuery) {
                        $stockQuery->where('quantity', '<=', 0);
                    });
                    break;

                case 'over-qty-limit':
                    $query->whereHas('inv_ce_stock', function ($stockQuery) {
                        $stockQuery->where('quantity', '>', 0);
                    });
                    break;

                case 'outside-qty-limit':
                    $query->whereHas('inv_ce_stock', function ($stockQuery) {
                        $stockQuery->where('quantity', '<=', 0);
                    });
                    break;

                case 'inside-qty-limit':
                    $query->whereHas('inv_ce_stock', function ($stockQuery) {
                        $stockQuery->where('quantity', '>', 0);
                    });
                    break;

                case 'no-qty-limit':
                    $query->whereHas('inv_ce_stock', function ($stockQuery) {
                        $stockQuery->where('quantity', 0);
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
                $query->where(function ($subQuery) {
                    $subQuery->whereNull('item_code')->orWhere('item_code', '');
                });
                break;

            case 'no-photo':
                $query->where(function ($subQuery) {
                    $subQuery->whereNull('photo')->orWhere('photo', '');
                });
                break;

            case 'no-location':
                $query->whereNull('location_id');
                break;

            case 'no-tags':
                // CE schema does not currently define tags relation.
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

        if ($this->params['type'] === 'stocks') {
            switch ($aging) {
                case 'gt-100-days':
                    $query->where('updated_at', '<', $sub_100_days);
                    break;

                case 'gt-90-days':
                    $query->whereBetween('updated_at', [$sub_100_days, $sub_90_days]);
                    break;

                case 'gt-60-days':
                    $query->whereBetween('updated_at', [$sub_90_days, $sub_60_days]);
                    break;

                case 'gt-30-days':
                    $query->whereBetween('updated_at', [$sub_60_days, $sub_30_days]);
                    break;

                case 'lt-30-days':
                    $query->where('updated_at', '>', $sub_30_days);
                    break;
            }
        } else {
            switch ($aging) {
                case 'gt-100-days':
                    $query->whereHas('inv_ce_stock', function ($stockQuery) use ($sub_100_days) {
                        $stockQuery->where('updated_at', '<', $sub_100_days);
                    });
                    break;

                case 'gt-90-days':
                    $query->whereHas('inv_ce_stock', function ($stockQuery) use ($sub_100_days, $sub_90_days) {
                        $stockQuery->whereBetween('updated_at', [$sub_100_days, $sub_90_days]);
                    });
                    break;

                case 'gt-60-days':
                    $query->whereHas('inv_ce_stock', function ($stockQuery) use ($sub_90_days, $sub_60_days) {
                        $stockQuery->whereBetween('updated_at', [$sub_90_days, $sub_60_days]);
                    });
                    break;

                case 'gt-30-days':
                    $query->whereHas('inv_ce_stock', function ($stockQuery) use ($sub_60_days, $sub_30_days) {
                        $stockQuery->whereBetween('updated_at', [$sub_60_days, $sub_30_days]);
                    });
                    break;

                case 'lt-30-days':
                    $query->whereHas('inv_ce_stock', function ($stockQuery) use ($sub_30_days) {
                        $stockQuery->where('updated_at', '>', $sub_30_days);
                    });
                    break;
            }
        }
    }

    private function applySorting($query)
    {
        $sort = $this->params['sort'];
        $type = $this->params['type'];

        if ($type === 'stocks') {
            $this->applyStocksSorting($query, $sort);
        } else {
            $this->applyChemicalsSorting($query, $sort);
        }
    }

    private function applyStocksSorting($query, $sort)
    {
        switch ($sort) {
            case 'updated':
                $query->orderByDesc('updated_at')->orderBy('inv_ce_chemical_id');
                break;
            case 'created':
                $query->orderByDesc('created_at')->orderBy('inv_ce_chemical_id');
                break;

            case 'loc':
                $query->orderByRaw('
                    (SELECT parent FROM inv_ce_locations WHERE
                    inv_ce_locations.id = (SELECT location_id FROM inv_ce_chemicals
                    WHERE inv_ce_chemicals.id = inv_ce_stock.inv_ce_chemical_id)) ASC
                    ,
                    (SELECT bin FROM inv_ce_locations WHERE
                    inv_ce_locations.id = (SELECT location_id FROM inv_ce_chemicals
                    WHERE inv_ce_chemicals.id = inv_ce_stock.inv_ce_chemical_id)) ASC
                ');
                break;

            case 'last_deposit':
                $query->orderByDesc('created_at')->orderBy('inv_ce_chemical_id');
                break;

            case 'last_withdrawal':
                $query->orderByDesc('updated_at')->orderBy('inv_ce_chemical_id');
                break;

            case 'qty_low':
                $query->orderBy('quantity');
                break;

            case 'qty_high':
                $query->orderByDesc('quantity');
                break;

            case 'alpha':
                $query->orderByRaw('
                    (SELECT name FROM inv_ce_chemicals
                    WHERE inv_ce_chemicals.id = inv_ce_stock.inv_ce_chemical_id) ASC');
                break;

            default:
                $query->orderByDesc('updated_at')->orderBy('inv_ce_chemical_id');
                break;
        }
    }

    private function applyChemicalsSorting($query, $sort)
    {
        switch ($sort) {
            case 'updated':
                $query->orderByDesc('updated_at');
                break;

            case 'created':
                $query->orderByDesc('created_at');
                break;

            case 'loc':
                $query->orderByRaw('
                    (SELECT parent FROM inv_ce_locations WHERE inv_ce_locations.id = inv_ce_chemicals.location_id) ASC,
                    (SELECT bin FROM inv_ce_locations WHERE inv_ce_locations.id = inv_ce_chemicals.location_id) ASC
                ');
                break;

            case 'last_deposit':
                $query->orderByRaw('
                    (SELECT MAX(created_at) FROM inv_ce_stock
                    WHERE inv_ce_stock.inv_ce_chemical_id = inv_ce_chemicals.id) DESC');
                break;

            case 'last_withdrawal':
                $query->orderByRaw('
                    (SELECT MAX(updated_at) FROM inv_ce_stock
                    WHERE inv_ce_stock.inv_ce_chemical_id = inv_ce_chemicals.id) DESC');
                break;

            case 'alpha':
                $query->orderBy('name');
                break;

            default:
                $query->orderByDesc('updated_at');
                break;
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
