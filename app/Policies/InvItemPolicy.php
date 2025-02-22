<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InvItem;
use Illuminate\Auth\Access\Response;

class InvItemPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

        /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        return count($user->invAreaIds())
        ? Response::allow()
        : Response::deny( __('Kamu tak memiliki wewenang untuk melihat inventaris') );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InvItem $invItem): Response
    {
        return $user->authInvArea($invItem->inv_area_id)
        ? Response::allow()
        : Response::deny( __('Kamu tak memiliki wewenang untuk melihat barang ini') );
    }

    public function updateLoc(User $user, InvItem $invItem) : bool
    {
        $auth = $user->inv_auths->where('inv_area_id', $invItem->inv_area_id)->first();
        $actions = json_decode($auth->actions ?? '{}', true);
        return in_array('item-loc', $actions);

    }

    public function updateTag(User $user, InvItem $invItem) : bool
    {
        $auth = $user->inv_auths->where('inv_area_id', $invItem->inv_area_id)->first();
        $actions = json_decode($auth->actions ?? '{}', true);
        return in_array('item-tag', $actions);

    }

    public function create(User $user): Response
    {
        $auths = $user->inv_auths;
        $actions = [];

        foreach ($auths as $auth) {
            $authActions = json_decode($auth->actions, true);
            $actions = array_merge($actions, $authActions);
        }
        return in_array('item-manage', $actions)
            ? Response::allow()
            : Response::deny(__('Kamu tak memiliki wewenang untuk membuat barang'));
    }

    public function store(User $user, InvItem $invItem): Response
    {
        $auth = $user->inv_auths->where('inv_area_id', $invItem->inv_area_id)->first();

        $actions = json_decode($auth->actions ?? '{}', true);
        return in_array('item-manage', $actions)
        ? Response::allow()
        : Response::deny( __('Kamu tak memiliki wewenang untuk mengelola barang di area ini'));
    }

    public function eval(User $user, InvItem $invItem): bool
    {
        $auth = $user->inv_auths->where('inv_area_id', $invItem->inv_area_id)->first();
        $actions = json_decode($auth->actions ?? '{}', true);
        return in_array('circ-eval', $actions);
    }


    public function before(User $user): bool|null
    {
        return $user->id == 1 ? true : null;
    }
}
