<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRun;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->testSuite = TestSuite::factory()->create(['project_id' => $this->project->id]);
    $this->testCase = TestCase::factory()->create(['test_suite_id' => $this->testSuite->id]);
});

test('pausing an active run sets paused_at', function () {
    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
    ]);

    $this->actingAs($this->user)->post(
        route('test-runs.pause', [$this->project, $testRun])
    );

    $testRun->refresh();
    expect($testRun->paused_at)->not->toBeNull();
});

test('cannot pause a non-active run', function () {
    $testRun = TestRun::factory()->completed()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->actingAs($this->user)->post(
        route('test-runs.pause', [$this->project, $testRun])
    );

    $testRun->refresh();
    expect($testRun->paused_at)->toBeNull();
    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('cannot pause an already paused run', function () {
    $testRun = TestRun::factory()->paused()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->actingAs($this->user)->post(
        route('test-runs.pause', [$this->project, $testRun])
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('resuming a paused run accumulates paused seconds and clears paused_at', function () {
    Carbon::setTestNow('2026-02-15 10:00:00');

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
        'paused_at' => Carbon::parse('2026-02-15 09:50:00'),
        'total_paused_seconds' => 100,
    ]);

    $this->actingAs($this->user)->post(
        route('test-runs.resume', [$this->project, $testRun])
    );

    $testRun->refresh();
    expect($testRun->paused_at)->toBeNull();
    expect($testRun->total_paused_seconds)->toBe(700); // 100 + 600 (10 min)

    Carbon::setTestNow();
});

test('cannot resume a non-paused run', function () {
    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->actingAs($this->user)->post(
        route('test-runs.resume', [$this->project, $testRun])
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('completing a paused run finalizes pause time', function () {
    Carbon::setTestNow('2026-02-15 10:00:00');

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
        'paused_at' => Carbon::parse('2026-02-15 09:55:00'),
        'total_paused_seconds' => 60,
    ]);

    $this->actingAs($this->user)->post(
        route('test-runs.complete', [$this->project, $testRun])
    );

    $testRun->refresh();
    expect($testRun->status)->toBe('completed');
    expect($testRun->paused_at)->toBeNull();
    expect($testRun->total_paused_seconds)->toBe(360); // 60 + 300 (5 min)

    Carbon::setTestNow();
});

test('archiving a paused run finalizes pause time', function () {
    Carbon::setTestNow('2026-02-15 10:00:00');

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
        'paused_at' => Carbon::parse('2026-02-15 09:58:00'),
        'total_paused_seconds' => 0,
    ]);

    $this->actingAs($this->user)->post(
        route('test-runs.archive', [$this->project, $testRun])
    );

    $testRun->refresh();
    expect($testRun->status)->toBe('archived');
    expect($testRun->paused_at)->toBeNull();
    expect($testRun->total_paused_seconds)->toBe(120); // 2 min

    Carbon::setTestNow();
});

test('getElapsedSeconds returns correct value for active run', function () {
    Carbon::setTestNow('2026-02-15 10:00:00');

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
        'started_at' => Carbon::parse('2026-02-15 09:00:00'),
        'total_paused_seconds' => 600,
    ]);

    // 3600s total - 600s paused = 3000s
    expect($testRun->getElapsedSeconds())->toBe(3000);

    Carbon::setTestNow();
});

test('getElapsedSeconds accounts for active pause', function () {
    Carbon::setTestNow('2026-02-15 10:00:00');

    $testRun = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
        'started_at' => Carbon::parse('2026-02-15 09:00:00'),
        'total_paused_seconds' => 0,
        'paused_at' => Carbon::parse('2026-02-15 09:50:00'),
    ]);

    // 3600s total - 600s (current pause) = 3000s
    expect($testRun->getElapsedSeconds())->toBe(3000);

    Carbon::setTestNow();
});

test('isPaused returns correct values', function () {
    $active = TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
    ]);
    expect($active->isPaused())->toBeFalse();

    $paused = TestRun::factory()->paused()->create([
        'project_id' => $this->project->id,
    ]);
    expect($paused->isPaused())->toBeTrue();
});

test('store sets created_by to authenticated user', function () {
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
    expect($testRun->created_by)->toBe($this->user->id);
});

test('index includes creator, elapsed_seconds, and is_paused', function () {
    $creator = User::factory()->create(['name' => 'John Creator']);

    TestRun::factory()->active()->create([
        'project_id' => $this->project->id,
        'created_by' => $creator->id,
        'started_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($this->user)->get(
        route('test-runs.index', $this->project)
    );

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('TestRuns/Index')
        ->has('testRuns', 1)
        ->where('testRuns.0.creator.name', 'John Creator')
        ->has('testRuns.0.elapsed_seconds')
        ->where('testRuns.0.is_paused', false)
    );
});
