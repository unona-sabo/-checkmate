<?php

use App\Models\AiGeneratedTestCase;
use App\Models\AiSetting;
use App\Models\Checklist;
use App\Models\CoverageAnalysis;
use App\Models\Documentation;
use App\Models\Project;
use App\Models\ProjectFeature;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

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
        ->has('defaultAiProvider')
        ->has('hasGeminiKey')
        ->has('hasClaudeKey')
        ->has('hasOpenaiKey')
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
        'module' => ['UI'],
        'category' => 'Authentication',
        'priority' => 'critical',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('project_features', [
        'project_id' => $project->id,
        'name' => 'User Login',
        'module' => json_encode(['UI']),
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

// ===== Feature-Checklist Linking =====

test('link checklist to feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(route('test-coverage.features.link-checklist', [$project, $feature->id]), [
        'checklist_id' => $checklist->id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('feature_checklist', [
        'feature_id' => $feature->id,
        'checklist_id' => $checklist->id,
    ]);
});

test('unlink checklist from feature', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);
    $feature->checklists()->attach($checklist->id);

    $response = $this->actingAs($user)->delete(route('test-coverage.features.unlink-checklist', [$project, $feature->id, $checklist->id]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('feature_checklist', [
        'feature_id' => $feature->id,
        'checklist_id' => $checklist->id,
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

test('coverage statistics count checklist-linked features as covered', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $featureWithChecklist = ProjectFeature::factory()->create(['project_id' => $project->id]);
    $featureWithChecklist->checklists()->attach($checklist->id);

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

test('gaps exclude features with linked checklists', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);

    $coveredFeature = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Covered by checklist']);
    $coveredFeature->checklists()->attach($checklist->id);

    $uncoveredFeature = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Uncovered feature']);

    $response = $this->actingAs($user)->get(route('test-coverage.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('gaps', 1)
        ->where('gaps.0.feature', 'Uncovered feature')
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

test('coverage history includes a diff against the previous run', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $older = CoverageAnalysis::factory()->create([
        'project_id' => $project->id,
        'analyzed_at' => now()->subDay(),
        'overall_coverage' => 50,
        'total_features' => 10,
        'gaps_count' => 2,
        'analysis_data' => [
            'gaps' => [
                ['feature' => 'Login'],
                ['feature' => 'Checkout'],
            ],
        ],
    ]);

    $newer = CoverageAnalysis::factory()->create([
        'project_id' => $project->id,
        'analyzed_at' => now(),
        'overall_coverage' => 65,
        'total_features' => 12,
        'gaps_count' => 2,
        'analysis_data' => [
            'gaps' => [
                ['feature' => 'Checkout'],
                ['feature' => 'Search'],
            ],
        ],
    ]);

    $response = $this->actingAs($user)->getJson(route('test-coverage.history', $project));

    $response->assertOk();
    $response->assertJson([
        [
            'date' => $newer->analyzed_at->format('Y-m-d'),
            'coverage' => 65,
            'diff' => [
                'coverage_delta' => 15,
                'features_delta' => 2,
                'gaps_delta' => 0,
                'gaps_added' => ['Search'],
                'gaps_resolved' => ['Login'],
            ],
        ],
        [
            'date' => $older->analyzed_at->format('Y-m-d'),
            'coverage' => 50,
            'diff' => null,
        ],
    ]);
});

test('a history entry can be viewed with its full analysis snapshot', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $analysis = CoverageAnalysis::factory()->create([
        'project_id' => $project->id,
        'analysis_data' => [
            'summary' => 'Coverage was decent at this point in time.',
            'overall_coverage' => 60,
            'gaps' => [['id' => 'gap_1', 'feature' => 'Login']],
            'coverage_by_category' => ['functional' => 70],
        ],
    ]);

    $response = $this->actingAs($user)->getJson(route('test-coverage.history.show', [$project, $analysis]));

    $response->assertOk();
    $response->assertJson([
        'id' => $analysis->id,
        'analysis_data' => [
            'summary' => 'Coverage was decent at this point in time.',
            'overall_coverage' => 60,
            'gaps' => [['id' => 'gap_1', 'feature' => 'Login']],
            'coverage_by_category' => ['functional' => 70],
        ],
    ]);
});

test('a history entry from another project cannot be viewed', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create();
    $analysis = CoverageAnalysis::factory()->create(['project_id' => $otherProject->id]);

    $this->actingAs($user)
        ->getJson(route('test-coverage.history.show', [$project, $analysis]))
        ->assertNotFound();
});

// ===== AI Analysis =====

test('ai analysis uses the selected provider', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'summary' => 'Solid coverage overall.',
                        'overall_coverage' => 80,
                        'gaps' => [],
                    ]),
                ],
            ]],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    AiSetting::forWorkspace($workspace)->update(['openai_api_key' => 'test-key']);

    $response = $this->actingAs($user)->postJson(route('test-coverage.ai-analysis', $project), [
        'provider' => 'openai',
    ]);

    $response->assertOk();
    expect($response->json('analysis.summary'))->toBe('Solid coverage overall.');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'openai.com'));
});

