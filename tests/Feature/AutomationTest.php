<?php

use App\Models\AutomationTestResult;
use App\Models\Project;
use App\Models\TestEnvironment;
use App\Models\TestRunTemplate;
use App\Models\User;
use App\Models\Workspace;

// ===== Index =====

test('index page renders for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('automation.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Automation/Index')
        ->has('project')
        ->has('recentResults')
        ->has('latestRunStats')
        ->has('environments')
        ->has('templates')
    );
});

test('index requires authentication', function () {
    $project = Project::factory()->create();

    $this->get(route('automation.index', $project))->assertRedirect(route('login'));
});

test('index shows recent results', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    AutomationTestResult::factory()->count(3)->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('automation.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('recentResults', 3)
    );
});

test('index computes latest run stats', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $executedAt = now();

    AutomationTestResult::factory()->create(['project_id' => $project->id, 'status' => 'passed', 'executed_at' => $executedAt]);
    AutomationTestResult::factory()->create(['project_id' => $project->id, 'status' => 'passed', 'executed_at' => $executedAt]);
    AutomationTestResult::factory()->create(['project_id' => $project->id, 'status' => 'failed', 'executed_at' => $executedAt]);

    $response = $this->actingAs($user)->get(route('automation.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('latestRunStats.total', 3)
        ->where('latestRunStats.passed', 2)
        ->where('latestRunStats.failed', 1)
    );
});

test('index returns environments and templates', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    TestEnvironment::factory()->create(['project_id' => $project->id, 'name' => 'Staging']);
    TestRunTemplate::factory()->create(['project_id' => $project->id, 'name' => 'Smoke Tests']);

    $response = $this->actingAs($user)->get(route('automation.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('environments', 1)
        ->has('templates', 1)
    );
});

// ===== Update Config =====

test('update config saves automation path', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->put(route('automation.update-config', $project), [
        'automation_tests_path' => 'C:\\AutotestMilx\\milx-qa',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'automation_tests_path' => 'C:\\AutotestMilx\\milx-qa',
    ]);
});

test('update config validates required path', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson(route('automation.update-config', $project), [
        'automation_tests_path' => '',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['automation_tests_path']);
});

// ===== Scan =====

test('scan returns error when path not configured', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'automation_tests_path' => null]);

    $response = $this->actingAs($user)->getJson(route('automation.scan', $project));

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Automation tests path not configured']);
});

// ===== Clear Results =====

test('clear results deletes all automation results', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    AutomationTestResult::factory()->count(5)->create(['project_id' => $project->id]);

    expect($project->automationTestResults()->count())->toBe(5);

    $response = $this->actingAs($user)->delete(route('automation.clear-results', $project));

    $response->assertRedirect();
    expect($project->automationTestResults()->count())->toBe(0);
});

// ===== Environments =====

test('environment can be created', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('automation.environments.store', $project), [
        'name' => 'Staging',
        'base_url' => 'https://staging.example.com',
        'browser' => 'chromium',
        'workers' => 2,
        'retries' => 1,
        'headed' => false,
        'timeout' => 30000,
        'is_default' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('test_environments', [
        'project_id' => $project->id,
        'name' => 'Staging',
        'base_url' => 'https://staging.example.com',
        'workers' => 2,
        'is_default' => true,
    ]);
});

test('environment validates name is required', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson(route('automation.environments.store', $project), [
        'name' => '',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name']);
});

test('environment can be updated', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $env = TestEnvironment::factory()->create(['project_id' => $project->id, 'name' => 'Old Name']);

    $response = $this->actingAs($user)->put(route('automation.environments.update', [$project, $env]), [
        'name' => 'New Name',
        'browser' => 'firefox',
        'workers' => 4,
        'retries' => 0,
        'headed' => true,
        'timeout' => 60000,
        'is_default' => false,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('test_environments', [
        'id' => $env->id,
        'name' => 'New Name',
        'browser' => 'firefox',
        'workers' => 4,
    ]);
});

test('environment can be deleted', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $env = TestEnvironment::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->delete(route('automation.environments.destroy', [$project, $env]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('test_environments', ['id' => $env->id]);
});

test('setting default environment clears previous default', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $env1 = TestEnvironment::factory()->create(['project_id' => $project->id, 'is_default' => true]);

    $this->actingAs($user)->post(route('automation.environments.store', $project), [
        'name' => 'New Default',
        'browser' => 'chromium',
        'workers' => 1,
        'retries' => 0,
        'headed' => false,
        'timeout' => 30000,
        'is_default' => true,
    ]);

    expect($env1->fresh()->is_default)->toBeFalse();
    expect(TestEnvironment::where('project_id', $project->id)->where('is_default', true)->count())->toBe(1);
});

// ===== Templates =====

test('template can be created', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('automation.templates.store', $project), [
        'name' => 'Smoke Tests',
        'description' => 'Run smoke tests on staging',
        'tags' => ['@smoke', '@critical'],
        'tag_mode' => 'or',
        'file_pattern' => 'tests/smoke/**',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('test_run_templates', [
        'project_id' => $project->id,
        'name' => 'Smoke Tests',
        'tag_mode' => 'or',
    ]);
});

test('template can be updated', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $tmpl = TestRunTemplate::factory()->create(['project_id' => $project->id, 'name' => 'Old']);

    $response = $this->actingAs($user)->put(route('automation.templates.update', [$project, $tmpl]), [
        'name' => 'Updated',
        'tag_mode' => 'and',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('test_run_templates', [
        'id' => $tmpl->id,
        'name' => 'Updated',
        'tag_mode' => 'and',
    ]);
});

test('template can be deleted', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $tmpl = TestRunTemplate::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->delete(route('automation.templates.destroy', [$project, $tmpl]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('test_run_templates', ['id' => $tmpl->id]);
});

// ===== RBAC =====

test('viewer cannot update config', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($viewer)->putJson(route('automation.update-config', $project), [
        'automation_tests_path' => 'C:\\test',
    ]);

    $response->assertForbidden();
});

test('viewer can view automation page', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($viewer)->get(route('automation.index', $project));

    $response->assertOk();
});

test('viewer cannot run tests', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($viewer)->postJson(route('automation.run', $project));

    $response->assertForbidden();
});

test('viewer cannot clear results', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($viewer)->deleteJson(route('automation.clear-results', $project));

    $response->assertForbidden();
});

test('viewer cannot create environments', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($viewer)->postJson(route('automation.environments.store', $project), [
        'name' => 'Test',
    ]);

    $response->assertForbidden();
});

test('viewer cannot create templates', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($viewer)->postJson(route('automation.templates.store', $project), [
        'name' => 'Test',
    ]);

    $response->assertForbidden();
});
