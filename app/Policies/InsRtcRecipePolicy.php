<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class InsRtcRecipePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function manage(User $user): Response
    {
        $auth = $user->ins_rtc_auths->first();
        $actions = json_decode($auth->actions ?? '{}', true);

        return in_array('recipe-manage', $actions)
        ? Response::allow()
        : Response::deny(__('Kamu tak memiliki wewenang untuk membuat atau memperbarui resep RTC'));
    }

    public function before(User $user): ?bool
    {
        return $user->id == 1 ? true : null;
    }
}
