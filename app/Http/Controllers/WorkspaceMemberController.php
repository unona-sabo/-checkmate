<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Http\Request;

class WorkspaceMemberController extends Controller
{
    public function store(Request $request)
    {
        $workspace = $request->attributes->get('workspace');

        $this->authorize('manageMembers', $workspace);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|string|in:admin,member,viewer',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($workspace->members()->where('users.id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'This user is already a member of this workspace.']);
        }

        $workspace->members()->attach($user->id, ['role' => $validated['role']]);

        return back()->with('success', "{$user->name} has been added to the workspace.");
    }

    public function update(Request $request, int $memberId)
    {
        $workspace = $request->attributes->get('workspace');

        $this->authorize('manageMembers', $workspace);

        $validated = $request->validate([
            'role' => 'required|string|in:admin,member,viewer',
        ]);

        $member = $workspace->members()->where('users.id', $memberId)->first();

        if (! $member) {
            abort(404, 'Member not found in this workspace.');
        }

        if ($member->pivot->role === WorkspaceRole::Owner->value) {
            return back()->withErrors(['role' => 'Cannot change the owner\'s role.']);
        }

        $workspace->members()->updateExistingPivot($memberId, ['role' => $validated['role']]);

        return back()->with('success', 'Member role updated successfully.');
    }

    public function destroy(Request $request, int $memberId)
    {
        $workspace = $request->attributes->get('workspace');

        $this->authorize('manageMembers', $workspace);

        $member = $workspace->members()->where('users.id', $memberId)->first();

        if (! $member) {
            abort(404, 'Member not found in this workspace.');
        }

        if ($member->pivot->role === WorkspaceRole::Owner->value) {
            return back()->withErrors(['member' => 'Cannot remove the workspace owner.']);
        }

        $workspace->members()->detach($memberId);

        $user = User::find($memberId);
        if ($user && $user->current_workspace_id === $workspace->id) {
            $otherWorkspace = $user->workspaces()->first();
            $user->update(['current_workspace_id' => $otherWorkspace?->id]);
        }

        return back()->with('success', 'Member removed from workspace.');
    }
}
