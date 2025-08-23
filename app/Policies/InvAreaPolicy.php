<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvAreaPolicy
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
        return Response::deny(__('Kamu tak memiliki wewenang untuk membuat atau memperbarui area inventaris'));
    }

    public function before(User $user): ?bool
    {
        return $user->id == 1 ? true : null;
    }
}
