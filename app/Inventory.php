<?php

namespace App;

use Carbon\Carbon;
use App\Models\InvCirc;
use App\Models\InvItem;
use Illuminate\Database\Eloquent\Builder;

class Inventory
{
    public static function itemsBuild($areas, $q, $status, $filter, $loc, $tag, $without, $sort, $qty): Builder
    {
        $q = trim($q);
        $inv_items = InvItem::whereIn('inv_items.inv_area_id', $areas)->where(function (Builder $query) use ($q) {
            $query
                ->orWhere('inv_items.name', 'LIKE', '%' . $q . '%')
                ->orWhere('inv_items.desc', 'LIKE', '%' . $q . '%')
                ->orWhere('inv_items.code', 'LIKE', '%' . $q . '%');
        });
        switch ($status) {
            case 'active':
                $inv_items->where('inv_items.is_active', true);
                break;
            case 'inactive':
                $inv_items->where('inv_items.is_active', false);
                break;
        }
        if ($filter) {
            if ($loc) {
                $loc = trim($loc);
                $inv_items
                    ->join('inv_locs', 'inv_items.inv_loc_id', '=', 'inv_locs.id')
                    ->where('inv_locs.name', 'like', '%' . $loc . '%')
                    ->select('inv_items.*', 'inv_locs.name as loc_names');
            }
            if ($tag) {
                $tag = trim($tag);
                $inv_items
                    ->join('inv_item_tags', 'inv_items.id', '=', 'inv_item_tags.inv_item_id')
                    ->join('inv_tags', 'inv_item_tags.inv_tag_id', '=', 'inv_tags.id')
                    ->where('inv_tags.name', 'like', '%' . $tag . '%')
                    ->select('inv_items.*', 'inv_tags.name as tag_names');
            }
            switch ($without) {
                case 'loc':
                    $inv_items->whereNull('inv_items.inv_loc_id');
                    break;
                case 'tags':
                    $inv_items->whereNotIn('id', function ($query) {
                        $query->select('inv_item_id')->from('inv_item_tags');
                    });
                    break;
                case 'photo':
                    $inv_items->whereNull('inv_items.photo');
                    break;
                case 'code':
                    $inv_items->whereNull('inv_items.code');
                    break;
                case 'qty_main_min':
                    $inv_items->where('inv_items.qty_main_min', 0);
                    break;
                case 'qty_main_max':
                    $inv_items->where('inv_items.qty_main_max', 0);
                    break;
            }
        }

        switch ($sort) {
            case 'updated':
                $inv_items->orderByDesc('inv_items.updated_at');
                break;
            case 'created':
                $inv_items->orderByDesc('inv_items.created_at');
                break;
            case 'price_low':
                $inv_items->orderBy('inv_items.price');
                break;
            case 'price_high':
                $inv_items->orderByDesc('inv_items.price');
                break;
            case 'qty_low':
                switch ($qty) {
                    case 'total':
                        $inv_items->selectRaw('*, (inv_items.qty_main + inv_items.qty_used + inv_items.qty_rep) as qty_total')->orderBy('qty_total');
                        break;
                    case 'main':
                        $inv_items->orderBy('inv_items.qty_main');
                        break;
                    case 'used':
                        $inv_items->orderBy('inv_items.qty_used');
                        break;
                    case 'rep':
                        $inv_items->orderBy('inv_items.qty_rep');
                        break;
                }
                break;
            case 'qty_high':
                switch ($qty) {
                    case 'total':
                        $inv_items->selectRaw('*, (inv_items.qty_main + inv_items.qty_used + inv_items.qty_rep) as qty_total')->orderByDesc('qty_total');
                        break;
                    case 'main':
                        $inv_items->orderByDesc('inv_items.qty_main');
                        break;
                    case 'used':
                        $inv_items->orderByDesc('inv_items.qty_used');
                        break;
                    case 'rep':
                        $inv_items->orderByDesc('inv_items.qty_rep');
                        break;
                }
                break;
                break;

            case 'alpha':
                $inv_items->orderBy('inv_items.name');
                break;
        }
        return $inv_items;
    }

    public static function circsBuild($area_ids_clean, $q, $status, $user, $qdirs, $start_at, $end_at, $sort) : Builder
    {
        $circs = InvCirc::join('inv_items', 'inv_circs.inv_item_id', '=', 'inv_items.id')
        ->whereIn('inv_items.inv_area_id', $area_ids_clean)
        ->select('inv_circs.*', 'inv_items.name', 'inv_items.desc', 'inv_items.code');

        if ($q) {
            $circs->where(function (Builder $query) use ($q) {
                $query->orWhere('inv_items.name', 'LIKE', '%'.$q.'%')
                      ->orWhere('inv_items.desc', 'LIKE', '%'.$q.'%')
                      ->orWhere('inv_items.code', 'LIKE', '%'.$q.'%')
                      ->orWhere('inv_circs.remarks', 'LIKE', '%'.$q.'%');
            });
        }

        $statusMap = [
            'pending'   => 0,
            'approved'  => 1,
            'rejected'  => 2,
        ];
        $statusCodes = [];
        foreach ($status as $i) {
            // Check if the status exists in the mapping
            if (array_key_exists($i, $statusMap)) {
                // Map the status to its corresponding numeric value and add to the new array
                $statusCodes[] = $statusMap[$i];
            }
        }
        if (count($statusCodes)) {
            $circs->whereIn('inv_circs.status', $statusCodes);
        } else {
            $circs->where('inv_circs.status', 9);
        }

        $user = trim($user);
        if($user) {
            $circs->join('users', 'inv_circs.user_id', '=', 'users.id')
            ->select('inv_circs.*', 'users.name as user_names', 'users.emp_id');
            $circs->where(function (Builder $query) use ($user) {
                $query->orWhere('users.name', 'LIKE', '%'.$user.'%')
                ->orWhere('users.emp_id', 'LIKE', '%'.$user.'%');
            });
        }

        if(count($qdirs)) {
            $circs->where(function (Builder $query) use ($qdirs) {
                $query;
                if(in_array('deposit', $qdirs)) {
                    $query->orWhere('inv_circs.qty', '>', 0);
                }
                if(in_array('withdrawal', $qdirs)) {
                    $query->orWhere('inv_circs.qty', '<', 0);
                }
                if(in_array('capture', $qdirs)) {
                    $query->orWhere('inv_circs.qty', 0);
                }
            });
        } else {
            $circs->whereNull('qty');
        }

        if($start_at && $end_at) {

            $start  = Carbon::parse($start_at);
            $end    = Carbon::parse($end_at)->addDay();

            $circs->whereBetween('inv_circs.updated_at', [$start, $end]);

        } else {
            $circs->whereNull('inv_circs.updated_at');
        }        

        switch ($sort) {
            case 'updated':
                $circs->orderByDesc('inv_circs.updated_at');
                break;
            case 'created':
                $circs->orderByDesc('inv_circs.created_at');
                break;            
            case 'amount_low':
                $circs->orderBy('inv_circs.amount');
                break;
            case 'amount_high':
                $circs->orderByDesc('inv_circs.amount');
                break;
            case 'qty_low':
                $circs->orderByRaw('ABS(qty)');
                break;
            case 'qty_high':
                $circs->orderByRaw('ABS(qty) DESC');
                break;        
        }

        return $circs;
    }
}
