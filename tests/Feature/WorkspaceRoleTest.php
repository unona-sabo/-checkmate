<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

test('viewer cannot create a project', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->post('/projects', ['name' => 'Viewer Project'])
        ->assertForbidden();
});

test('member can create a project', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $member = User::factory()->create();
    $workspace->members()->attach($member->id, ['role' => 'member']);
    $member->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($member)
        ->post('/projects', ['name' => 'Member Project'])
        ->assertRedirect();

    expect(Project::where('name', 'Member Project')->exists())->toBeTrue();
});

test('viewer can view a workspace project', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->get(route('projects.show', $project))
        ->assertSuccessful();
});

test('admin can delete a workspace project', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $admin = User::factory()->create();
    $workspace->members()->attach($admin->id, ['role' => 'admin']);
    $admin->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect();

    expect(Project::find($project->id))->toBeNull();
});

test('member cannot delete a workspace project', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $member = User::factory()->create();
    $workspace->members()->attach($member->id, ['role' => 'member']);
    $member->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($member)
        ->delete(route('projects.destroy', $project))
        ->assertForbidden();
});
