<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * These workspace integration settings pages (ClickUp/AI/Grafana) are only
 * for owners/admins — everyone else gets a friendly redirect explaining why,
 * instead of a raw 403.
 */
trait RequiresWorkspaceManager
{
    protected function ensureCanManageWorkspace(Request $request, Workspace $workspace): ?RedirectResponse
    {
        if ($request->user()->can('update', $workspace)) {
            return null;
        }

        return redirect()->route('projects.index')
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
