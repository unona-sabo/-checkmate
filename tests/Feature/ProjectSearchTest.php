<?php

use App\Models\AutomationTestResult;
use App\Models\Bugreport;
use App\Models\Checklist;
use App\Models\DesignLink;
use App\Models\Documentation;
use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectFeature;
use App\Models\Release;
use App\Models\TestCase;
use App\Models\TestCommand;
use App\Models\TestLink;
use App\Models\TestRun;
use App\Models\TestSuite;
use App\Models\TestUser;
use App\Models\User;

test('project search requires authentication', function () {
    $project = Project::factory()->create();

    $this->getJson(route('projects.search', $project).'?q=test')
        ->assertUnauthorized();
});

test('project search requires authorization', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=test')
        ->assertForbidden();
});

test('project search requires at least 2 characters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=a')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

test('project search finds test suites by name', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestSuite::factory()->create([
        'project_id' => $project->id,
        'name' => 'Authentication Suite',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Authentication')
        ->assertSuccessful();

    $data = $response->json();
    expect($data['total'])->toBeGreaterThanOrEqual(1);

    $types = collect($data['results'])->pluck('type')->all();
    expect($types)->toContain('test_suites');
});

test('project search finds test suites by description', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestSuite::factory()->create([
        'project_id' => $project->id,
        'name' => 'Suite A',
        'description' => 'Covers login functionality',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=login')
        ->assertSuccessful();

    $data = $response->json();
    $suiteGroup = collect($data['results'])->firstWhere('type', 'test_suites');
    expect($suiteGroup)->not->toBeNull();
    expect($suiteGroup['items'][0]['title'])->toBe('Suite A');
});

test('project search finds test cases by title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);

    TestCase::factory()->create([
        'test_suite_id' => $suite->id,
        'title' => 'Verify login flow works correctly',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=login')
        ->assertSuccessful();

    $data = $response->json();
    $caseGroup = collect($data['results'])->firstWhere('type', 'test_cases');
    expect($caseGroup)->not->toBeNull();
    expect($caseGroup['items'][0]['title'])->toBe('Verify login flow works correctly');
});

test('project search finds checklists by name', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Checklist::factory()->create([
        'project_id' => $project->id,
        'name' => 'Deployment Checklist',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Deployment')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'checklists');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('Deployment Checklist');
});

test('project search finds test runs by name', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestRun::factory()->create([
        'project_id' => $project->id,
        'name' => 'Sprint 42 Regression',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Regression')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'test_runs');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('Sprint 42 Regression');
});

test('project search finds bug reports by title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Bugreport::factory()->create([
        'project_id' => $project->id,
        'title' => 'Login button broken on mobile',
        'reported_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Login')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'bugreports');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('Login button broken on mobile');
});

test('project search finds documentations by title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Documentation::factory()->create([
        'project_id' => $project->id,
        'title' => 'API Authentication Guide',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Authentication')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'documentations');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('API Authentication Guide');
});

test('project search returns multiple entity types for cross-cutting queries', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestSuite::factory()->create([
        'project_id' => $project->id,
        'name' => 'Login Tests',
    ]);

    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    TestCase::factory()->create([
        'test_suite_id' => $suite->id,
        'title' => 'Test login validation',
    ]);

    Bugreport::factory()->create([
        'project_id' => $project->id,
        'title' => 'Login page crash',
        'reported_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=login')
        ->assertSuccessful();

    $data = $response->json();
    $types = collect($data['results'])->pluck('type')->all();

    expect($types)->toContain('test_suites');
    expect($types)->toContain('test_cases');
    expect($types)->toContain('bugreports');
    expect($data['total'])->toBeGreaterThanOrEqual(3);
});

test('project search does not leak data from other projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create(['user_id' => $user->id]);

    TestSuite::factory()->create([
        'project_id' => $otherProject->id,
        'name' => 'Secret Suite',
    ]);

    Bugreport::factory()->create([
        'project_id' => $otherProject->id,
        'title' => 'Secret Bug',
        'reported_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Secret')
        ->assertSuccessful();

    $data = $response->json();
    expect($data['total'])->toBe(0);
    expect($data['results'])->toBeEmpty();
});

test('project search limits results to 10 per type', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    for ($i = 0; $i < 15; $i++) {
        Checklist::factory()->create([
            'project_id' => $project->id,
            'name' => "Searchable checklist $i",
        ]);
    }

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Searchable')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'checklists');
    expect($group)->not->toBeNull();
    expect(count($group['items']))->toBeLessThanOrEqual(10);
});

