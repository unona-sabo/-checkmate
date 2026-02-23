<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\SwitchWorkspaceRequest;
use App\Http\Requests\Workspace\TransferOwnershipRequest;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function show(Request $request): Response
    {
        $workspace = $request->attributes->get('workspace');

        $this->authorize('view', $workspace);

        $members = $workspace->members()->get()->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->pivot->role,
        ]);

        return Inertia::render('Workspaces/Show', [
            'workspace' => $workspace,
            'members' => $members,
            'roles' => WorkspaceRole::values(),
        ]);
    }

    public function store(StoreWorkspaceRequest $request)
    {
        $validated = $request->validated();

        $slug = Str::slug($validated['name']);
        $baseSlug = $slug;
        $counter = 1;
        while (Workspace::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        $workspace = Workspace::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'owner_id' => auth()->id(),
        ]);

        $workspace->members()->attach(auth()->id(), ['role' => WorkspaceRole::Owner->value]);

        auth()->user()->update(['current_workspace_id' => $workspace->id]);

        return redirect()->route('projects.index')
            ->with('success', 'Workspace created successfully.');
    }

    public function update(StoreWorkspaceRequest $request)
    {
        $workspace = $request->attributes->get('workspace');

        $this->authorize('update', $workspace);

        $validated = $request->validated();

        $workspace->update($validated);

        return back()->with('success', 'Workspace updated successfully.');
    }

    public function destroy(Request $request)
    {
        $workspace = $request->attributes->get('workspace');

        $this->authorize('delete', $workspace);

        $affectedMembers = \App\Models\User::whereIn('id', $workspace->members()->pluck('users.id'))
            ->where('current_workspace_id', $workspace->id)
            ->get();

        foreach ($affectedMembers as $user) {
            $otherWorkspace = $user->workspaces()
                ->where('workspaces.id', '!=', $workspace->id)
                ->first();
            $user->update(['current_workspace_id' => $otherWorkspace?->id]);
        }

        $workspace->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Workspace deleted successfully.');
    }

    public function transferOwnership(TransferOwnershipRequest $request)
    {
        $workspace = $request->attributes->get('workspace');

        $this->authorize('delete', $workspace);

        $newOwnerId = $request->validated('new_owner_id');

        if (! $workspace->members()->where('users.id', $newOwnerId)->exists()) {
            return back()->withErrors(['new_owner_id' => 'The selected user is not a member of this workspace.']);
        }

        $oldOwnerId = $workspace->owner_id;

        $workspace->update(['owner_id' => $newOwnerId]);

        $workspace->members()->updateExistingPivot($newOwnerId, ['role' => WorkspaceRole::Owner->value]);
        $workspace->members()->updateExistingPivot($oldOwnerId, ['role' => WorkspaceRole::Admin->value]);

        return back()->with('success', 'Ownership transferred successfully.');
    }

    public function switchWorkspace(SwitchWorkspaceRequest $request)
    {
        $validated = $request->validated();

        $user = auth()->user();
        $workspace = Workspace::findOrFail($validated['workspace_id']);

        if (! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            abort(403, 'You are not a member of this workspace.');
        }

        $user->update(['current_workspace_id' => $workspace->id]);

        return redirect()->route('projects.index')
            ->with('success', "Switched to {$workspace->name}.");
    }
}
