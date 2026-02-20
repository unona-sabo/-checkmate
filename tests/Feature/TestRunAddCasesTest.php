<?php

use App\Models\Checklist;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRun;
use App\Models\TestRunCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;

test('add test cases to active test-cases-sourced run', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $tc1 = TestCase::factory()->create(['test_suite_id' => $suite->id]);
    $tc2 = TestCase::factory()->create(['test_suite_id' => $suite->id]);
    $tc3 = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $project->id,
        'source' => 'test-cases',
        'created_by' => $user->id,
    ]);

    TestRunCase::factory()->create([
        'test_run_id' => $testRun->id,
        'test_case_id' => $tc1->id,
        'status' => 'untested',
    ]);

    $response = $this->actingAs($user)->post(
        route('test-runs.add-cases', [$project, $testRun]),
        ['test_case_ids' => [$tc1->id, $tc2->id, $tc3->id]]
    );

    $response->assertRedirect();

    $testRun->refresh();
    expect($testRun->testRunCases)->toHaveCount(3);

    // tc1 should not be duplicated
    expect($testRun->testRunCases->where('test_case_id', $tc1->id))->toHaveCount(1);
    expect($testRun->testRunCases->where('test_case_id', $tc2->id))->toHaveCount(1);
    expect($testRun->testRunCases->where('test_case_id', $tc3->id))->toHaveCount(1);
});

test('add titles to active checklist-sourced run', function () {
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
        'title' => 'Existing row',
        'status' => 'untested',
    ]);

    $response = $this->actingAs($user)->post(
        route('test-runs.add-cases', [$project, $testRun]),
        ['titles' => ['Existing row', 'New row 1', 'New row 2']]
    );

    $response->assertRedirect();

    $testRun->refresh();
    expect($testRun->testRunCases)->toHaveCount(3);
    expect($testRun->testRunCases->where('title', 'Existing row'))->toHaveCount(1);
});

test('cannot add cases to completed test run', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $tc = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $testRun = TestRun::factory()->create([
        'project_id' => $project->id,
        'source' => 'test-cases',
        'status' => 'completed',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(
        route('test-runs.add-cases', [$project, $testRun]),
        ['test_case_ids' => [$tc->id]]
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($testRun->testRunCases)->toHaveCount(0);
});

test('add cases validates required fields for test-cases source', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $project->id,
        'source' => 'test-cases',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(
        route('test-runs.add-cases', [$project, $testRun]),
        []
    );

    $response->assertSessionHasErrors('test_case_ids');
});

test('add cases validates required fields for checklist source', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $project->id,
        'source' => 'checklist',
        'checklist_id' => $checklist->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(
        route('test-runs.add-cases', [$project, $testRun]),
        []
    );

    $response->assertSessionHasErrors('titles');
});

test('viewer cannot add cases to test run', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $tc = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $project->id,
        'source' => 'test-cases',
        'created_by' => $owner->id,
    ]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($viewer)->post(
        route('test-runs.add-cases', [$project, $testRun]),
        ['test_case_ids' => [$tc->id]]
    );

    $response->assertForbidden();
});

test('show page passes testSuites for test-cases source', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id, 'parent_id' => null]);

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $project->id,
        'source' => 'test-cases',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(
        route('test-runs.show', [$project, $testRun])
    );

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('TestRuns/Show')
        ->loadDeferredProps(fn ($page) => $page
            ->has('testSuites')
        )
    );
});

test('show page passes checklists for checklist source', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $project->id,
        'source' => 'checklist',
        'checklist_id' => $checklist->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(
        route('test-runs.show', [$project, $testRun])
    );

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('TestRuns/Show')
        ->loadDeferredProps(fn ($page) => $page
            ->has('checklists', 1)
        )
    );
});

test('create page with checklist source passes checklists', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Checklist::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(
        route('test-runs.create', $project).'?source=checklist'
    );

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('TestRuns/Create')
        ->where('source', 'checklist')
        ->has('checklists', 1)
    );
});

test('stats and progress are updated after adding cases', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $tc1 = TestCase::factory()->create(['test_suite_id' => $suite->id]);
    $tc2 = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $project->id,
        'source' => 'test-cases',
        'created_by' => $user->id,
    ]);

    // Add first case and mark as passed
    TestRunCase::factory()->create([
        'test_run_id' => $testRun->id,
        'test_case_id' => $tc1->id,
        'status' => 'passed',
    ]);

    $this->actingAs($user)->post(
        route('test-runs.add-cases', [$project, $testRun]),
        ['test_case_ids' => [$tc2->id]]
    );

    $testRun->refresh();
    expect($testRun->stats)->toBe(['passed' => 1, 'untested' => 1]);
    expect($testRun->progress)->toBe(50);
});
