<?php

use App\Models\CoverageAnalysis;
use App\Models\Project;
use App\Models\ProjectFeature;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;

// ===== Index =====

test('index page renders with coverage statistics', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    ProjectFeature::factory()->count(5)->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('test-coverage.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('TestCoverage/Index')
        ->has('project')
        ->has('statistics')
        ->has('coverageByModule')
        ->has('features', 5)
        ->has('gaps')
        ->has('hasAnthropicKey')
    );
});

test('index shows zero coverage when no features exist', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('test-coverage.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('statistics.overall_coverage', 0)
        ->where('statistics.total_features', 0)
        ->where('statistics.gaps_count', 0)
    );
});

test('index requires authentication', function () {
    $project = Project::factory()->create();

    $this->get(route('test-coverage.index', $project))->assertRedirect(route('login'));
});

// ===== Feature CRUD =====

test('store creates a project feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('test-coverage.features.store', $project), [
        'name' => 'User Login',
        'description' => 'Users can log in with email and password',
        'module' => 'UI',
        'category' => 'Authentication',
        'priority' => 'critical',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('project_features', [
        'project_id' => $project->id,
        'name' => 'User Login',
        'module' => 'UI',
        'category' => 'Authentication',
        'priority' => 'critical',
    ]);
});

test('store validates required fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson(route('test-coverage.features.store', $project), [
        'name' => '',
        'priority' => 'invalid',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'priority']);
});

test('update modifies a project feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create([
        'project_id' => $project->id,
        'name' => 'Original Name',
    ]);

    $response = $this->actingAs($user)->put(route('test-coverage.features.update', [$project, $feature->id]), [
        'name' => 'Updated Name',
        'priority' => 'high',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('project_features', [
        'id' => $feature->id,
        'name' => 'Updated Name',
        'priority' => 'high',
    ]);
});

test('destroy deletes a project feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->delete(route('test-coverage.features.destroy', [$project, $feature->id]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('project_features', ['id' => $feature->id]);
});

// ===== Feature-Test Case Linking =====

test('link test case to feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $testCase = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $response = $this->actingAs($user)->post(route('test-coverage.features.link-test-case', [$project, $feature->id]), [
        'test_case_id' => $testCase->id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('feature_test_case', [
        'feature_id' => $feature->id,
        'test_case_id' => $testCase->id,
    ]);
});

test('unlink test case from feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $testCase = TestCase::factory()->create(['test_suite_id' => $suite->id]);
    $feature->testCases()->attach($testCase->id);

    $response = $this->actingAs($user)->delete(route('test-coverage.features.unlink-test-case', [$project, $feature->id, $testCase->id]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('feature_test_case', [
        'feature_id' => $feature->id,
        'test_case_id' => $testCase->id,
    ]);
});

// ===== Coverage Calculation =====

test('coverage statistics reflect linked test cases', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $testCase = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $coveredFeature = ProjectFeature::factory()->create(['project_id' => $project->id]);
    $coveredFeature->testCases()->attach($testCase->id);

    ProjectFeature::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('test-coverage.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('statistics.total_features', 2)
        ->where('statistics.covered_features', 1)
        ->where('statistics.uncovered_features', 1)
        ->where('statistics.overall_coverage', 50)
        ->where('statistics.gaps_count', 1)
    );
});

// ===== Coverage History =====

test('coverage history returns analysis records', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    CoverageAnalysis::factory()->count(3)->create([
        'project_id' => $project->id,
        'analyzed_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson(route('test-coverage.history', $project));

    $response->assertOk();
    $response->assertJsonCount(3);
});

test('coverage history requires authentication', function () {
    $project = Project::factory()->create();

    $this->getJson(route('test-coverage.history', $project))->assertUnauthorized();
});

// ===== RBAC =====

test('viewer cannot create features', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($viewer)->postJson(route('test-coverage.features.store', $project), [
        'name' => 'Test Feature',
        'priority' => 'medium',
    ]);

    $response->assertForbidden();
});

test('viewer cannot delete features', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($viewer)->deleteJson(route('test-coverage.features.destroy', [$project, $feature->id]));

    $response->assertForbidden();
});

// ===== Coverage Module Breakdown =====

test('coverage by module groups features correctly', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    ProjectFeature::factory()->count(3)->create(['project_id' => $project->id, 'module' => 'UI']);
    ProjectFeature::factory()->count(2)->create(['project_id' => $project->id, 'module' => 'API']);

    $response = $this->actingAs($user)->get(route('test-coverage.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('coverageByModule', 2)
    );
});

// ===== Auto-Link =====

