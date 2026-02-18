<?php

use App\Models\Bugreport;
use App\Models\Checklist;
use App\Models\Project;
use App\Models\ProjectFeature;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
});

// ===== Test Case feature linking =====

test('test case store syncs feature_ids', function () {
    $suite = TestSuite::factory()->create(['project_id' => $this->project->id]);
    $features = ProjectFeature::factory()->count(2)->create(['project_id' => $this->project->id]);

    $response = $this->actingAs($this->user)->post(route('test-cases.store', [$this->project, $suite]), [
        'title' => 'Login test',
        'priority' => 'high',
        'severity' => 'major',
        'type' => 'functional',
        'automation_status' => 'not_automated',
        'feature_ids' => $features->pluck('id')->toArray(),
    ]);

    $response->assertRedirect();

    $testCase = TestCase::where('title', 'Login test')->first();
    expect($testCase->projectFeatures)->toHaveCount(2);
});

test('test case update syncs feature_ids', function () {
    $suite = TestSuite::factory()->create(['project_id' => $this->project->id]);
    $testCase = TestCase::factory()->create(['test_suite_id' => $suite->id]);
    $features = ProjectFeature::factory()->count(3)->create(['project_id' => $this->project->id]);

    $testCase->projectFeatures()->sync([$features[0]->id]);

    $response = $this->actingAs($this->user)->put(route('test-cases.update', [$this->project, $suite, $testCase]), [
        'title' => $testCase->title,
        'priority' => $testCase->priority,
        'severity' => $testCase->severity,
        'type' => $testCase->type,
        'automation_status' => $testCase->automation_status,
        'feature_ids' => [$features[1]->id, $features[2]->id],
    ]);

    $response->assertRedirect();

    $testCase->refresh();
    expect($testCase->projectFeatures)->toHaveCount(2);
    expect($testCase->projectFeatures->pluck('id')->sort()->values()->all())
        ->toBe([$features[1]->id, $features[2]->id]);
});

// ===== Checklist feature linking =====

test('checklist store syncs feature_ids', function () {
    $features = ProjectFeature::factory()->count(2)->create(['project_id' => $this->project->id]);

    $response = $this->actingAs($this->user)->post(route('checklists.store', $this->project), [
        'name' => 'My Checklist',
        'feature_ids' => $features->pluck('id')->toArray(),
    ]);

    $response->assertRedirect();

    $checklist = Checklist::where('name', 'My Checklist')->first();
    expect($checklist->projectFeatures)->toHaveCount(2);
});

test('checklist update syncs feature_ids', function () {
    $checklist = Checklist::factory()->create(['project_id' => $this->project->id]);
    $features = ProjectFeature::factory()->count(2)->create(['project_id' => $this->project->id]);

    $response = $this->actingAs($this->user)->put(route('checklists.update', [$this->project, $checklist]), [
        'name' => $checklist->name,
        'columns_config' => $checklist->columns_config,
        'feature_ids' => [$features[0]->id],
    ]);

    $response->assertRedirect();

    $checklist->refresh();
    expect($checklist->projectFeatures)->toHaveCount(1);
    expect($checklist->projectFeatures->first()->id)->toBe($features[0]->id);
});

// ===== Bugreport feature linking =====

test('bugreport store syncs feature_ids', function () {
    $features = ProjectFeature::factory()->count(2)->create(['project_id' => $this->project->id]);

    $response = $this->actingAs($this->user)->post(route('bugreports.store', $this->project), [
        'title' => 'Login bug',
        'severity' => 'major',
        'priority' => 'high',
        'status' => 'new',
        'feature_ids' => $features->pluck('id')->toArray(),
    ]);

    $response->assertRedirect();

    $bugreport = Bugreport::where('title', 'Login bug')->first();
    expect($bugreport->projectFeatures)->toHaveCount(2);
});

test('bugreport update syncs feature_ids', function () {
    $bugreport = Bugreport::factory()->create(['project_id' => $this->project->id, 'reported_by' => $this->user->id]);
    $features = ProjectFeature::factory()->count(2)->create(['project_id' => $this->project->id]);

    $bugreport->projectFeatures()->sync([$features[0]->id, $features[1]->id]);

    $response = $this->actingAs($this->user)->put(route('bugreports.update', [$this->project, $bugreport]), [
        'title' => $bugreport->title,
        'severity' => $bugreport->severity,
        'priority' => $bugreport->priority,
        'status' => $bugreport->status,
        'feature_ids' => [],
    ]);

    $response->assertRedirect();

    $bugreport->refresh();
    expect($bugreport->projectFeatures)->toHaveCount(0);
});

// ===== Test Suite feature linking =====

test('test suite store syncs feature_ids', function () {
    $features = ProjectFeature::factory()->count(2)->create(['project_id' => $this->project->id]);

    $response = $this->actingAs($this->user)->post(route('test-suites.store', $this->project), [
        'name' => 'Auth Suite',
        'type' => 'functional',
        'feature_ids' => $features->pluck('id')->toArray(),
    ]);

    $response->assertRedirect();

    $testSuite = TestSuite::where('name', 'Auth Suite')->first();
    expect($testSuite->projectFeatures)->toHaveCount(2);
});

test('test suite update syncs feature_ids', function () {
    $testSuite = TestSuite::factory()->create(['project_id' => $this->project->id, 'type' => 'functional']);
    $features = ProjectFeature::factory()->count(3)->create(['project_id' => $this->project->id]);

    $testSuite->projectFeatures()->sync([$features[0]->id]);

    $response = $this->actingAs($this->user)->put(route('test-suites.update', [$this->project, $testSuite]), [
        'name' => $testSuite->name,
        'type' => $testSuite->type,
        'feature_ids' => [$features[1]->id, $features[2]->id],
    ]);

    $response->assertRedirect();

    $testSuite->refresh();
    expect($testSuite->projectFeatures)->toHaveCount(2);
    expect($testSuite->projectFeatures->pluck('id')->sort()->values()->all())
        ->toBe([$features[1]->id, $features[2]->id]);
});

// ===== Quick create feature endpoint =====

test('quick create feature returns json', function () {
    $response = $this->actingAs($this->user)->postJson(route('project-features.store', $this->project), [
        'name' => 'New Feature',
        'priority' => 'high',
    ]);

    $response->assertCreated();
    $response->assertJsonFragment(['name' => 'New Feature', 'priority' => 'high']);

    $this->assertDatabaseHas('project_features', [
        'project_id' => $this->project->id,
        'name' => 'New Feature',
    ]);
});

test('quick create feature validates name required', function () {
    $response = $this->actingAs($this->user)->postJson(route('project-features.store', $this->project), [
        'priority' => 'high',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('name');
});
