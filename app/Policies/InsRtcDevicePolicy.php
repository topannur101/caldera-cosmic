<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InsRtcDevice;
use Illuminate\Auth\Access\Response;

class InsRtcDevicePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function updateOrCreate(User $user): Response
    {
        $auth = $user->ins_rtc_auths->first();
        $actions = json_decode($auth->actions ?? '{}', true);
        return in_array('device-create', $actions)
        ? Response::allow()
        : Response::deny( __('Kamu tak memiliki wewenang untuk membuat atau memperbarui perangkat RTC') );
    }

    public function before(User $user): bool|null
    {
        return $user->id == 1 ? true : null;
    }
}
