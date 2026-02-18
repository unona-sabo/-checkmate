<?php

use App\Models\Project;
use App\Models\ProjectFeature;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

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

test('bulk copy copies attachments when requested', function () {
    Storage::fake('public');
    Storage::disk('public')->put('attachments/test-cases/original.png', 'file-content');

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $targetSuite = TestSuite::factory()->create(['project_id' => $project->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $case->attachments()->create([
        'original_filename' => 'screenshot.png',
        'stored_path' => 'attachments/test-cases/original.png',
        'mime_type' => 'image/png',
        'size' => 1234,
    ]);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-copy-cases', $project),
        [
            'test_case_ids' => [$case->id],
            'target_suite_id' => $targetSuite->id,
            'copy_attachments' => true,
        ]
    );

    $response->assertRedirect();

    $copiedCase = TestCase::where('test_suite_id', $targetSuite->id)->first();
    expect($copiedCase->attachments)->toHaveCount(1);
    expect($copiedCase->attachments->first()->original_filename)->toBe('screenshot.png');
    expect($copiedCase->attachments->first()->stored_path)->not->toBe('attachments/test-cases/original.png');
    Storage::disk('public')->assertExists($copiedCase->attachments->first()->stored_path);
});

test('bulk copy skips attachments when not requested', function () {
    Storage::fake('public');
    Storage::disk('public')->put('attachments/test-cases/original.png', 'file-content');

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $targetSuite = TestSuite::factory()->create(['project_id' => $project->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $case->attachments()->create([
        'original_filename' => 'screenshot.png',
        'stored_path' => 'attachments/test-cases/original.png',
        'mime_type' => 'image/png',
        'size' => 1234,
    ]);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-copy-cases', $project),
        [
            'test_case_ids' => [$case->id],
            'target_suite_id' => $targetSuite->id,
            'copy_attachments' => false,
        ]
    );

    $response->assertRedirect();

    $copiedCase = TestCase::where('test_suite_id', $targetSuite->id)->first();
    expect($copiedCase->attachments)->toHaveCount(0);
});

test('bulk copy copies feature links within same project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $targetSuite = TestSuite::factory()->create(['project_id' => $project->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $feature = ProjectFeature::factory()->create(['project_id' => $project->id]);
    $case->projectFeatures()->attach($feature->id);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-copy-cases', $project),
        [
            'test_case_ids' => [$case->id],
            'target_suite_id' => $targetSuite->id,
            'copy_features' => true,
        ]
    );

    $response->assertRedirect();

    $copiedCase = TestCase::where('test_suite_id', $targetSuite->id)->first();
    expect($copiedCase->projectFeatures)->toHaveCount(1);
    expect($copiedCase->projectFeatures->first()->id)->toBe($feature->id);
});

test('bulk copy copies notes when requested', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $targetSuite = TestSuite::factory()->create(['project_id' => $project->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $case->note()->create(['content' => 'Important test note']);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-copy-cases', $project),
        [
            'test_case_ids' => [$case->id],
            'target_suite_id' => $targetSuite->id,
            'copy_notes' => true,
        ]
    );

    $response->assertRedirect();

    $copiedCase = TestCase::where('test_suite_id', $targetSuite->id)->first();
    expect($copiedCase->note)->not->toBeNull();
    expect($copiedCase->note->content)->toBe('Important test note');
});

