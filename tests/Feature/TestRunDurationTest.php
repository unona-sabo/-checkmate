<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRun;
use App\Models\TestRunCase;
use App\Models\TestSuite;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->testSuite = TestSuite::factory()->create(['project_id' => $this->project->id]);
    $this->testCase = TestCase::factory()->create(['test_suite_id' => $this->testSuite->id]);
});

test('creating a test run does not set started_at', function () {
    $response = $this->actingAs($this->user)->post(
        route('test-runs.store', $this->project),
        [
            'name' => 'My Test Run',
            'description' => null,
            'environment' => 'Staging',
            'milestone' => null,
            'test_case_ids' => [$this->testCase->id],
        ]
    );

    $response->assertRedirect();

    $testRun = TestRun::latest()->first();
    expect($testRun->started_at)->toBeNull();
    expect($testRun->status)->toBe('active');
});

test('updating a test case status from untested sets started_at on the run', function () {
    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
        'started_at' => null,
    ]);

    $testRunCase = TestRunCase::create([
        'test_run_id' => $testRun->id,
        'test_case_id' => $this->testCase->id,
        'status' => 'untested',
    ]);

    $this->actingAs($this->user)->put(
        route('test-run-cases.update', [$this->project, $testRun, $testRunCase]),
        ['status' => 'passed']
    );

    $testRun->refresh();
    expect($testRun->started_at)->not->toBeNull();
});

test('updating a test case status does not overwrite existing started_at', function () {
    $originalStartedAt = now()->subHour();

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
        'started_at' => $originalStartedAt,
    ]);

    $testRunCase = TestRunCase::create([
        'test_run_id' => $testRun->id,
        'test_case_id' => $this->testCase->id,
        'status' => 'untested',
    ]);

    $this->actingAs($this->user)->put(
        route('test-run-cases.update', [$this->project, $testRun, $testRunCase]),
        ['status' => 'failed']
    );

    $testRun->refresh();
    expect($testRun->started_at->timestamp)->toBe($originalStartedAt->timestamp);
});

test('bulk updating test cases sets started_at when null', function () {
    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
        'started_at' => null,
    ]);

    $testRunCase = TestRunCase::create([
        'test_run_id' => $testRun->id,
        'test_case_id' => $this->testCase->id,
        'status' => 'untested',
    ]);

    $this->actingAs($this->user)->post(
        route('test-run-cases.bulk-update', [$this->project, $testRun]),
        [
            'test_run_case_ids' => [$testRunCase->id],
            'status' => 'passed',
        ]
    );

    $testRun->refresh();
    expect($testRun->started_at)->not->toBeNull();
});

test('completing a test run sets completed_by', function () {
    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
    ]);

    $this->actingAs($this->user)->post(
        route('test-runs.complete', [$this->project, $testRun])
    );

    $testRun->refresh();
    expect($testRun->completed_at)->not->toBeNull();
    expect($testRun->completed_by)->toBe($this->user->id);
});

test('updating test run status to completed sets completed_by', function () {
    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
    ]);

    $this->actingAs($this->user)->put(
        route('test-runs.update', [$this->project, $testRun]),
        [
            'name' => $testRun->name,
            'description' => $testRun->description,
            'environment' => $testRun->environment,
            'milestone' => $testRun->milestone,
            'status' => 'completed',
        ]
    );

    $testRun->refresh();
    expect($testRun->completed_at)->not->toBeNull();
    expect($testRun->completed_by)->toBe($this->user->id);
});

test('test runs index eager loads completed_by_user', function () {
    $completer = User::factory()->create(['name' => 'Jane Doe']);

    TestRun::factory()->create([
        'project_id' => $this->project->id,
        'status' => 'completed',
        'completed_at' => now(),
        'completed_by' => $completer->id,
    ]);

    $response = $this->actingAs($this->user)->get(
        route('test-runs.index', $this->project)
    );

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('TestRuns/Index')
        ->has('testRuns', 1)
        ->where('testRuns.0.completed_by_user.name', 'Jane Doe')
    );
});
