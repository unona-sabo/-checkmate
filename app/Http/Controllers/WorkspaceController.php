<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

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

    public function update(Request $request)
    {
        $workspace = $request->attributes->get('workspace');

        $this->authorize('update', $workspace);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $workspace->update($validated);

        return back()->with('success', 'Workspace updated successfully.');
    }

    public function destroy(Request $request)
    {
        $workspace = $request->attributes->get('workspace');

        $this->authorize('delete', $workspace);

        $memberIds = $workspace->members()->pluck('users.id');

        foreach ($memberIds as $memberId) {
            $user = \App\Models\User::find($memberId);
            if ($user && $user->current_workspace_id === $workspace->id) {
                $otherWorkspace = $user->workspaces()
                    ->where('workspaces.id', '!=', $workspace->id)
                    ->first();
                $user->update(['current_workspace_id' => $otherWorkspace?->id]);
            }
        }

        $workspace->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Workspace deleted successfully.');
    }

    public function switchWorkspace(Request $request)
    {
        $validated = $request->validate([
            'workspace_id' => 'required|exists:workspaces,id',
        ]);

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
