<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetWorkspaceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $workspace = $user->currentWorkspace;

        if (! $workspace || ! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            $workspace = $user->workspaces()->first();

            if ($workspace) {
                $user->update(['current_workspace_id' => $workspace->id]);
                $user->setRelation('currentWorkspace', $workspace);
            }
        }

        // If a project is in the route, use its workspace to keep header in sync
        $project = $request->route('project');
        if ($project instanceof \App\Models\Project && $project->workspace_id) {
            $projectWorkspace = $user->workspaces()
                ->where('workspaces.id', $project->workspace_id)
                ->first();

            if ($projectWorkspace) {
                $workspace = $projectWorkspace;
                if ($user->current_workspace_id !== $workspace->id) {
                    $user->update(['current_workspace_id' => $workspace->id]);
                    $user->setRelation('currentWorkspace', $workspace);
                }
            }
        }

        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}