test('auto-link finds test cases matching feature name', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Registration']);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $matching = TestCase::factory()->create(['test_suite_id' => $suite->id, 'title' => 'Test user registration flow']);
    $nonMatching = TestCase::factory()->create(['test_suite_id' => $suite->id, 'title' => 'Test login page']);

    $response = $this->actingAs($user)->post(route('test-coverage.features.auto-link', [$project, $feature]));

    $response->assertRedirect();

    $this->assertDatabaseHas('feature_test_case', [
        'feature_id' => $feature->id,
        'test_case_id' => $matching->id,
    ]);
    $this->assertDatabaseMissing('feature_test_case', [
        'feature_id' => $feature->id,
        'test_case_id' => $nonMatching->id,
    ]);
});

test('auto-link is case insensitive', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Login']);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $tc = TestCase::factory()->create(['test_suite_id' => $suite->id, 'title' => 'Verify LOGIN form validation']);

    $this->actingAs($user)->post(route('test-coverage.features.auto-link', [$project, $feature]));

    $this->assertDatabaseHas('feature_test_case', [
        'feature_id' => $feature->id,
        'test_case_id' => $tc->id,
    ]);
});

test('auto-link does not remove existing manual links', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Registration']);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $manualTc = TestCase::factory()->create(['test_suite_id' => $suite->id, 'title' => 'Manual test case']);
    $autoTc = TestCase::factory()->create(['test_suite_id' => $suite->id, 'title' => 'Test registration process']);
    $feature->testCases()->attach($manualTc->id);

    $this->actingAs($user)->post(route('test-coverage.features.auto-link', [$project, $feature]));

    $this->assertDatabaseHas('feature_test_case', [
        'feature_id' => $feature->id,
        'test_case_id' => $manualTc->id,
    ]);
    $this->assertDatabaseHas('feature_test_case', [
        'feature_id' => $feature->id,
        'test_case_id' => $autoTc->id,
    ]);
});

test('auto-link-all links across multiple features', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature1 = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Login']);
    $feature2 = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Dashboard']);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $tc1 = TestCase::factory()->create(['test_suite_id' => $suite->id, 'title' => 'Test login page loads']);
    $tc2 = TestCase::factory()->create(['test_suite_id' => $suite->id, 'title' => 'Test dashboard widgets']);

    $response = $this->actingAs($user)->post(route('test-coverage.auto-link-all', $project));

    $response->assertRedirect();

    $this->assertDatabaseHas('feature_test_case', ['feature_id' => $feature1->id, 'test_case_id' => $tc1->id]);
    $this->assertDatabaseHas('feature_test_case', ['feature_id' => $feature2->id, 'test_case_id' => $tc2->id]);
    $this->assertDatabaseMissing('feature_test_case', ['feature_id' => $feature1->id, 'test_case_id' => $tc2->id]);
    $this->assertDatabaseMissing('feature_test_case', ['feature_id' => $feature2->id, 'test_case_id' => $tc1->id]);
});

test('store feature auto-links matching test cases', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $tc = TestCase::factory()->create(['test_suite_id' => $suite->id, 'title' => 'Verify payment processing']);

    $this->actingAs($user)->post(route('test-coverage.features.store', $project), [
        'name' => 'Payment',
        'priority' => 'high',
    ]);

    $feature = ProjectFeature::where('name', 'Payment')->first();

    $this->assertDatabaseHas('feature_test_case', [
        'feature_id' => $feature->id,
        'test_case_id' => $tc->id,
    ]);
});

test('get test cases returns all project test cases', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    TestCase::factory()->count(3)->create(['test_suite_id' => $suite->id]);

    $response = $this->actingAs($user)->getJson(route('test-coverage.test-cases', $project));

    $response->assertOk();
    $response->assertJsonCount(3);
});

test('viewer cannot auto-link', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($viewer)->postJson(route('test-coverage.auto-link-all', $project));

    $response->assertForbidden();
});

test('link and unlink return redirect', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $testCase = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $linkResponse = $this->actingAs($user)->post(route('test-coverage.features.link-test-case', [$project, $feature->id]), [
        'test_case_id' => $testCase->id,
    ]);

    $linkResponse->assertRedirect();

    $unlinkResponse = $this->actingAs($user)->delete(route('test-coverage.features.unlink-test-case', [$project, $feature->id, $testCase->id]));

    $unlinkResponse->assertRedirect();
});

// ===== Index with allTestCases prop =====

test('index includes allTestCases prop', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    TestCase::factory()->count(2)->create(['test_suite_id' => $suite->id]);

    $response = $this->actingAs($user)->get(route('test-coverage.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('allTestCases', 2)
    );
});

// ===== Inactive Features =====

test('inactive features are excluded from statistics', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    ProjectFeature::factory()->create(['project_id' => $project->id, 'is_active' => true]);
    ProjectFeature::factory()->create(['project_id' => $project->id, 'is_active' => false]);

    $response = $this->actingAs($user)->get(route('test-coverage.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('statistics.total_features', 1)
    );
});
