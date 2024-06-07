<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class InsRtcMetricPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function download(User $user): Response
    {
        $auth = $user->ins_rtc_auths->first();
        $actions = json_decode($auth->actions ?? '{}', true);
        return in_array('csv-download', $actions)
        ? Response::allow()
        : Response::deny( __('Kamu tak memiliki wewenang untuk mengunduh data CSV RTC') );
    }

    public function before(User $user): bool|null
    {
        return $user->id == 1 ? true : null;
    }
}
