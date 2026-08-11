<?php

use App\Models\Bugreport;
use App\Models\Checklist;
use App\Models\CoverageAnalysis;
use App\Models\Project;
use App\Models\Release;
use App\Models\TestCase;
use App\Models\TestCaseNote;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\Workspace;
use App\Services\AchievementService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->service = app(AchievementService::class);
});

function unlockedKeys(User $user): array
{
    return $user->achievements()->pluck('achievement_key')->all();
}

test('first blood unlocks after the first bug report', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Bugreport::factory()->create(['project_id' => $project->id, 'reported_by' => $user->id]);

    $this->service->checkBugAchievements($user);

    expect(unlockedKeys($user))->toContain('first-blood')
        ->and(unlockedKeys($user))->not->toContain('bug-hunter');
});

test('bug hunter unlocks at 10 bugs and exterminator at 50', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Bugreport::factory()->count(10)->create(['project_id' => $project->id, 'reported_by' => $user->id]);

    $this->service->checkBugAchievements($user);

    expect(unlockedKeys($user))->toContain('bug-hunter')
        ->and(unlockedKeys($user))->not->toContain('exterminator');

    Bugreport::factory()->count(40)->create(['project_id' => $project->id, 'reported_by' => $user->id]);

    $this->service->checkBugAchievements($user);

    expect(unlockedKeys($user))->toContain('exterminator');
});

test('speed demon unlocks when a bug is resolved within an hour via the real update route', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $user->id,
        'status' => 'to_do',
    ]);

    $this->travel(30)->minutes();

    $this->actingAs($user)
        ->put(route('bugreports.update', [$project, $bugreport]), [
            'title' => $bugreport->title,
            'description' => $bugreport->description,
            'severity' => $bugreport->severity,
            'priority' => $bugreport->priority,
            'status' => 'done',
        ])
        ->assertRedirect();

    expect($bugreport->fresh()->resolved_at)->not->toBeNull();
    expect(unlockedKeys($user))->toContain('speed-demon');
});

test('speed demon does not unlock when resolution takes over an hour', function () {
    $user = User::factory()->create();
    $bugreport = Bugreport::factory()->create(['reported_by' => $user->id]);
    $bugreport->resolved_at = $bugreport->created_at->addHours(2);
    $bugreport->save();

    $this->service->checkSpeedDemon($bugreport->fresh());

    expect(unlockedKeys($user))->not->toContain('speed-demon');
});

test('checklist champion unlocks at 25 completed checklists', function () {
    $user = User::factory()->create();
    Checklist::factory()->count(25)->create(['completed_by' => $user->id]);

    $this->service->checkChecklistChampion($user);

    expect(unlockedKeys($user))->toContain('checklist-champion');
});

test('checklist controller marks a checklist completed once every row is checked off', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $checklist = Checklist::factory()->create(['project_id' => $project->id]);
    $row = $checklist->rows()->create(['data' => ['status' => false], 'order' => 0, 'row_type' => 'normal']);

    $this->actingAs($user)
        ->patch(route('checklists.patch-rows', [$project, $checklist]), [
            'rows' => [
                ['id' => $row->id, 'data' => ['status' => true], 'order' => 0, 'row_type' => 'normal'],
            ],
        ])
        ->assertRedirect();

    expect($checklist->fresh()->completed_by)->toBe($user->id);
});

test('detail oriented unlocks after 20 test cases with substantial notes', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 20; $i++) {
        TestCaseNote::create([
            'test_case_id' => TestCase::factory()->create()->id,
            'updated_by' => $user->id,
            'content' => 'This is a sufficiently detailed note about this test case.',
        ]);
    }

    $this->service->checkDetailOriented($user);

    expect(unlockedKeys($user))->toContain('detail-oriented');
});

test('clickup connector and grafana guru unlock directly', function () {
    $user = User::factory()->create();

    $this->service->checkClickupConnector($user);
    $this->service->checkGrafanaGuru($user);

    expect(unlockedKeys($user))->toContain('clickup-connector')
        ->and(unlockedKeys($user))->toContain('grafana-guru');
});

test('project starter unlocks only for the first project', function () {
    $user = User::factory()->create();
    Project::factory()->create(['user_id' => $user->id]);

    $this->service->checkProjectStarter($user);
    expect(unlockedKeys($user))->toContain('project-starter');

    UserAchievement::query()->delete();
    Project::factory()->create(['user_id' => $user->id]);

    $this->service->checkProjectStarter($user);
    expect(unlockedKeys($user))->toBe([]);
});

test('team player unlocks with 5 owned projects', function () {
    $user = User::factory()->create();
    Project::factory()->count(5)->create(['user_id' => $user->id]);

    $this->service->checkTeamPlayer($user);

    expect(unlockedKeys($user))->toContain('team-player');
});

test('team player counts projects accessible via workspace membership', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id, ['role' => 'member']);
    Project::factory()->count(5)->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $this->service->checkTeamPlayer($member);

    expect(unlockedKeys($member))->toContain('team-player');
});

