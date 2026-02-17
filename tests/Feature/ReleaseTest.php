<?php

use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseChecklistItem;
use App\Models\ReleaseFeature;
use App\Models\TestRun;
use App\Models\User;
use App\Models\Workspace;

// ===== Index =====

test('index page renders with releases', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Release::factory()->count(3)->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->actingAs($user)->get(route('releases.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Releases/Index')
        ->has('project')
        ->has('releases', 3)
    );
});

test('index requires authentication', function () {
    $project = Project::factory()->create();

    $this->get(route('releases.index', $project))->assertRedirect(route('login'));
});

// ===== Store =====

test('store creates a release', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('releases.store', $project), [
        'version' => '1.0.0',
        'name' => 'Initial Release',
        'description' => 'First release',
        'planned_date' => '2026-03-01',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('releases', [
        'project_id' => $project->id,
        'version' => '1.0.0',
        'name' => 'Initial Release',
        'created_by' => $user->id,
    ]);
});

test('store creates default checklist items', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('releases.store', $project), [
        'version' => '1.0.0',
        'name' => 'Test Release',
    ]);

    $release = Release::where('version', '1.0.0')->first();

    expect($release->checklistItems()->count())->toBe(17);

    $categories = $release->checklistItems()->distinct()->pluck('category')->sort()->values()->all();
    expect($categories)->toBe(['deployment', 'documentation', 'performance', 'security', 'testing']);
});

test('store validates required fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson(route('releases.store', $project), [
        'version' => '',
        'name' => '',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['version', 'name']);
});

// ===== Show =====

test('show page renders with release data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    ReleaseFeature::factory()->count(2)->create(['release_id' => $release->id]);
    ReleaseChecklistItem::factory()->count(3)->create(['release_id' => $release->id]);

    $response = $this->actingAs($user)->get(route('releases.show', [$project, $release]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Releases/Show')
        ->has('project')
        ->has('release')
        ->has('blockers')
        ->loadDeferredProps('sidebar', fn ($page) => $page
            ->has('projectFeatures')
            ->has('projectTestRuns')
            ->has('workspaceMembers')
        )
    );
});

// ===== Update =====

test('update modifies a release', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->actingAs($user)->put(route('releases.update', [$project, $release]), [
        'name' => 'Updated Release',
        'status' => 'testing',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('releases', [
        'id' => $release->id,
        'name' => 'Updated Release',
        'status' => 'testing',
    ]);
});

test('update changes decision', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->actingAs($user)->put(route('releases.update', [$project, $release]), [
        'decision' => 'go',
        'decision_notes' => 'All checks passed',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('releases', [
        'id' => $release->id,
        'decision' => 'go',
        'decision_notes' => 'All checks passed',
    ]);
});

// ===== Destroy =====

test('destroy deletes a release', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->actingAs($user)->delete(route('releases.destroy', [$project, $release]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('releases', ['id' => $release->id]);
});

// ===== Refresh Metrics =====

test('refresh metrics creates a snapshot', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->actingAs($user)->post(route('releases.refresh-metrics', [$project, $release]));

    $response->assertRedirect();

    expect($release->metricsSnapshots()->count())->toBe(1);
});

// ===== Features =====

test('store feature adds a release feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->actingAs($user)->post(route('releases.features.store', [$project, $release]), [
        'feature_name' => 'User Login',
        'description' => 'Login feature',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('release_features', [
        'release_id' => $release->id,
        'feature_name' => 'User Login',
    ]);
});

test('update feature modifies a release feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $feature = ReleaseFeature::factory()->create(['release_id' => $release->id]);

    $response = $this->actingAs($user)->put(route('releases.features.update', [$project, $release, $feature]), [
        'feature_name' => 'Updated Feature',
        'status' => 'completed',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('release_features', [
        'id' => $feature->id,
        'feature_name' => 'Updated Feature',
        'status' => 'completed',
    ]);
});

test('destroy feature removes a release feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $feature = ReleaseFeature::factory()->create(['release_id' => $release->id]);

    $response = $this->actingAs($user)->delete(route('releases.features.destroy', [$project, $release, $feature]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('release_features', ['id' => $feature->id]);
});

// ===== Checklist Items =====

test('store checklist item adds to release', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->actingAs($user)->post(route('releases.checklist-items.store', [$project, $release]), [
        'title' => 'New checklist item',
        'category' => 'testing',
        'priority' => 'high',
        'is_blocker' => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('release_checklist_items', [
        'release_id' => $release->id,
        'title' => 'New checklist item',
        'category' => 'testing',
        'priority' => 'high',
        'is_blocker' => true,
    ]);
});

test('update checklist item edits title and priority', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $item = ReleaseChecklistItem::factory()->create(['release_id' => $release->id, 'title' => 'Old title', 'priority' => 'low']);

    $response = $this->actingAs($user)->put(route('releases.checklist-items.update', [$project, $release, $item]), [
        'title' => 'Updated title',
        'priority' => 'critical',
    ]);

    $response->assertRedirect();

    $item->refresh();
    expect($item->title)->toBe('Updated title');
    expect($item->priority)->toBe('critical');
});

test('destroy checklist item removes from release', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $item = ReleaseChecklistItem::factory()->create(['release_id' => $release->id]);

    $response = $this->actingAs($user)->delete(route('releases.checklist-items.destroy', [$project, $release, $item]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('release_checklist_items', ['id' => $item->id]);
});

test('update checklist item changes status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $item = ReleaseChecklistItem::factory()->create(['release_id' => $release->id, 'status' => 'pending']);

    $response = $this->actingAs($user)->put(route('releases.checklist-items.update', [$project, $release, $item]), [
        'status' => 'completed',
    ]);

    $response->assertRedirect();

    $item->refresh();
    expect($item->status)->toBe('completed');
    expect($item->completed_at)->not->toBeNull();
});

// ===== Test Run Linking =====

test('link test run attaches to release', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $testRun = TestRun::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(route('releases.test-runs.link', [$project, $release]), [
        'test_run_id' => $testRun->id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('release_test_runs', [
        'release_id' => $release->id,
        'test_run_id' => $testRun->id,
    ]);
});

test('unlink test run detaches from release', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $testRun = TestRun::factory()->create(['project_id' => $project->id]);
    $release->testRuns()->attach($testRun->id);

    $response = $this->actingAs($user)->delete(route('releases.test-runs.unlink', [$project, $release, $testRun]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('release_test_runs', [
        'release_id' => $release->id,
        'test_run_id' => $testRun->id,
    ]);
});

// ===== RBAC =====

test('viewer cannot create releases', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($viewer)->postJson(route('releases.store', $project), [
        'version' => '1.0.0',
        'name' => 'Test',
    ]);

    $response->assertForbidden();
});

test('viewer cannot update releases', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id]);

    $response = $this->actingAs($viewer)->putJson(route('releases.update', [$project, $release]), [
        'name' => 'Updated',
    ]);

    $response->assertForbidden();
});

test('viewer cannot delete releases', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_by' => $owner->id]);

    $response = $this->actingAs($viewer)->deleteJson(route('releases.destroy', [$project, $release]));

    $response->assertForbidden();
});