test('bulk copy to cross-project suite succeeds', function () {
    $user = User::factory()->create();
    $sourceProject = Project::factory()->create(['user_id' => $user->id]);
    $targetProject = Project::factory()->create(['user_id' => $user->id]);
    $sourceSuite = TestSuite::factory()->create(['project_id' => $sourceProject->id]);
    $targetSuite = TestSuite::factory()->create(['project_id' => $targetProject->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $sourceSuite->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-copy-cases', $sourceProject),
        [
            'test_case_ids' => [$case->id],
            'target_suite_id' => $targetSuite->id,
            'target_project_id' => $targetProject->id,
        ]
    );

    $response->assertRedirect();
    expect(TestCase::where('test_suite_id', $targetSuite->id)->count())->toBe(1);
});

test('bulk copy cross-project matches features by name', function () {
    $user = User::factory()->create();
    $sourceProject = Project::factory()->create(['user_id' => $user->id]);
    $targetProject = Project::factory()->create(['user_id' => $user->id]);
    $sourceSuite = TestSuite::factory()->create(['project_id' => $sourceProject->id]);
    $targetSuite = TestSuite::factory()->create(['project_id' => $targetProject->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $sourceSuite->id]);

    $sourceFeature = ProjectFeature::factory()->create([
        'project_id' => $sourceProject->id,
        'name' => 'Login Feature',
    ]);
    $targetFeature = ProjectFeature::factory()->create([
        'project_id' => $targetProject->id,
        'name' => 'Login Feature',
    ]);

    $case->projectFeatures()->attach($sourceFeature->id);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-copy-cases', $sourceProject),
        [
            'test_case_ids' => [$case->id],
            'target_suite_id' => $targetSuite->id,
            'target_project_id' => $targetProject->id,
            'copy_features' => true,
        ]
    );

    $response->assertRedirect();

    $copiedCase = TestCase::where('test_suite_id', $targetSuite->id)->first();
    expect($copiedCase->projectFeatures)->toHaveCount(1);
    expect($copiedCase->projectFeatures->first()->id)->toBe($targetFeature->id);
});

test('creating suite with test_case_ids moves cases into new suite', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $parentSuite = TestSuite::factory()->create(['project_id' => $project->id, 'parent_id' => null]);
    $sourceSuite = TestSuite::factory()->create(['project_id' => $project->id, 'parent_id' => $parentSuite->id]);
    $cases = TestCase::factory()->count(3)->create(['test_suite_id' => $sourceSuite->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.store', $project),
        [
            'name' => 'New Subcategory',
            'type' => 'functional',
            'parent_id' => $parentSuite->id,
            'test_case_ids' => $cases->pluck('id')->toArray(),
        ]
    );

    $response->assertRedirect(route('test-suites.index', $project));

    $newSuite = TestSuite::where('name', 'New Subcategory')->first();
    expect($newSuite)->not->toBeNull();

    foreach ($cases as $case) {
        expect($case->fresh()->test_suite_id)->toBe($newSuite->id);
    }

    // Verify sequential ordering
    $movedCases = TestCase::where('test_suite_id', $newSuite->id)->orderBy('order')->get();
    expect($movedCases->pluck('order')->all())->toBe([1, 2, 3]);
});

test('creating suite with test_case_ids ignores cases from other projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $parentSuite = TestSuite::factory()->create(['project_id' => $project->id, 'parent_id' => null]);
    $ownSuite = TestSuite::factory()->create(['project_id' => $project->id, 'parent_id' => $parentSuite->id]);
    $ownCase = TestCase::factory()->create(['test_suite_id' => $ownSuite->id]);

    $otherProject = Project::factory()->create(['user_id' => $user->id]);
    $otherSuite = TestSuite::factory()->create(['project_id' => $otherProject->id]);
    $otherCase = TestCase::factory()->create(['test_suite_id' => $otherSuite->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.store', $project),
        [
            'name' => 'Scoped Subcategory',
            'type' => 'regression',
            'parent_id' => $parentSuite->id,
            'test_case_ids' => [$ownCase->id, $otherCase->id],
        ]
    );

    $response->assertRedirect(route('test-suites.index', $project));

    $newSuite = TestSuite::where('name', 'Scoped Subcategory')->first();

    // Own case moved
    expect($ownCase->fresh()->test_suite_id)->toBe($newSuite->id);
    // Other project's case stays in its original suite
    expect($otherCase->fresh()->test_suite_id)->toBe($otherSuite->id);
});

test('bulk copy cross-project rejects mismatched suite', function () {
    $user = User::factory()->create();
    $sourceProject = Project::factory()->create(['user_id' => $user->id]);
    $targetProject = Project::factory()->create(['user_id' => $user->id]);
    $thirdProject = Project::factory()->create(['user_id' => $user->id]);
    $sourceSuite = TestSuite::factory()->create(['project_id' => $sourceProject->id]);
    $mismatchedSuite = TestSuite::factory()->create(['project_id' => $thirdProject->id]);
    $case = TestCase::factory()->create(['test_suite_id' => $sourceSuite->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.bulk-copy-cases', $sourceProject),
        [
            'test_case_ids' => [$case->id],
            'target_suite_id' => $mismatchedSuite->id,
            'target_project_id' => $targetProject->id,
        ]
    );

    $response->assertSessionHasErrors('target_suite_id');
});
