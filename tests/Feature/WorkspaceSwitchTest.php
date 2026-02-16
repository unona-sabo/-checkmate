<?php

use App\Models\Workspace;

test('user can switch between workspaces', function () {
    [$user, $workspace1] = createUserWithWorkspace();

    $workspace2 = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace2->members()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->post('/workspaces/switch', ['workspace_id' => $workspace2->id])
        ->assertRedirect();

    expect($user->fresh()->current_workspace_id)->toBe($workspace2->id);
});

test('user cannot switch to a workspace they are not a member of', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $otherWorkspace = Workspace::factory()->create();

    $this->actingAs($user)
        ->post('/workspaces/switch', ['workspace_id' => $otherWorkspace->id])
        ->assertForbidden();
});

test('user can create a new workspace and switch to it', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $this->actingAs($user)
        ->post('/workspaces', ['name' => 'New Workspace'])
        ->assertRedirect();

    $newWorkspace = Workspace::where('name', 'New Workspace')->first();
    expect($newWorkspace)->not->toBeNull();
    expect($user->fresh()->current_workspace_id)->toBe($newWorkspace->id);
    expect($newWorkspace->members()->where('users.id', $user->id)->first()->pivot->role)->toBe('owner');
});
