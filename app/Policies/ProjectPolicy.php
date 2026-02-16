<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): bool
    {
        if (! $project->workspace_id) {
            return $user->id === $project->user_id;
        }

        return $user->hasWorkspaceRole($project->workspace, [
            WorkspaceRole::Owner,
            WorkspaceRole::Admin,
            WorkspaceRole::Member,
            WorkspaceRole::Viewer,
        ]);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        if (! $workspace) {
            return true;
        }

        $role = $user->workspaceRole($workspace);

        return $role ? $role->canManageProjects() : false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        if (! $project->workspace_id) {
            return $user->id === $project->user_id;
        }

        return $user->hasWorkspaceRole($project->workspace, [
            WorkspaceRole::Owner,
            WorkspaceRole::Admin,
            WorkspaceRole::Member,
        ]);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        if (! $project->workspace_id) {
            return $user->id === $project->user_id;
        }

        return $user->hasWorkspaceRole($project->workspace, [
            WorkspaceRole::Owner,
            WorkspaceRole::Admin,
        ]);
    }
}
