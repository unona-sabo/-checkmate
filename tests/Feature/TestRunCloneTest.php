<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRun;
use App\Models\TestRunCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;

test('duplicating a test run copies cases with statuses reset to untested', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $testCase = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $testRun = TestRun::factory()->completed()->create([
        'project_id' => $project->id,
        'name' => 'Regression Run',
        'environment' => 'Staging',
    ]);

    $case = TestRunCase::create([
        'test_run_id' => $testRun->id,
        'test_case_id' => $testCase->id,
        'title' => $testCase->title,
        'status' => 'failed',
        'actual_result' => 'Broke on step 3',
        'time_spent' => 20,
    ]);

    $response = $this->actingAs($user)->post(
        route('test-runs.clone', [$project, $testRun])
    );

    $newRun = TestRun::where('id', '!=', $testRun->id)->where('project_id', $project->id)->first();
    expect($newRun)->not->toBeNull();
    $response->assertRedirect(route('test-runs.show', [$project, $newRun]));

    expect($newRun->name)->toBe('Duplicate of Regression Run');
    expect($newRun->environment)->toBe('Staging');
    expect($newRun->status)->toBe('active');
    expect($newRun->started_at)->toBeNull();
    expect($newRun->completed_at)->toBeNull();
    expect($newRun->duplicated_from_id)->toBe($testRun->id);

    $newCase = $newRun->testRunCases()->first();
    expect($newCase->test_case_id)->toBe($testCase->id);
    expect($newCase->status)->toBe('untested');
    expect($newCase->actual_result)->toBeNull();
    expect($newCase->time_spent)->toBeNull();

    // The original run and its case are untouched.
    expect($case->fresh()->status)->toBe('failed');
});

test('viewer cannot duplicate a test run', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);
    $testRun = TestRun::factory()->active()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($viewer)->post(
        route('test-runs.clone', [$project, $testRun])
    );

    $response->assertForbidden();
});
