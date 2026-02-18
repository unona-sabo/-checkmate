<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;

test('bulk delete removes test cases belonging to project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $cases = TestCase::factory()->count(3)->create(['test_suite_id' => $suite->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-destroy-cases', $project),
        ['test_case_ids' => $cases->pluck('id')->toArray()]
    );

    $response->assertRedirect();
    expect(TestCase::whereIn('id', $cases->pluck('id'))->count())->toBe(0);
});

test('bulk delete only deletes cases from project suites', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $ownCase = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    // Another project's case
    $otherProject = Project::factory()->create(['user_id' => $user->id]);
    $otherSuite = TestSuite::factory()->create(['project_id' => $otherProject->id]);
    $otherCase = TestCase::factory()->create(['test_suite_id' => $otherSuite->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-destroy-cases', $project),
        ['test_case_ids' => [$ownCase->id, $otherCase->id]]
    );

    $response->assertRedirect();
    // Own case deleted, other project's case remains
    expect(TestCase::find($ownCase->id))->toBeNull();
    expect(TestCase::find($otherCase->id))->not->toBeNull();
});

test('bulk copy replicates test cases to target suite', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $sourceSuite = TestSuite::factory()->create(['project_id' => $project->id]);
    $targetSuite = TestSuite::factory()->create(['project_id' => $project->id]);
    $cases = TestCase::factory()->count(2)->create(['test_suite_id' => $sourceSuite->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-copy-cases', $project),
        [
            'test_case_ids' => $cases->pluck('id')->toArray(),
            'target_suite_id' => $targetSuite->id,
        ]
    );

    $response->assertRedirect();
    // Original cases still exist
    expect(TestCase::whereIn('id', $cases->pluck('id'))->count())->toBe(2);
    // Copies exist in target suite
    expect(TestCase::where('test_suite_id', $targetSuite->id)->count())->toBe(2);
});

test('bulk copy rejects target suite from another project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $otherProject = Project::factory()->create(['user_id' => $user->id]);
    $otherSuite = TestSuite::factory()->create(['project_id' => $otherProject->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-copy-cases', $project),
        [
            'test_case_ids' => [$case->id],
            'target_suite_id' => $otherSuite->id,
        ]
    );

    $response->assertSessionHasErrors('target_suite_id');
});

test('viewer cannot bulk delete test cases', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($viewer)->post(
        route('test-suites.bulk-destroy-cases', $project),
        ['test_case_ids' => [$case->id]]
    );

    $response->assertForbidden();
});

test('viewer cannot bulk copy test cases', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);
    $targetSuite = TestSuite::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($viewer)->post(
        route('test-suites.bulk-copy-cases', $project),
        [
            'test_case_ids' => [$case->id],
            'target_suite_id' => $targetSuite->id,
        ]
    );

    $response->assertForbidden();
});

test('bulk delete validates test_case_ids required', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-destroy-cases', $project),
        []
    );

    $response->assertSessionHasErrors('test_case_ids');
});

test('bulk copy validates target_suite_id required', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-copy-cases', $project),
        ['test_case_ids' => [$case->id]]
    );

    $response->assertSessionHasErrors('target_suite_id');
});
