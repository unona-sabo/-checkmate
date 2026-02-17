<?php

use App\Models\Checklist;
use App\Models\Project;
use App\Models\TestRun;
use App\Models\TestRunCase;
use App\Models\User;
use App\Models\Workspace;

test('store from checklist creates run with source and cases with titles', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('test-runs.store-from-checklist', $project),
        [
            'name' => 'Checklist Test Run',
            'description' => 'From checklist',
            'environment' => 'Staging',
            'milestone' => 'v1.0',
            'checklist_id' => $checklist->id,
            'titles' => ['Check login form', 'Check signup form', 'Check password reset'],
        ]
    );

    $response->assertRedirect();

    $testRun = TestRun::where('name', 'Checklist Test Run')->first();
    expect($testRun)->not->toBeNull();
    expect($testRun->source)->toBe('checklist');
    expect($testRun->checklist_id)->toBe($checklist->id);
    expect($testRun->created_by)->toBe($user->id);
    expect($testRun->status)->toBe('active');

    $cases = $testRun->testRunCases;
    expect($cases)->toHaveCount(3);
    expect($cases[0]->title)->toBe('Check login form');
    expect($cases[0]->test_case_id)->toBeNull();
    expect($cases[0]->status)->toBe('untested');
    expect($cases[1]->title)->toBe('Check signup form');
    expect($cases[2]->title)->toBe('Check password reset');
});

test('store from checklist validates required fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(
        route('test-runs.store-from-checklist', $project),
        []
    );

    $response->assertSessionHasErrors(['name', 'checklist_id', 'titles']);
});

test('store from checklist validates non-existent checklist_id', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(
        route('test-runs.store-from-checklist', $project),
        [
            'name' => 'Test Run',
            'checklist_id' => 99999,
            'titles' => ['Item 1'],
        ]
    );

    $response->assertSessionHasErrors('checklist_id');
});

test('viewer cannot create test run from checklist', function () {
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

    $response = $this->actingAs($viewer)->post(
        route('test-runs.store-from-checklist', $project),
        [
            'name' => 'Viewer Test Run',
            'checklist_id' => $checklist->id,
            'titles' => ['Item 1'],
        ]
    );

    $response->assertForbidden();
});

test('show page renders with checklist-sourced cases', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $project->id,
        'source' => 'checklist',
        'checklist_id' => $checklist->id,
        'created_by' => $user->id,
    ]);

    TestRunCase::factory()->create([
        'test_run_id' => $testRun->id,
        'test_case_id' => null,
        'title' => 'Check item from checklist',
        'status' => 'untested',
    ]);

    $response = $this->actingAs($user)->get(
        route('test-runs.show', [$project, $testRun])
    );

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('TestRuns/Show')
        ->where('testRun.source', 'checklist')
        ->where('testRun.checklist.id', $checklist->id)
        ->where('testRun.checklist.name', $checklist->name)
        ->has('testRun.test_run_cases', 1)
        ->where('testRun.test_run_cases.0.title', 'Check item from checklist')
        ->where('testRun.test_run_cases.0.test_case_id', null)
    );
});

test('index page passes users prop', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestRun::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(
        route('test-runs.index', $project)
    );

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('TestRuns/Index')
        ->has('users')
        ->has('testRuns')
    );
});
