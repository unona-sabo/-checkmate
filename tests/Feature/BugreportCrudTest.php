<?php

use App\Models\Bugreport;
use App\Models\Project;
use App\Models\ProjectFeature;
use App\Models\User;
use App\Models\Workspace;

test('index page renders with bugreports for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Bugreport::factory()->count(3)->create([
        'project_id' => $project->id,
        'reported_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('bugreports.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Bugreports/Index')
        ->has('project')
        ->has('bugreports', 3)
        ->has('availableFeatures')
    );
});

test('index page includes linked features on bugreports and available features for filtering', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
    ]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $user->id,
    ]);
    $bugreport->projectFeatures()->attach($feature->id);

    $response = $this->actingAs($user)->get(route('bugreports.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Bugreports/Index')
        ->has('bugreports', 1, fn ($bug) => $bug
            ->has('project_features', 1)
            ->etc()
        )
        ->has('availableFeatures', 1, fn ($f) => $f
            ->where('id', $feature->id)
            ->where('name', $feature->name)
            ->etc()
        )
    );
});

test('store creates bugreport with valid data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('bugreports.store', $project), [
        'title' => 'Login button broken',
        'description' => 'The login button does not respond to clicks',
        'steps_to_reproduce' => 'Click the login button',
        'expected_result' => 'User should be logged in',
        'actual_result' => 'Nothing happens',
        'severity' => 'major',
        'priority' => 'high',
        'status' => 'new',
        'environment' => 'Production',
        'fixed_on' => ['develop', 'staging'],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('bugreports', [
        'project_id' => $project->id,
        'title' => 'Login button broken',
        'severity' => 'major',
        'priority' => 'high',
        'status' => 'new',
        'reported_by' => $user->id,
    ]);

    $bugreport = Bugreport::where('title', 'Login button broken')->first();
    expect($bugreport->fixed_on)->toBe(['develop', 'staging']);
});

test('update modifies existing bugreport', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->put(route('bugreports.update', [$project, $bugreport]), [
        'title' => 'Updated Bug Title',
        'description' => 'Updated description',
        'severity' => 'critical',
        'priority' => 'high',
        'status' => 'in_progress',
        'fixed_on' => ['production'],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('bugreports', [
        'id' => $bugreport->id,
        'title' => 'Updated Bug Title',
        'severity' => 'critical',
        'status' => 'in_progress',
    ]);

    expect($bugreport->fresh()->fixed_on)->toBe(['production']);
});

test('destroy deletes bugreport', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->delete(route('bugreports.destroy', [$project, $bugreport]));

    $response->assertRedirect(route('bugreports.index', $project));

    $this->assertDatabaseMissing('bugreports', ['id' => $bugreport->id]);
});

test('viewer cannot store bugreport', function () {
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
        ->post(route('bugreports.store', $project), [
            'title' => 'Viewer Bug',
            'severity' => 'minor',
            'priority' => 'low',
            'status' => 'new',
        ])
        ->assertForbidden();
});

test('viewer cannot update bugreport', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $owner->id,
    ]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->put(route('bugreports.update', [$project, $bugreport]), [
            'title' => 'Updated',
            'severity' => 'minor',
            'priority' => 'low',
            'status' => 'new',
        ])
        ->assertForbidden();
});

test('viewer cannot destroy bugreport', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $owner->id,
    ]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->delete(route('bugreports.destroy', [$project, $bugreport]))
        ->assertForbidden();
});
