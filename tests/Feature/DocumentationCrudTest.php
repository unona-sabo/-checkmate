<?php

use App\Models\Documentation;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

test('index page renders with documentations for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Documentation::factory()->count(3)->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('documentations.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documentations/Index')
        ->has('project')
        ->has('documentations', 3)
    );
});

test('store creates documentation with valid data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('documentations.store', $project), [
        'title' => 'API Documentation',
        'content' => 'This is the API documentation content.',
        'category' => 'API',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('documentations', [
        'project_id' => $project->id,
        'title' => 'API Documentation',
        'content' => 'This is the API documentation content.',
        'category' => 'API',
    ]);
});

test('update modifies existing documentation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $documentation = Documentation::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put(route('documentations.update', [$project, $documentation]), [
        'title' => 'Updated Documentation Title',
        'content' => 'Updated documentation content.',
        'category' => 'Frontend',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('documentations', [
        'id' => $documentation->id,
        'title' => 'Updated Documentation Title',
        'content' => 'Updated documentation content.',
        'category' => 'Frontend',
    ]);
});

test('destroy deletes documentation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $documentation = Documentation::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->delete(route('documentations.destroy', [$project, $documentation]));

    $response->assertRedirect(route('documentations.index', $project));

    $this->assertDatabaseMissing('documentations', ['id' => $documentation->id]);
});

test('viewer cannot store documentation', function () {
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
        ->post(route('documentations.store', $project), [
            'title' => 'Viewer Doc',
            'content' => 'Should not work',
        ])
        ->assertForbidden();
});

test('viewer cannot update documentation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $documentation = Documentation::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->put(route('documentations.update', [$project, $documentation]), [
            'title' => 'Updated',
            'content' => 'Should not work',
        ])
        ->assertForbidden();
});

test('viewer cannot destroy documentation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $documentation = Documentation::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->delete(route('documentations.destroy', [$project, $documentation]))
        ->assertForbidden();
});
