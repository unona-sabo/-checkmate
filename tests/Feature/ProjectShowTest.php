<?php

use App\Models\Bugreport;
use App\Models\Documentation;
use App\Models\Project;
use App\Models\User;

test('project show page loads bugreports and documentations', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $bugreports = Bugreport::factory()->count(3)->create([
        'project_id' => $project->id,
        'reported_by' => $user->id,
    ]);

    $documentations = Documentation::factory()->count(2)->create([
        'project_id' => $project->id,
    ]);

    $response = $this->actingAs($user)->get(route('projects.show', $project));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Show')
        ->has('project.bugreports', 3)
        ->has('project.documentations', 2)
        ->has('project.bugreports.0.title')
        ->has('project.bugreports.0.status')
        ->has('project.documentations.0.title')
    );
});

test('project show page limits bugreports to 5', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Bugreport::factory()->count(8)->create([
        'project_id' => $project->id,
        'reported_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('projects.show', $project));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Show')
        ->has('project.bugreports', 5)
    );
});

test('project show page limits documentations to 5 and filters by parent_id null', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // Create 7 root-level documentations
    Documentation::factory()->count(7)->create([
        'project_id' => $project->id,
        'parent_id' => null,
    ]);

    // Create a child documentation (should not appear)
    $parent = Documentation::where('project_id', $project->id)->first();
    Documentation::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parent->id,
    ]);

    $response = $this->actingAs($user)->get(route('projects.show', $project));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Show')
        ->has('project.documentations', 5)
    );
});