test('ai analysis includes custom instructions in the prompt', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => json_encode(['summary' => 'ok', 'overall_coverage' => 50, 'gaps' => []])]],
                ],
            ]],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    AiSetting::forWorkspace($workspace)->update(['gemini_api_key' => 'test-key']);

    $this->actingAs($user)->postJson(route('test-coverage.ai-analysis', $project), [
        'provider' => 'gemini',
        'custom_instructions' => 'Focus only on payment and security flows.',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->body(), 'Focus only on payment and security flows.'));
});

test('ai analysis prompt omits test case steps and caps documentation length to stay within context limits', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => json_encode(['summary' => 'ok', 'overall_coverage' => 50, 'gaps' => []])]],
                ],
            ]],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    AiSetting::forWorkspace($workspace)->update(['gemini_api_key' => 'test-key']);

    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    TestCase::factory()->create([
        'test_suite_id' => $suite->id,
        'title' => 'Login with valid credentials',
        'steps' => ['This exact step text should not appear verbatim in the prompt'],
    ]);
    Documentation::factory()->create([
        'project_id' => $project->id,
        'content' => str_repeat('word ', 1000),
    ]);

    $this->actingAs($user)->postJson(route('test-coverage.ai-analysis', $project), [
        'provider' => 'gemini',
    ])->assertOk();

    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, 'Login with valid credentials')
            && ! str_contains($body, 'This exact step text should not appear verbatim in the prompt')
            && ! str_contains($body, str_repeat('word ', 1000));
    });
});

test('ai analysis falls back to the workspace default provider when none is given', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => json_encode(['summary' => 'ok', 'overall_coverage' => 50, 'gaps' => []])]],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    AiSetting::forWorkspace($workspace)->update(['anthropic_api_key' => 'test-key', 'default_provider' => 'claude']);

    $this->actingAs($user)->postJson(route('test-coverage.ai-analysis', $project))->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic.com'));
});

test('ai analysis validation rejects an unknown provider', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);

    $response = $this->actingAs($user)->postJson(route('test-coverage.ai-analysis', $project), [
        'provider' => 'not-a-provider',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['provider']);
});

test('ai analysis returns a friendly error and saves no history when the ai provider call fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'The model is overloaded.']], 503),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    AiSetting::forWorkspace($workspace)->update(['gemini_api_key' => 'test-key']);

    $response = $this->actingAs($user)->postJson(route('test-coverage.ai-analysis', $project), [
        'provider' => 'gemini',
    ]);

    $response->assertStatus(502);
    expect($response->json('message'))->toContain('The model is overloaded.');
    expect($project->coverageAnalyses()->count())->toBe(0);
});

test('ai analysis returns a friendly error when no api key is configured', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);

    $response = $this->actingAs($user)->postJson(route('test-coverage.ai-analysis', $project), [
        'provider' => 'gemini',
    ]);

    $response->assertStatus(502);
    expect($response->json('message'))->toContain('Gemini API key is not configured');
});

test('running ai analysis again creates a new coverage analysis record', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => json_encode(['summary' => 'ok', 'overall_coverage' => 50, 'gaps' => []])]],
                ],
            ]],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    AiSetting::forWorkspace($workspace)->update(['gemini_api_key' => 'test-key']);

    $this->actingAs($user)->postJson(route('test-coverage.ai-analysis', $project), ['provider' => 'gemini'])->assertOk();
    $this->actingAs($user)->postJson(route('test-coverage.ai-analysis', $project), [
        'provider' => 'gemini',
        'custom_instructions' => 'Re-check with a focus on edge cases.',
    ])->assertOk();

    expect($project->coverageAnalyses()->count())->toBe(2);
});

// ===== Generate Test Cases =====

