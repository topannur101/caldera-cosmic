<?php

namespace App\Policies;

use App\Models\User;
use App\Models\KpiItem;
use Illuminate\Auth\Access\Response;

class KpiItemPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function viewAny(User $user): Response
    {
        return count($user->kpiAreaIds())
        ? Response::allow()
        : Response::deny( __('Kamu tak memiliki wewenang untuk melihat laporan KPI') );
    }

    public function manage(User $user, KpiItem $kpiItem): Response
    {
        $auth = $user->kpi_auths->where('kpi_area_id', $kpiItem->kpi_area_id)->first();
        $actions = json_decode($auth->actions ?? '{}', true);
        return in_array('item-manage', $actions)
        ? Response::allow()
        : Response::deny( __('Kamu tak memiliki wewenang untuk mengelola KPI di') . ' ' . $kpiItem->kpi_area->name) ;
    }

    public function before(User $user, string $ability): bool|null
    {
        return $user->id == 1 ? true : null;
    }
}