test('project search returns empty results for non-matching queries', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestSuite::factory()->create([
        'project_id' => $project->id,
        'name' => 'Authentication Suite',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=zznonexistent')
        ->assertSuccessful();

    $data = $response->json();
    expect($data['total'])->toBe(0);
    expect($data['results'])->toBeEmpty();
});

test('project search finds releases by name', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Release::factory()->create([
        'project_id' => $project->id,
        'name' => 'Holiday Hotfix',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Holiday')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'releases');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toContain('Holiday Hotfix');
});

test('project search finds releases by version', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Release::factory()->create([
        'project_id' => $project->id,
        'version' => '9.8.7',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=9.8.7')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'releases');
    expect($group)->not->toBeNull();
});

test('project search finds design links by title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    DesignLink::factory()->create([
        'project_id' => $project->id,
        'title' => 'Dashboard Wireframe',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Wireframe')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'design_links');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('Dashboard Wireframe');
});

test('project search finds notes by title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Note::factory()->create([
        'project_id' => $project->id,
        'title' => 'Sprint Retrospective Notes',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Retrospective')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'notes');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('Sprint Retrospective Notes');
});

test('project search finds notes by content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Note::factory()->create([
        'project_id' => $project->id,
        'title' => 'Meeting Notes',
        'content' => 'Discussed microservices architecture',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=microservices')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'notes');
    expect($group)->not->toBeNull();
});

test('project search finds test data users by name', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestUser::factory()->create([
        'project_id' => $project->id,
        'name' => 'Staging Admin Account',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Staging Admin')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'test_data_users');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('Staging Admin Account');
});

test('project search finds test data commands by description', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestCommand::factory()->create([
        'project_id' => $project->id,
        'description' => 'Seed production database',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Seed production')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'test_data_commands');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('Seed production database');
});

test('project search finds test data links by description', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestLink::factory()->create([
        'project_id' => $project->id,
        'description' => 'Grafana monitoring dashboard',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Grafana')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'test_data_links');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('Grafana monitoring dashboard');
});

test('project search finds project features by name', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    ProjectFeature::factory()->create([
        'project_id' => $project->id,
        'name' => 'Two Factor Authentication',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Two Factor')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'project_features');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('Two Factor Authentication');
});

test('project search excludes inactive project features', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    ProjectFeature::factory()->create([
        'project_id' => $project->id,
        'name' => 'Deprecated Feature XYZ',
        'is_active' => false,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Deprecated')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'project_features');
    expect($group)->toBeNull();
});

test('project search finds automation results by test name', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    AutomationTestResult::factory()->create([
        'project_id' => $project->id,
        'test_name' => 'should validate checkout flow',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=checkout flow')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'automation_results');
    expect($group)->not->toBeNull();
    expect($group['items'][0]['title'])->toBe('should validate checkout flow');
});

test('project search finds automation results by error message', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    AutomationTestResult::factory()->failed()->create([
        'project_id' => $project->id,
        'test_name' => 'login test',
        'error_message' => 'Element not found: #submit-button',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=submit-button')
        ->assertSuccessful();

    $data = $response->json();
    $group = collect($data['results'])->firstWhere('type', 'automation_results');
    expect($group)->not->toBeNull();
});

test('project search returns correct response structure', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestSuite::factory()->create([
        'project_id' => $project->id,
        'name' => 'Auth Suite',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('projects.search', $project).'?q=Auth')
        ->assertSuccessful();

    $response->assertJsonStructure([
        'query',
        'results' => [
            '*' => [
                'type',
                'label',
                'count',
                'items' => [
                    '*' => [
                        'id',
                        'title',
                        'subtitle',
                        'badge',
                        'url',
                    ],
                ],
            ],
        ],
        'total',
    ]);
});
