<?php

use App\Models\Project;
use App\Models\Workspace;

test('project index shows only current workspace projects', function () {
    [$user, $workspace1] = createUserWithWorkspace();

    $project1 = Project::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace1->id,
        'name' => 'WS1 Project',
    ]);

    $workspace2 = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace2->members()->attach($user->id, ['role' => 'owner']);

    $project2 = Project::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace2->id,
        'name' => 'WS2 Project',
    ]);

    $response = $this->actingAs($user)->get('/projects');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Index')
        ->has('projects', 1)
        ->where('projects.0.name', 'WS1 Project')
    );
});

test('creating a project assigns it to the current workspace', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $this->actingAs($user)
        ->post('/projects', ['name' => 'New Project'])
        ->assertRedirect();

    $project = Project::where('name', 'New Project')->first();
    expect($project->workspace_id)->toBe($workspace->id);
    expect($project->user_id)->toBe($user->id);
});
