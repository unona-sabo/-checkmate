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

test('store from checklist persists expected_results per title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('test-runs.store-from-checklist', $project),
        [
            'name' => 'Expected Result Run',
            'checklist_id' => $checklist->id,
            'titles' => ['Login works', 'Signup works'],
            'expected_results' => [
                'Login works' => 'User is redirected to dashboard',
                'Signup works' => 'User receives confirmation email',
            ],
        ]
    );

    $response->assertRedirect();

    $testRun = TestRun::where('name', 'Expected Result Run')->first();
    $cases = $testRun->testRunCases()->orderBy('id')->get();

    expect($cases)->toHaveCount(2);
    expect($cases[0]->title)->toBe('Login works');
    expect($cases[0]->expected_result)->toBe('User is redirected to dashboard');
    expect($cases[1]->title)->toBe('Signup works');
    expect($cases[1]->expected_result)->toBe('User receives confirmation email');
});

test('store from checklist works without expected_results', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('test-runs.store-from-checklist', $project),
        [
            'name' => 'No Expected Run',
            'checklist_id' => $checklist->id,
            'titles' => ['Check item'],
        ]
    );

    $response->assertRedirect();

    $testRun = TestRun::where('name', 'No Expected Run')->first();
    expect($testRun->testRunCases->first()->expected_result)->toBeNull();
});

test('store from test cases accepts and persists priority', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = \App\Models\TestSuite::factory()->create(['project_id' => $project->id]);
    $testCase = \App\Models\TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $response = $this->actingAs($user)->post(
        route('test-runs.store', $project),
        [
            'name' => 'Priority Test Run',
            'description' => 'Testing priority',
            'environment' => 'Staging',
            'milestone' => 'v2.0',
            'priority' => 'high',
            'test_case_ids' => [$testCase->id],
        ]
    );

    $response->assertRedirect();

    $testRun = TestRun::where('name', 'Priority Test Run')->first();
    expect($testRun)->not->toBeNull();
    expect($testRun->priority)->toBe('high');
});

test('store from checklist accepts and persists priority', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('test-runs.store-from-checklist', $project),
        [
            'name' => 'Priority Checklist Run',
            'priority' => 'critical',
            'checklist_id' => $checklist->id,
            'titles' => ['Item 1'],
        ]
    );

    $response->assertRedirect();

    $testRun = TestRun::where('name', 'Priority Checklist Run')->first();
    expect($testRun)->not->toBeNull();
    expect($testRun->priority)->toBe('critical');
});

test('store accepts null priority', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = \App\Models\TestSuite::factory()->create(['project_id' => $project->id]);
    $testCase = \App\Models\TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $response = $this->actingAs($user)->post(
        route('test-runs.store', $project),
        [
            'name' => 'No Priority Run',
            'test_case_ids' => [$testCase->id],
        ]
    );

    $response->assertRedirect();

    $testRun = TestRun::where('name', 'No Priority Run')->first();
    expect($testRun)->not->toBeNull();
    expect($testRun->priority)->toBeNull();
});

test('store rejects invalid priority value', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = \App\Models\TestSuite::factory()->create(['project_id' => $project->id]);
    $testCase = \App\Models\TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $response = $this->actingAs($user)->post(
        route('test-runs.store', $project),
        [
            'name' => 'Bad Priority Run',
            'priority' => 'urgent',
            'test_case_ids' => [$testCase->id],
        ]
    );

    $response->assertSessionHasErrors('priority');
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
        ->has('testRuns')
        ->loadDeferredProps(fn ($page) => $page
            ->has('users')
        )
    );
});
