<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;

test('export returns CSV with all test cases', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id, 'name' => 'Login Suite']);
    TestCase::factory()->create([
        'test_suite_id' => $suite->id,
        'title' => 'Login Test',
        'priority' => 'high',
        'steps' => [['action' => 'Open page', 'expected' => 'Page loads']],
        'tags' => ['auth', 'login'],
    ]);
    TestCase::factory()->create([
        'test_suite_id' => $suite->id,
        'title' => 'Logout Test',
        'priority' => 'medium',
    ]);

    $response = $this->actingAs($user)->get(
        route('test-suites.export-cases', $project)
    );

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Login Test');
    expect($content)->toContain('Logout Test');
    expect($content)->toContain('Login Suite');
    expect($content)->toContain('1. Open page | Expected: Page loads');
    expect($content)->toContain('"auth, login"');
});

test('export returns CSV with only selected test cases when ids provided', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $case1 = TestCase::factory()->create(['test_suite_id' => $suite->id, 'title' => 'Included Case']);
    $case2 = TestCase::factory()->create(['test_suite_id' => $suite->id, 'title' => 'Excluded Case']);

    $response = $this->actingAs($user)->get(
        route('test-suites.export-cases', $project).'?ids='.$case1->id
    );

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('Included Case');
    expect($content)->not->toContain('Excluded Case');
});

test('import creates test cases from parsed data with matching fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.import-cases', $project),
        [
            'test_suite_id' => $suite->id,
            'headers' => ['Title', 'Description', 'Priority', 'Tags'],
            'rows' => [
                ['Login test', 'Test login flow', 'high', 'auth, login'],
                ['Signup test', 'Test signup', 'low', 'auth'],
            ],
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success', '2 test case(s) imported successfully.');

    $cases = TestCase::where('test_suite_id', $suite->id)->orderBy('order')->get();
    expect($cases)->toHaveCount(2);
    expect($cases[0]->title)->toBe('Login test');
    expect($cases[0]->description)->toBe('Test login flow');
    expect($cases[0]->priority)->toBe('high');
    expect($cases[0]->tags)->toBe(['auth', 'login']);
    expect($cases[0]->created_by)->toBe($user->id);
    expect($cases[1]->title)->toBe('Signup test');
    expect($cases[1]->priority)->toBe('low');
});

test('import ignores unmatched columns', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.import-cases', $project),
        [
            'test_suite_id' => $suite->id,
            'headers' => ['Title', 'Custom Column', 'Unknown Field'],
            'rows' => [
                ['Test case 1', 'custom value', 'unknown value'],
            ],
        ]
    );

    $response->assertRedirect();

    $case = TestCase::where('test_suite_id', $suite->id)->first();
    expect($case)->not->toBeNull();
    expect($case->title)->toBe('Test case 1');
});

test('import maps field aliases correctly', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.import-cases', $project),
        [
            'test_suite_id' => $suite->id,
            'headers' => ['Test Case Name', 'Summary', 'Pre-conditions', 'Test Steps', 'Expected', 'Labels'],
            'rows' => [
                ['Aliased case', 'A summary', 'Must be logged in', "1. Open page\n2. Click button", 'Success', 'smoke, regression'],
            ],
        ]
    );

    $response->assertRedirect();

    $case = TestCase::where('test_suite_id', $suite->id)->first();
    expect($case->title)->toBe('Aliased case');
    expect($case->description)->toBe('A summary');
    expect($case->preconditions)->toBe('Must be logged in');
    expect($case->steps)->toHaveCount(2);
    expect($case->steps[0]['action'])->toBe('Open page');
    expect($case->steps[1]['action'])->toBe('Click button');
    expect($case->expected_result)->toBe('Success');
    expect($case->tags)->toBe(['smoke', 'regression']);
});

test('import validates target suite belongs to project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create(['user_id' => $user->id]);
    $otherSuite = TestSuite::factory()->create(['project_id' => $otherProject->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.import-cases', $project),
        [
            'test_suite_id' => $otherSuite->id,
            'headers' => ['Title'],
            'rows' => [['Test']],
        ]
    );

    $response->assertSessionHasErrors('test_suite_id');
    expect(TestCase::where('test_suite_id', $otherSuite->id)->count())->toBe(0);
});

test('import fixes double-encoded UTF-8 text', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);

    // Simulate double-encoded Cyrillic: "Тест" in UTF-8 is D0A2 D0B5 D1 81 D1 82
    // Double-encoded becomes: C390 C2A2 C390 C2B5 C391 C281 C391 C282
    $doubleEncoded = mb_convert_encoding('Тест', 'UTF-8', 'ISO-8859-1');

    $response = $this->actingAs($user)->post(
        route('test-suites.import-cases', $project),
        [
            'test_suite_id' => $suite->id,
            'headers' => ['Title'],
            'rows' => [[$doubleEncoded]],
        ]
    );

    $response->assertRedirect();

    $case = TestCase::where('test_suite_id', $suite->id)->first();
    expect($case->title)->toBe('Тест');
});

test('import requires at least one row', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('test-suites.import-cases', $project),
        [
            'test_suite_id' => $suite->id,
            'headers' => ['Title'],
            'rows' => [],
        ]
    );

    $response->assertSessionHasErrors('rows');
});

test('viewer can export', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->get(route('test-suites.export-cases', $project))
        ->assertOk();
});

test('viewer cannot import', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $suite = TestSuite::factory()->create(['project_id' => $project->id]);

    $this->actingAs($viewer)
        ->post(route('test-suites.import-cases', $project), [
            'test_suite_id' => $suite->id,
            'headers' => ['Title'],
            'rows' => [['Test']],
        ])
        ->assertForbidden();
});
