<?php

use App\Models\Checklist;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

test('index page renders with checklists for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Checklist::factory()->count(3)->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('checklists.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Checklists/Index')
        ->has('project')
        ->has('checklists', 3)
    );
});

test('store creates checklist with valid data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('checklists.store', $project), [
        'name' => 'Deployment Checklist',
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Done', 'type' => 'checkbox'],
        ],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('checklists', [
        'project_id' => $project->id,
        'name' => 'Deployment Checklist',
    ]);
});

test('update modifies existing checklist', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put(route('checklists.update', [$project, $checklist]), [
        'name' => 'Updated Checklist Name',
        'columns_config' => [
            ['key' => 'item', 'label' => 'Task', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Complete', 'type' => 'checkbox'],
        ],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('checklists', [
        'id' => $checklist->id,
        'name' => 'Updated Checklist Name',
    ]);
});

test('destroy deletes checklist', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->delete(route('checklists.destroy', [$project, $checklist]));

    $response->assertRedirect(route('checklists.index', $project));

    $this->assertDatabaseMissing('checklists', ['id' => $checklist->id]);
});

test('viewer cannot store checklist', function () {
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
        ->post(route('checklists.store', $project), [
            'name' => 'Viewer Checklist',
        ])
        ->assertForbidden();
});

test('viewer cannot update checklist', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->put(route('checklists.update', [$project, $checklist]), [
            'name' => 'Updated',
        ])
        ->assertForbidden();
});

test('viewer cannot destroy checklist', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->delete(route('checklists.destroy', [$project, $checklist]))
        ->assertForbidden();
});
