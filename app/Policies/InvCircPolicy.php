<?php

namespace App\Policies;

use App\Models\InvCirc;
use App\Models\InvItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvCircPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function createAny(User $user): Response
    {
        $auths = $user->inv_auths;
        $actions = [];

        foreach ($auths as $auth) {
            $authActions = json_decode($auth->actions, true);
            $actions = array_merge($actions, $authActions);
        }

        return in_array('circ-create', $actions)
            ? Response::allow()
            : Response::deny(__('Kamu tak memiliki wewenang untuk membuat sirkulasi'));
    }

    public function create(User $user, InvCirc $invCirc): Response
    {
        $item = InvItem::with(['inv_stocks'])
            ->whereHas('inv_stocks', function ($query) use ($invCirc) {
                $query->where('id', $invCirc->inv_stock_id);
            })->first();

        $auth = $user->inv_auths->where('inv_area_id', $item->inv_area_id)->first();
        $actions = json_decode($auth->actions ?? '{}', true);

        return in_array('circ-create', $actions)
            ? Response::allow()
            : Response::deny(__('Kamu tak memiliki wewenang untuk membuat sirkulasi di area ini'));
    }

    public function eval(User $user, InvCirc $invCirc): bool
    {
        $auth = $user->inv_auths->where('inv_area_id', $invCirc->inv_stock->inv_item->inv_area_id)->first();
        $actions = json_decode($auth->actions ?? '{}', true);

        return in_array('circ-eval', $actions);
    }

    public function edit(User $user, InvCirc $invCirc): bool
    {
        return $this->eval($user, $invCirc) || $invCirc->user_id === $user->id;
    }

    public function before(User $user): ?bool
    {
        return $user->id == 1 ? true : null;
    }
}
