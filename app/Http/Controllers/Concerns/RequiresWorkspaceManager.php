<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * These workspace integration settings pages (ClickUp/AI/Grafana) are only
 * for owners/admins. Since the URL doesn't name the workspace — it always
 * operates on whichever workspace is "current" for the user — someone can
 * land here right after switching to a workspace where they're a plain
 * member, which reads as a confusing raw 403 rather than an explanation.
 */
trait RequiresWorkspaceManager
{
    protected function ensureCanManageWorkspace(Request $request, Workspace $workspace): ?RedirectResponse
    {
        if ($request->user()->can('update', $workspace)) {
            return null;
        }

        return redirect()->route('workspaces.show')
            ->with('error', "You need to be an owner or admin of \"{$workspace->name}\" to manage this integration.");
    }

    protected function ensureCanManageWorkspaceJson(Request $request, Workspace $workspace): ?JsonResponse
    {
        if ($request->user()->can('update', $workspace)) {
            return null;
        }

        return response()->json([
            'error' => "You need to be an owner or admin of \"{$workspace->name}\" to manage this integration.",
        ], 403);
    }
}
