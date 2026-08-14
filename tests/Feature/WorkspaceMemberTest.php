<?php

use App\Models\User;
use App\Models\Workspace;

test('owner can add a member to workspace', function () {
    [$owner, $workspace] = createUserWithWorkspace();

    $newUser = User::factory()->create();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->getRouteKey()}/members", [
            'email' => $newUser->email,
            'role' => 'member',
        ])
        ->assertRedirect();

    expect($workspace->members()->where('users.id', $newUser->id)->exists())->toBeTrue();
});

test('member cannot add members to workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $member = User::factory()->create();
    $workspace->members()->attach($member->id, ['role' => 'member']);
    $member->update(['current_workspace_id' => $workspace->id]);

    $newUser = User::factory()->create();

    $this->actingAs($member)
        ->post("/workspaces/{$workspace->getRouteKey()}/members", [
            'email' => $newUser->email,
            'role' => 'viewer',
        ])
        ->assertForbidden();
});

test('cannot add a nonexistent email as member', function () {
    [$owner, $workspace] = createUserWithWorkspace();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->getRouteKey()}/members", [
            'email' => 'nonexistent@example.com',
            'role' => 'member',
        ])
        ->assertSessionHasErrors('email');
});

test('owner can remove a member from workspace', function () {
    [$owner, $workspace] = createUserWithWorkspace();

    $member = User::factory()->create();
    $workspace->members()->attach($member->id, ['role' => 'member']);
    $member->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->getRouteKey()}/members/{$member->id}")
        ->assertRedirect();

    expect($workspace->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('cannot remove the workspace owner', function () {
    [$owner, $workspace] = createUserWithWorkspace();

    // Create an admin who tries to remove the owner
    $admin = User::factory()->create();
    $workspace->members()->attach($admin->id, ['role' => 'admin']);
    $admin->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->delete("/workspaces/{$workspace->getRouteKey()}/members/{$owner->id}")
        ->assertSessionHasErrors('member');
});

test('owner can update a member role', function () {
    [$owner, $workspace] = createUserWithWorkspace();

    $member = User::factory()->create();
    $workspace->members()->attach($member->id, ['role' => 'member']);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->getRouteKey()}/members/{$member->id}", ['role' => 'admin'])
        ->assertRedirect();

    $updatedRole = $workspace->members()->where('users.id', $member->id)->first()->pivot->role;
    expect($updatedRole)->toBe('admin');
});

test('workspace settings page loads for owner', function () {
    [$owner, $workspace] = createUserWithWorkspace();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->getRouteKey()}/settings")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Workspaces/Show')
            ->has('workspace')
            ->has('members')
            ->has('roles')
        );
});

test('workspace settings page loads for admin', function () {
    [$user, $workspace] = createUserWithWorkspace('admin');

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->getRouteKey()}/settings")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Workspaces/Show'));
});

test('member cannot view workspace settings page', function () {
    [$user, $workspace] = createUserWithWorkspace('member');

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->getRouteKey()}/settings")
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('error');
});

test('viewer cannot view workspace settings page', function () {
    [$user, $workspace] = createUserWithWorkspace('viewer');

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->getRouteKey()}/settings")
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('error');
});
