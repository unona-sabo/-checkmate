<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view the workspace.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    /**
     * Determine whether the user can update the workspace.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, [WorkspaceRole::Owner, WorkspaceRole::Admin]);
    }

    /**
     * Determine whether the user can manage members of the workspace.
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, [WorkspaceRole::Owner, WorkspaceRole::Admin]);
    }

    /**
     * Determine whether the user can delete the workspace.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, [WorkspaceRole::Owner]);
    }
}
