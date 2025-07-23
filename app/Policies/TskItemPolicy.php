<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TskItem;
use App\Models\TskProject;
use App\Models\TskAuth;
use Illuminate\Auth\Access\Response;

class TskItemPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user): bool|null
    {
        return $user->id == 1 ? true : null;
    }

    /**
     * Determine whether the user can create tasks in a project.
     */
    public function create(User $user, TskProject $project): Response
    {
        // Any team member can create tasks in their team projects
        return $this->isTeamMember($user, $project->tsk_team_id)
            ? Response::allow()
            : Response::deny(__('Anda bukan anggota tim untuk proyek ini'));
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, TskItem $task): Response
    {
        // Team members can view tasks in their team
        return $this->isTeamMember($user, $task->tsk_project->tsk_team_id)
            ? Response::allow()
            : Response::deny(__('Anda tidak memiliki akses ke tugas ini'));
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, TskItem $task): Response
    {
        $teamId = $task->tsk_project->tsk_team_id;

        // Task creator can edit their own task
        if ($task->created_by === $user->id) {
            return Response::allow();
        }

        // Task assignee can edit task assigned to them
        if ($task->assigned_to === $user->id) {
            return Response::allow();
        }

        // Team members with task-manage can edit tasks that are NOT their own
        if ($this->hasTeamPermission($user, $teamId, 'task-manage')) {
            return Response::allow();
        }

        return Response::deny(__('Anda tidak memiliki izin untuk mengedit tugas ini'));
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, TskItem $task): Response
    {
        $teamId = $task->tsk_project->tsk_team_id;

        // Task creator can delete their own task
        if ($task->created_by === $user->id) {
            return Response::allow();
        }

        // Team members with task-manage can delete tasks that are NOT their own
        if ($this->hasTeamPermission($user, $teamId, 'task-manage')) {
            return Response::allow();
        }

        return Response::deny(__('Anda tidak memiliki izin untuk menghapus tugas ini'));
    }

    /**
     * Determine whether the user can assign tasks to others.
     */
    public function assign(User $user, TskItem $task, User $assignee): Response
    {
        $teamId = $task->tsk_project->tsk_team_id;

        // Check if assigner has task-assign permission in this team
        if (!$this->hasTeamPermission($user, $teamId, 'task-assign')) {
            return Response::deny(__('Anda tidak memiliki izin untuk menugaskan tugas ini'));
        }

        // Check if assignee is member of this team
        if (!$this->isTeamMember($assignee, $teamId)) {
            return Response::deny(__('Pengguna yang dipilih bukan anggota tim'));
        }

        return Response::allow();
    }

    /**
     * Check if user is a team member.
     */
    private function isTeamMember(User $user, int $teamId): bool
    {
        return TskAuth::where('user_id', $user->id)
            ->where('tsk_team_id', $teamId)
            ->exists();
    }

    /**
     * Check if user has specific permission in team.
     */
    private function hasTeamPermission(User $user, int $teamId, string $permission): bool
    {
        $auth = TskAuth::where('user_id', $user->id)
            ->where('tsk_team_id', $teamId)
            ->first();

        return $auth && $auth->hasPermission($permission);
    }
}