test('generate test cases returns a friendly error and saves nothing when the ai provider call fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'The model is overloaded.']], 503),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    AiSetting::forWorkspace($workspace)->update(['gemini_api_key' => 'test-key']);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Login']);

    $response = $this->actingAs($user)->postJson(route('test-coverage.generate-test-cases', $project), [
        'id' => (string) $feature->id,
        'feature' => 'Login',
        'priority' => 'high',
        'provider' => 'gemini',
    ]);

    $response->assertStatus(502);
    expect($response->json('message'))->toContain('The model is overloaded.');
    $this->assertDatabaseCount('ai_generated_test_cases', 0);
});

test('generate test cases accepts a gap without a description or category', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => json_encode([
                        [
                            'title' => 'Verify login succeeds with valid credentials',
                            'test_steps' => ['Open login page', 'Submit valid credentials'],
                            'expected_result' => 'User is redirected to the dashboard',
                            'priority' => 'high',
                            'type' => 'positive',
                        ],
                    ])]],
                ],
            ]],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    AiSetting::forWorkspace($workspace)->update(['gemini_api_key' => 'test-key']);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Login']);

    // Mirrors the payload sent for a DB-sourced (non-AI) coverage gap, whose
    // feature description/category can be null and whose module is an array.
    $response = $this->actingAs($user)->postJson(route('test-coverage.generate-test-cases', $project), [
        'id' => (string) $feature->id,
        'feature' => 'Login',
        'description' => null,
        'module' => 'Auth',
        'category' => null,
        'priority' => 'high',
        'provider' => 'gemini',
    ]);

    $response->assertOk();
    expect($response->json('test_cases.0.title'))->toBe('Verify login succeeds with valid credentials');
    $this->assertDatabaseHas('ai_generated_test_cases', [
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'title' => 'Verify login succeeds with valid credentials',
    ]);
});

test('generate test cases handles multiple generated cases from the ai response', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => json_encode([
                        [
                            'title' => 'Login with valid credentials',
                            'test_steps' => ['Open login page', 'Submit valid credentials'],
                            'expected_result' => 'User is redirected to the dashboard',
                            'priority' => 'high',
                            'type' => 'positive',
                        ],
                        [
                            'title' => 'Login with invalid credentials',
                            'test_steps' => ['Open login page', 'Submit invalid credentials'],
                            'expected_result' => 'An error message is shown',
                            'priority' => 'medium',
                            'type' => 'negative',
                        ],
                    ])]],
                ],
            ]],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    AiSetting::forWorkspace($workspace)->update(['gemini_api_key' => 'test-key']);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Login']);

    $response = $this->actingAs($user)->postJson(route('test-coverage.generate-test-cases', $project), [
        'id' => (string) $feature->id,
        'feature' => 'Login',
        'description' => 'Users can log into the app',
        'module' => 'Auth',
        'category' => 'functional',
        'priority' => 'high',
        'provider' => 'gemini',
    ]);

    $response->assertOk();
    expect($response->json('test_cases'))->toHaveCount(2);
    $this->assertDatabaseCount('ai_generated_test_cases', 2);
    $this->assertDatabaseHas('ai_generated_test_cases', ['title' => 'Login with valid credentials']);
    $this->assertDatabaseHas('ai_generated_test_cases', ['title' => 'Login with invalid credentials']);
});

test('generate test cases prompt includes the feature existing test cases and its linked documentation', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => json_encode([])]],
                ],
            ]],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    AiSetting::forWorkspace($workspace)->update(['gemini_api_key' => 'test-key']);

    $feature = ProjectFeature::factory()->create(['project_id' => $project->id, 'name' => 'Login']);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $existingTestCase = TestCase::factory()->create([
        'test_suite_id' => $suite->id,
        'title' => 'Login with valid credentials',
        'steps' => ['Open login page', 'Submit valid credentials'],
    ]);
    $feature->testCases()->attach($existingTestCase->id);

    $linkedDoc = Documentation::factory()->create([
        'project_id' => $project->id,
        'title' => 'Login flow spec',
        'content' => 'Users authenticate via email and password.',
    ]);
    $feature->documentations()->attach($linkedDoc->id);

    // Documentation not linked to this feature must not leak into the prompt.
    Documentation::factory()->create([
        'project_id' => $project->id,
        'title' => 'Unrelated billing spec',
        'content' => 'Invoices are generated monthly.',
    ]);

    $this->actingAs($user)->postJson(route('test-coverage.generate-test-cases', $project), [
        'id' => (string) $feature->id,
        'feature' => 'Login',
        'description' => 'Users can log into the app',
        'module' => 'Auth',
        'category' => 'functional',
        'priority' => 'high',
        'provider' => 'gemini',
    ])->assertOk();

    Http::assertSent(function ($request) {
        $prompt = $request->data()['contents'][0]['parts'][0]['text'];

        return str_contains($prompt, 'Login with valid credentials')
            && str_contains($prompt, 'Login flow spec')
            && str_contains($prompt, 'Users authenticate via email and password.')
            && ! str_contains($prompt, 'Unrelated billing spec');
    });
});