test('perfectionist unlocks at 100 percent coverage with a release', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Release::factory()->create(['project_id' => $project->id]);
    CoverageAnalysis::factory()->create(['project_id' => $project->id, 'overall_coverage' => 100]);

    $this->service->checkPerfectionist($user, $project->fresh());

    expect(unlockedKeys($user))->toContain('perfectionist');
});

test('perfectionist does not unlock without a release', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    CoverageAnalysis::factory()->create(['project_id' => $project->id, 'overall_coverage' => 100]);

    $this->service->checkPerfectionist($user, $project->fresh());

    expect(unlockedKeys($user))->not->toContain('perfectionist');
});

test('streak master unlocks after 7 consecutive days of activity', function () {
    $user = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));

    for ($i = 0; $i < 7; $i++) {
        $this->service->trackDailyActivity($user->fresh());
        Carbon::setTestNow(Carbon::now()->addDay());
    }

    Carbon::setTestNow();

    expect(unlockedKeys($user))->toContain('streak-master');
});

test('night owl and early bird count distinct days in the right hour windows', function () {
    $user = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-01-01 02:00:00'));
    for ($i = 0; $i < 10; $i++) {
        $this->service->trackDailyActivity($user->fresh());
        Carbon::setTestNow(Carbon::now()->addDay());
    }

    Carbon::setTestNow(Carbon::parse('2026-02-01 06:00:00'));
    for ($i = 0; $i < 10; $i++) {
        $this->service->trackDailyActivity($user->fresh());
        Carbon::setTestNow(Carbon::now()->addDay());
    }

    Carbon::setTestNow();

    expect(unlockedKeys($user))->toContain('night-owl')
        ->and(unlockedKeys($user))->toContain('early-bird');
});

test('legend unlocks automatically once all other 22 achievements are unlocked', function () {
    $user = User::factory()->create();

    $keys = [
        'first-blood', 'bug-hunter', 'exterminator', 'checklist-champion',
        'detail-oriented', 'clickup-connector', 'speed-demon', 'team-player',
        'grafana-guru', 'project-starter', 'perfectionist', 'night-owl',
        'early-bird', 'marathon', 'first-test-suite', 'first-checklist',
        'first-document', 'first-note', 'first-test-run', 'first-release',
        'first-ai-generation',
    ];

    foreach ($keys as $key) {
        $this->service->unlock($user, $key);
    }

    expect(unlockedKeys($user))->not->toContain('legend');

    $this->service->unlock($user, 'streak-master');

    expect(unlockedKeys($user))->toContain('legend');
});

test('marathon unlocks via the focus session ping endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('focus-sessions.ping'))
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(unlockedKeys($user))->toContain('marathon');
});

test('first-x achievement checks are idempotent — unlock only fires once', function () {
    $user = User::factory()->create();

    $this->service->checkFirstTestSuite($user);
    $this->service->checkFirstTestSuite($user);

    expect(UserAchievement::where('user_id', $user->id)->where('achievement_key', 'first-test-suite')->count())->toBe(1);
});

test('creating the first test suite unlocks Suite Starter', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('test-suites.store', $project), ['name' => 'Smoke Suite'])
        ->assertRedirect();

    expect(unlockedKeys($user))->toContain('first-test-suite');
});

test('creating the first checklist unlocks Checklist Creator', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('checklists.store', $project), ['name' => 'Release Checklist'])
        ->assertRedirect();

    expect(unlockedKeys($user))->toContain('first-checklist');
});

test('creating the first documentation unlocks Documentarian', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('documentations.store', $project), ['title' => 'API Overview'])
        ->assertRedirect();

    expect(unlockedKeys($user))->toContain('first-document');
});

test('creating the first note unlocks Note Taker', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('projects.notes.store', $project), ['content' => 'Remember to check X'])
        ->assertRedirect();

    expect(unlockedKeys($user))->toContain('first-note');
});

test('creating the first test run unlocks Test Runner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $testSuite = TestSuite::factory()->create(['project_id' => $project->id]);
    $testCase = TestCase::factory()->create(['test_suite_id' => $testSuite->id]);

    $this->actingAs($user)
        ->post(route('test-runs.store', $project), [
            'name' => 'Regression Run',
            'test_case_ids' => [$testCase->id],
        ])
        ->assertRedirect();

    expect(unlockedKeys($user))->toContain('first-test-run');
});

test('creating the first release unlocks Release Manager', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('releases.store', $project), [
            'version' => '1.0.0',
            'name' => 'Initial Release',
        ])
        ->assertRedirect();

    expect(unlockedKeys($user))->toContain('first-release');
});

test('first ai generation check unlocks AI Pioneer', function () {
    $user = User::factory()->create();

    $this->service->checkFirstAiGeneration($user);

    expect(unlockedKeys($user))->toContain('first-ai-generation');
});
