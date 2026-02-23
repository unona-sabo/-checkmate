<?php

use App\Models\AiGeneration;
use App\Models\Project;
use App\Models\ProjectFeature;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
});

test('index renders for authorized user', function () {
    $response = $this->actingAs($this->user)->get(route('ai-generator.index', $this->project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('AIGenerator/Index')
        ->has('project')
        ->has('testSuites')
        ->has('defaultProvider')
        ->has('hasGeminiKey')
        ->has('hasClaudeKey')
    );
});

test('index requires authentication', function () {
    $this->get(route('ai-generator.index', $this->project))
        ->assertRedirect(route('login'));
});

test('generate endpoint validates input', function () {
    $response = $this->actingAs($this->user)->postJson(
        route('ai-generator.generate', $this->project),
        []
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['input_type']);
});

test('generate with text input validates text is required', function () {
    $response = $this->actingAs($this->user)->postJson(
        route('ai-generator.generate', $this->project),
        ['input_type' => 'text', 'text' => '']
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['text']);
});

test('generate with text input returns test cases', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode([
                            [
                                'title' => 'Verify login with valid credentials',
                                'description' => 'Test that users can log in successfully',
                                'preconditions' => 'User account exists',
                                'steps' => [
                                    ['action' => 'Navigate to login page', 'expected' => 'Login page is displayed'],
                                    ['action' => 'Enter credentials', 'expected' => null],
                                    ['action' => 'Click login', 'expected' => 'User is redirected'],
                                ],
                                'expected_result' => 'User is redirected to dashboard',
                                'priority' => 'high',
                                'severity' => 'major',
                                'type' => 'functional',
                                'automation_status' => 'not_automated',
                            ],
                        ]),
                    ]],
                ],
            ]],
        ]),
    ]);

    config(['services.gemini.api_key' => 'test-key']);

    $response = $this->actingAs($this->user)->postJson(
        route('ai-generator.generate', $this->project),
        [
            'input_type' => 'text',
            'text' => 'The login page allows users to authenticate with email and password.',
            'count' => 1,
            'provider' => 'gemini',
        ]
    );

    $response->assertOk();
    $response->assertJsonStructure([
        'test_cases' => [['title', 'description', 'preconditions', 'steps', 'expected_result', 'priority', 'severity', 'type', 'automation_status']],
        'generation_id',
        'provider',
        'model',
    ]);

    expect($response->json('test_cases.0.title'))->toBe('Verify login with valid credentials');
    expect($response->json('test_cases.0.severity'))->toBe('major');
    expect($response->json('test_cases.0.automation_status'))->toBe('not_automated');
    // Steps should be formatted as text with per-step expected results
    expect($response->json('test_cases.0.steps'))->toContain('Navigate to login page');
    expect($response->json('test_cases.0.steps'))->toContain('Expected: Login page is displayed');
});

test('ai_generations record created on generate', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode([[
                            'title' => 'Test case',
                            'description' => 'Desc',
                            'preconditions' => '',
                            'steps' => [['action' => 'Step one', 'expected' => null]],
                            'expected_result' => 'Pass',
                            'priority' => 'medium',
                            'severity' => 'major',
                            'type' => 'functional',
                            'automation_status' => 'not_automated',
                        ]]),
                    ]],
                ],
            ]],
        ]),
    ]);

    config(['services.gemini.api_key' => 'test-key']);

    $this->actingAs($this->user)->postJson(
        route('ai-generator.generate', $this->project),
        [
            'input_type' => 'text',
            'text' => 'Some documentation text for testing.',
            'count' => 1,
            'provider' => 'gemini',
        ]
    );

    $this->assertDatabaseHas('ai_generations', [
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'provider' => 'gemini',
        'input_type' => 'text',
        'test_cases_generated' => 1,
    ]);
});

test('save creates test case records in existing test suite', function () {
    $suite = TestSuite::factory()->create(['project_id' => $this->project->id]);

    $response = $this->actingAs($this->user)->post(
        route('ai-generator.save', $this->project),
        [
            'test_suite_id' => $suite->id,
            'test_cases' => [
                [
                    'title' => 'Generated Test Case 1',
                    'description' => 'A generated test case',
                    'preconditions' => 'None',
                    'steps' => "1. Navigate to login page\n   Expected: Login page is displayed\n2. Enter credentials\n3. Click login\n   Expected: User is redirected",
                    'expected_result' => 'Expected outcome',
                    'priority' => 'high',
                    'severity' => 'major',
                    'type' => 'functional',
                    'automation_status' => 'not_automated',
                ],
            ],
        ]
    );

    $response->assertRedirect();

    $this->assertDatabaseHas('test_cases', [
        'test_suite_id' => $suite->id,
        'title' => 'Generated Test Case 1',
        'priority' => 'high',
        'severity' => 'major',
        'type' => 'functional',
        'automation_status' => 'not_automated',
        'created_by' => $this->user->id,
    ]);

    $testCase = TestCase::where('title', 'Generated Test Case 1')->first();
    expect($testCase->steps)->toBeArray();
    expect($testCase->steps)->toHaveCount(3);
    expect($testCase->steps[0]['action'])->toBe('Navigate to login page');
    expect($testCase->steps[0]['expected'])->toBe('Login page is displayed');
    expect($testCase->steps[1]['action'])->toBe('Enter credentials');
    expect($testCase->steps[1]['expected'])->toBeNull();
    expect($testCase->steps[2]['expected'])->toBe('User is redirected');
});

