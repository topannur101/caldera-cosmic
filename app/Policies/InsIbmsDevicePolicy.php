<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class InsIbmsDevicePolicy
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
        $auth = $user->ins_ibms_auths->first();
        $actions = is_string($auth->actions ?? null)
            ? json_decode($auth->actions ?? "[]", true)
            : ($auth->actions ?? []);

        return in_array("device-manage", $actions, true)
            ? Response::allow()
            : Response::deny(__("Kamu tak memiliki wewenang untuk membuat atau memperbarui perangkat IBMS"));
    }

    public function before(User $user): ?bool
    {
        return $user->id == 1 ? true : null;
    }
}
