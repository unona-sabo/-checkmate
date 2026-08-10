<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;

test('archiving into a brand-new archive suite moves cases and records their original suite', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $caseOne = TestCase::factory()->create(['test_suite_id' => $suite->id]);
    $caseTwo = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $response = $this->actingAs($user)->post(route('test-suites.archive-cases', $project), [
        'test_case_ids' => [$caseOne->id, $caseTwo->id],
        'archive_suite_name' => 'My Archive',
    ]);

    $response->assertRedirect();

    $archiveSuite = TestSuite::where('project_id', $project->id)->where('is_archived', true)->first();

    expect($archiveSuite)->not->toBeNull();
    expect($archiveSuite->name)->toBe('My Archive');

    $this->assertDatabaseHas('test_cases', [
        'id' => $caseOne->id,
        'test_suite_id' => $archiveSuite->id,
        'archived_from_suite_id' => $suite->id,
    ]);
    $this->assertDatabaseHas('test_cases', [
        'id' => $caseTwo->id,
        'test_suite_id' => $archiveSuite->id,
        'archived_from_suite_id' => $suite->id,
    ]);
});

test('archiving into an existing archive suite appends after the current max order', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $archiveSuite = TestSuite::factory()->create(['project_id' => $project->id, 'is_archived' => true]);
    TestCase::factory()->create(['test_suite_id' => $archiveSuite->id, 'order' => 5]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $response = $this->actingAs($user)->post(route('test-suites.archive-cases', $project), [
        'test_case_ids' => [$case->id],
        'archive_suite_id' => $archiveSuite->id,
    ]);

    $response->assertRedirect();

    $case->refresh();
    expect($case->test_suite_id)->toBe($archiveSuite->id);
    expect($case->archived_from_suite_id)->toBe($suite->id);
    expect($case->order)->toBe(6);
});

test('archiving rejects an archive_suite_id pointing at a non-archive suite', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $notArchived = TestSuite::factory()->create(['project_id' => $project->id, 'is_archived' => false]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $this->actingAs($user)->post(route('test-suites.archive-cases', $project), [
        'test_case_ids' => [$case->id],
        'archive_suite_id' => $notArchived->id,
    ])->assertForbidden();
});

test('unarchiving with mode original returns each case to its recorded suite', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suiteA = TestSuite::factory()->create(['project_id' => $project->id]);
    $suiteB = TestSuite::factory()->create(['project_id' => $project->id]);
    $archiveSuite = TestSuite::factory()->create(['project_id' => $project->id, 'is_archived' => true]);

    $caseFromA = TestCase::factory()->create([
        'test_suite_id' => $archiveSuite->id,
        'archived_from_suite_id' => $suiteA->id,
    ]);
    $caseFromB = TestCase::factory()->create([
        'test_suite_id' => $archiveSuite->id,
        'archived_from_suite_id' => $suiteB->id,
    ]);
    $caseWithNoOriginal = TestCase::factory()->create([
        'test_suite_id' => $archiveSuite->id,
        'archived_from_suite_id' => null,
    ]);

    $response = $this->actingAs($user)->post(route('test-suites.unarchive-cases', $project), [
        'test_case_ids' => [$caseFromA->id, $caseFromB->id, $caseWithNoOriginal->id],
        'mode' => 'original',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('mode');

    $caseFromA->refresh();
    $caseFromB->refresh();
    $caseWithNoOriginal->refresh();

    expect($caseFromA->test_suite_id)->toBe($suiteA->id);
    expect($caseFromA->archived_from_suite_id)->toBeNull();
    expect($caseFromB->test_suite_id)->toBe($suiteB->id);
    expect($caseFromB->archived_from_suite_id)->toBeNull();
    // No recorded original suite — left in the archive suite untouched.
    expect($caseWithNoOriginal->test_suite_id)->toBe($archiveSuite->id);
});

test('unarchiving with mode original surfaces an error when the original suite was deleted', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $archiveSuite = TestSuite::factory()->create(['project_id' => $project->id, 'is_archived' => true]);

    $case = TestCase::factory()->create([
        'test_suite_id' => $archiveSuite->id,
        'archived_from_suite_id' => $suite->id,
    ]);

    // Deleting the suite nulls archived_from_suite_id via nullOnDelete().
    $suite->delete();
    $case->refresh();
    expect($case->archived_from_suite_id)->toBeNull();

    $response = $this->actingAs($user)->post(route('test-suites.unarchive-cases', $project), [
        'test_case_ids' => [$case->id],
        'mode' => 'original',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('mode');
    $response->assertSessionMissing('success');

    $case->refresh();
    expect($case->test_suite_id)->toBe($archiveSuite->id);
});

test('unarchiving with mode choose moves every selected case to the target suite', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $archiveSuite = TestSuite::factory()->create(['project_id' => $project->id, 'is_archived' => true]);
    $target = TestSuite::factory()->create(['project_id' => $project->id]);

    $caseOne = TestCase::factory()->create([
        'test_suite_id' => $archiveSuite->id,
        'archived_from_suite_id' => $archiveSuite->id,
    ]);
    $caseTwo = TestCase::factory()->create([
        'test_suite_id' => $archiveSuite->id,
        'archived_from_suite_id' => $archiveSuite->id,
    ]);

    $response = $this->actingAs($user)->post(route('test-suites.unarchive-cases', $project), [
        'test_case_ids' => [$caseOne->id, $caseTwo->id],
        'mode' => 'choose',
        'target_suite_id' => $target->id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_cases', [
        'id' => $caseOne->id,
        'test_suite_id' => $target->id,
        'archived_from_suite_id' => null,
    ]);
    $this->assertDatabaseHas('test_cases', [
        'id' => $caseTwo->id,
        'test_suite_id' => $target->id,
        'archived_from_suite_id' => null,
    ]);
});

test('viewer cannot archive or unarchive test cases', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $archiveSuite = TestSuite::factory()->create(['project_id' => $project->id, 'is_archived' => true]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $this->actingAs($viewer)->post(route('test-suites.archive-cases', $project), [
        'test_case_ids' => [$case->id],
        'archive_suite_name' => 'Archive',
    ])->assertForbidden();

    $this->actingAs($viewer)->post(route('test-suites.unarchive-cases', $project), [
        'test_case_ids' => [$case->id],
        'mode' => 'choose',
        'target_suite_id' => $archiveSuite->id,
    ])->assertForbidden();
});
