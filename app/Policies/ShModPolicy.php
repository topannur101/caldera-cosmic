<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class ShModPolicy
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
        return Response::deny(__('Kamu tak memiliki wewenang untuk mengelola model'));
    }

    public function before(User $user): ?bool
    {
        return $user->id == 1 ? true : null;
    }
}
