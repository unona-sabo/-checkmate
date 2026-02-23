<?php

use App\Models\User;
use App\Models\Workspace;

test('owner can transfer ownership to a member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);
    $owner->update(['current_workspace_id' => $workspace->id]);

    $member = User::factory()->create();
    $workspace->members()->attach($member->id, ['role' => 'member']);
    $member->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->put('/workspaces/transfer', ['new_owner_id' => $member->id])
        ->assertRedirect();

    $workspace->refresh();
    expect($workspace->owner_id)->toBe($member->id);

    $newOwnerPivot = $workspace->members()->where('users.id', $member->id)->first()->pivot;
    expect($newOwnerPivot->role)->toBe('owner');

    $oldOwnerPivot = $workspace->members()->where('users.id', $owner->id)->first()->pivot;
    expect($oldOwnerPivot->role)->toBe('admin');
});

test('non-owner cannot transfer ownership', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $admin = User::factory()->create();
    $workspace->members()->attach($admin->id, ['role' => 'admin']);
    $admin->update(['current_workspace_id' => $workspace->id]);

    $member = User::factory()->create();
    $workspace->members()->attach($member->id, ['role' => 'member']);

    $this->actingAs($admin)
        ->put('/workspaces/transfer', ['new_owner_id' => $member->id])
        ->assertForbidden();
});

test('cannot transfer ownership to a non-member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);
    $owner->update(['current_workspace_id' => $workspace->id]);

    $nonMember = User::factory()->create();

    $this->actingAs($owner)
        ->put('/workspaces/transfer', ['new_owner_id' => $nonMember->id])
        ->assertSessionHasErrors('new_owner_id');
});

test('cannot transfer ownership to a nonexistent user', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);
    $owner->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->put('/workspaces/transfer', ['new_owner_id' => 99999])
        ->assertSessionHasErrors('new_owner_id');
});