// ===== Approve Generated Test Cases =====

test('approving generated test cases creates real test cases in a new suite and marks them approved', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $feature = ProjectFeature::factory()->create(['project_id' => $project->id]);

    $case = AiGeneratedTestCase::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'title' => 'Login with valid credentials',
        'test_steps' => ['Open login page', 'Submit valid credentials'],
        'priority' => 'high',
    ]);

    $response = $this->actingAs($user)->post(route('test-coverage.approve-test-cases', $project), [
        'ids' => [$case->id],
        'test_suite_name' => 'AI Generated Tests',
    ]);

    $response->assertRedirect();

    $testSuite = TestSuite::where('project_id', $project->id)->where('name', 'AI Generated Tests')->firstOrFail();

    $this->assertDatabaseHas('test_cases', [
        'test_suite_id' => $testSuite->id,
        'title' => 'Login with valid credentials',
        'priority' => 'high',
    ]);

    $createdTestCase = TestCase::where('test_suite_id', $testSuite->id)->firstOrFail();
    expect($createdTestCase->steps)->toBe([
        ['action' => 'Open login page', 'expected' => null],
        ['action' => 'Submit valid credentials', 'expected' => null],
    ]);
    expect($createdTestCase->projectFeatures()->pluck('project_features.id')->all())->toBe([$feature->id]);

    $case->refresh();
    expect($case->is_approved)->toBeTrue()
        ->and($case->approved_by)->toBe($user->id)
        ->and($case->approved_at)->not->toBeNull();
});

test('approving generated test cases can target an existing test suite', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $case = AiGeneratedTestCase::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('test-coverage.approve-test-cases', $project), [
        'ids' => [$case->id],
        'test_suite_id' => $suite->id,
    ])->assertRedirect(route('test-suites.show', [$project, $suite]));

    $this->assertDatabaseHas('test_cases', ['test_suite_id' => $suite->id, 'title' => $case->title]);
});

test('approving generated test cases rejects ids belonging to another project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create();
    $foreignCase = AiGeneratedTestCase::factory()->create(['project_id' => $otherProject->id]);

    $this->actingAs($user)->post(route('test-coverage.approve-test-cases', $project), [
        'ids' => [$foreignCase->id],
        'test_suite_name' => 'Should not be created',
    ])->assertNotFound();

    $this->assertDatabaseMissing('test_suites', ['project_id' => $project->id, 'name' => 'Should not be created']);
});

test('viewer cannot approve generated test cases', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);
    $project = Project::factory()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id]);
    $case = AiGeneratedTestCase::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)->post(route('test-coverage.approve-test-cases', $project), [
        'ids' => [$case->id],
        'test_suite_name' => 'New Suite',
    ])->assertForbidden();
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

    ProjectFeature::factory()->count(3)->create(['project_id' => $project->id, 'module' => ['UI']]);
    ProjectFeature::factory()->count(2)->create(['project_id' => $project->id, 'module' => ['API']]);

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

// ===== Multi-Module =====

test('store feature accepts module as array', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('test-coverage.features.store', $project), [
        'name' => 'Cross-cutting Auth',
        'module' => ['UI', 'API'],
        'priority' => 'high',
    ]);

    $response->assertRedirect();

    $feature = ProjectFeature::where('name', 'Cross-cutting Auth')->first();
    expect($feature->module)->toBe(['UI', 'API']);
});

test('coverage by module counts multi-module features in each group', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    $testCase = TestCase::factory()->create(['test_suite_id' => $suite->id]);

    $multiFeature = ProjectFeature::factory()->create([
        'project_id' => $project->id,
        'module' => ['UI', 'API'],
    ]);
    $multiFeature->testCases()->attach($testCase->id);

    $response = $this->actingAs($user)->get(route('test-coverage.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('coverageByModule', 2)
        ->where('coverageByModule.0.module', 'UI')
        ->where('coverageByModule.0.total_features', 1)
        ->where('coverageByModule.0.covered_features', 1)
        ->where('coverageByModule.1.module', 'API')
        ->where('coverageByModule.1.total_features', 1)
        ->where('coverageByModule.1.covered_features', 1)
    );
});
