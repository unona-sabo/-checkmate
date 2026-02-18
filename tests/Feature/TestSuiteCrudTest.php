<?php

use App\Models\Project;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;

test('index page renders with test suites for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    TestSuite::factory()->count(3)->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('test-suites.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('TestSuites/Index')
        ->has('project')
        ->has('testSuites', 3)
    );
});

test('store creates test suite with valid data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('test-suites.store', $project), [
        'name' => 'Authentication Tests',
        'description' => 'Tests for the authentication module',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_suites', [
        'project_id' => $project->id,
        'name' => 'Authentication Tests',
        'description' => 'Tests for the authentication module',
    ]);
});

test('update modifies existing test suite', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $testSuite = TestSuite::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put(route('test-suites.update', [$project, $testSuite]), [
        'name' => 'Updated Suite Name',
        'description' => 'Updated description',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_suites', [
        'id' => $testSuite->id,
        'name' => 'Updated Suite Name',
        'description' => 'Updated description',
    ]);
});

test('destroy deletes test suite', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $testSuite = TestSuite::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->delete(route('test-suites.destroy', [$project, $testSuite]));

    $response->assertRedirect(route('test-suites.index', $project));

    $this->assertDatabaseMissing('test_suites', ['id' => $testSuite->id]);
});

test('viewer cannot store test suite', function () {
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
        ->post(route('test-suites.store', $project), [
            'name' => 'Viewer Suite',
            'description' => 'Should not work',
        ])
        ->assertForbidden();
});

test('viewer cannot update test suite', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $testSuite = TestSuite::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->put(route('test-suites.update', [$project, $testSuite]), [
            'name' => 'Updated',
            'description' => 'Should not work',
        ])
        ->assertForbidden();
});

test('viewer cannot destroy test suite', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $testSuite = TestSuite::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->delete(route('test-suites.destroy', [$project, $testSuite]))
        ->assertForbidden();
});
