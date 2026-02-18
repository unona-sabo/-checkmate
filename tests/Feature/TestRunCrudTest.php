<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRun;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;

test('index page renders with test runs for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    TestRun::factory()->count(3)->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('test-runs.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('TestRuns/Index')
        ->has('project')
        ->has('testRuns', 3)
    );
});

test('store creates test run with valid data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $testSuite = TestSuite::factory()->create(['project_id' => $project->id]);
    $testCases = TestCase::factory()->count(3)->create(['test_suite_id' => $testSuite->id]);

    $response = $this->actingAs($user)->post(route('test-runs.store', $project), [
        'name' => 'Sprint 1 Regression',
        'description' => 'Regression testing for Sprint 1',
        'environment' => 'Staging',
        'milestone' => 'v1.0',
        'test_case_ids' => $testCases->pluck('id')->toArray(),
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_runs', [
        'project_id' => $project->id,
        'name' => 'Sprint 1 Regression',
        'description' => 'Regression testing for Sprint 1',
        'environment' => 'Staging',
        'milestone' => 'v1.0',
        'status' => 'active',
        'created_by' => $user->id,
    ]);
});

test('update modifies existing test run', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $testRun = TestRun::factory()->active()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->put(route('test-runs.update', [$project, $testRun]), [
        'name' => 'Updated Test Run',
        'description' => 'Updated description',
        'environment' => 'Production',
        'milestone' => 'v2.0',
        'status' => 'active',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_runs', [
        'id' => $testRun->id,
        'name' => 'Updated Test Run',
        'description' => 'Updated description',
        'environment' => 'Production',
        'milestone' => 'v2.0',
    ]);
});

test('destroy deletes test run', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $testRun = TestRun::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->delete(route('test-runs.destroy', [$project, $testRun]));

    $response->assertRedirect(route('test-runs.index', $project));

    $this->assertDatabaseMissing('test_runs', ['id' => $testRun->id]);
});

test('viewer cannot store test run', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $testSuite = TestSuite::factory()->create(['project_id' => $project->id]);
    $testCases = TestCase::factory()->count(2)->create(['test_suite_id' => $testSuite->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->post(route('test-runs.store', $project), [
            'name' => 'Viewer Run',
            'test_case_ids' => $testCases->pluck('id')->toArray(),
        ])
        ->assertForbidden();
});

test('viewer cannot update test run', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->put(route('test-runs.update', [$project, $testRun]), [
            'name' => 'Updated',
            'status' => 'active',
        ])
        ->assertForbidden();
});

test('viewer cannot destroy test run', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $testRun = TestRun::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->delete(route('test-runs.destroy', [$project, $testRun]))
        ->assertForbidden();
});