test('save creates new test suite when test_suite_name provided', function () {
    $response = $this->actingAs($this->user)->post(
        route('ai-generator.save', $this->project),
        [
            'test_suite_name' => 'AI Generated Suite',
            'test_cases' => [
                [
                    'title' => 'Test Case in New Suite',
                    'description' => 'Description',
                    'steps' => '1. Do something',
                    'expected_result' => 'Something happens',
                    'priority' => 'medium',
                    'type' => 'functional',
                ],
            ],
        ]
    );

    $response->assertRedirect();

    $this->assertDatabaseHas('test_suites', [
        'project_id' => $this->project->id,
        'name' => 'AI Generated Suite',
    ]);

    $this->assertDatabaseHas('test_cases', [
        'title' => 'Test Case in New Suite',
    ]);
});

test('save updates ai_generation record when id provided', function () {
    $generation = AiGeneration::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'test_cases_generated' => 3,
        'test_cases_approved' => 0,
        'test_cases_imported' => 0,
    ]);

    $suite = TestSuite::factory()->create(['project_id' => $this->project->id]);

    $this->actingAs($this->user)->post(
        route('ai-generator.save', $this->project),
        [
            'test_suite_id' => $suite->id,
            'ai_generation_id' => $generation->id,
            'test_cases' => [
                [
                    'title' => 'Test Case 1',
                    'steps' => '1. Step',
                    'expected_result' => 'Result',
                    'priority' => 'medium',
                    'type' => 'functional',
                ],
                [
                    'title' => 'Test Case 2',
                    'steps' => '1. Step',
                    'expected_result' => 'Result',
                    'priority' => 'low',
                    'type' => 'smoke',
                ],
            ],
        ]
    );

    $this->assertDatabaseHas('ai_generations', [
        'id' => $generation->id,
        'test_cases_approved' => 2,
        'test_cases_imported' => 2,
        'test_suite_id' => $suite->id,
    ]);
});

test('viewer cannot generate or save', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $this->actingAs($viewer)->postJson(
        route('ai-generator.generate', $project),
        ['input_type' => 'text', 'text' => 'Test', 'provider' => 'gemini']
    )->assertForbidden();

    $this->actingAs($viewer)->post(
        route('ai-generator.save', $project),
        ['test_suite_name' => 'Suite', 'test_cases' => [['title' => 'TC']]]
    )->assertForbidden();
});

test('save validates test_cases are required', function () {
    $response = $this->actingAs($this->user)->post(
        route('ai-generator.save', $this->project),
        [
            'test_suite_name' => 'Suite',
            'test_cases' => [],
        ]
    );

    $response->assertSessionHasErrors('test_cases');
});

test('save links test cases to features associated with the test suite', function () {
    $suite = TestSuite::factory()->create(['project_id' => $this->project->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $this->project->id]);
    $suite->projectFeatures()->attach($feature->id);

    $this->actingAs($this->user)->post(
        route('ai-generator.save', $this->project),
        [
            'test_suite_id' => $suite->id,
            'test_cases' => [
                [
                    'title' => 'Feature-linked TC 1',
                    'steps' => '1. Step one',
                    'expected_result' => 'Result',
                    'priority' => 'medium',
                    'type' => 'functional',
                ],
                [
                    'title' => 'Feature-linked TC 2',
                    'steps' => '1. Step one',
                    'expected_result' => 'Result',
                    'priority' => 'high',
                    'type' => 'functional',
                ],
            ],
        ]
    );

    $createdCases = TestCase::where('test_suite_id', $suite->id)->pluck('id')->toArray();
    expect($createdCases)->toHaveCount(2);

    $linkedCaseIds = $feature->testCases()->pluck('test_cases.id')->toArray();
    expect($linkedCaseIds)->toEqualCanonicalizing($createdCases);
});

test('save validates test suite belongs to project', function () {
    $otherProject = Project::factory()->create(['user_id' => $this->user->id]);
    $otherSuite = TestSuite::factory()->create(['project_id' => $otherProject->id]);

    $response = $this->actingAs($this->user)->post(
        route('ai-generator.save', $this->project),
        [
            'test_suite_id' => $otherSuite->id,
            'test_cases' => [
                ['title' => 'TC', 'priority' => 'medium', 'type' => 'functional'],
            ],
        ]
    );

    $response->assertStatus(422);
});
