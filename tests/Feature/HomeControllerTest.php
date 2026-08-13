<?php

use App\Models\Bugreport;
use App\Models\Checklist;
use App\Models\ChecklistRow;
use App\Models\CoverageAnalysis;
use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseChecklistItem;
use App\Models\ReleaseFeature;
use App\Models\TestCase as ProjectTestCase;
use App\Models\TestRun;
use App\Models\TestSuite;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

function setUpWorkspaceUser(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
    ]);

    return [$user, $workspace, $project];
}

test('dashboard page renders for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('dashboard page requires authentication', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('dashboard has no activity when user has no workspace', function () {
    $user = User::factory()->create(['current_workspace_id' => null]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('activity', null)
    );
});

test('dashboard counts checklists created in the last 24 hours and in the last 7 days', function () {
    [$user, , $project] = setUpWorkspaceUser();

    Checklist::factory()->create(['project_id' => $project->id, 'created_at' => now()->subHours(2)]);
    Checklist::factory()->create(['project_id' => $project->id, 'created_at' => now()->subDays(3)]);
    Checklist::factory()->create(['project_id' => $project->id, 'created_at' => now()->subDays(30)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day.checklists', 1)
        ->where('activity.week.checklists', 2)
    );
});

test('dashboard counts bugreports, completed test runs, releases and features', function () {
    [$user, , $project] = setUpWorkspaceUser();

    Bugreport::factory()->create(['project_id' => $project->id, 'created_at' => now()->subHours(2)]);
    TestRun::factory()->create(['project_id' => $project->id, 'status' => 'completed', 'completed_at' => now()->subHours(2)]);
    $release = Release::factory()->create(['project_id' => $project->id, 'created_at' => now()->subHours(2)]);
    $releasedRecently = Release::factory()->create(['project_id' => $project->id, 'status' => 'released', 'created_at' => now()->subDays(10)]);
    DB::table('releases')
        ->where('id', $releasedRecently->id)
        ->update(['updated_at' => now()->subHours(2)]);
    ReleaseFeature::factory()->create(['release_id' => $release->id, 'created_at' => now()->subHours(2)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day.bugreports', 1)
        ->where('activity.last_day.test_runs_completed', 1)
        ->where('activity.last_day.releases_opened', 1)
        ->where('activity.last_day.releases_released', 1)
        ->where('activity.last_day.features_added', 1)
    );
});

test('dashboard only counts activity from projects in the current workspace', function () {
    [$user, , $project] = setUpWorkspaceUser();
    $otherProject = Project::factory()->create();

    Checklist::factory()->create(['project_id' => $project->id, 'created_at' => now()->subHours(2)]);
    Checklist::factory()->create(['project_id' => $otherProject->id, 'created_at' => now()->subHours(2)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day.checklists', 1)
    );
});

test('dashboard breaks down last 7 days activity by project', function () {
    [$user, , $project] = setUpWorkspaceUser();

    Checklist::factory()->count(2)->create(['project_id' => $project->id, 'created_at' => now()->subDays(2)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('activity.projects', 1)
        ->where('activity.projects.0.id', $project->id)
        ->where('activity.projects.0.counts.checklists', 2)
        ->where('activity.projects.0.total', 2)
    );
});

test('dashboard excludes projects with no recent activity', function () {
    [$user, , $project] = setUpWorkspaceUser();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('activity.projects', 0));
});

test('dashboard last day events include a bug report with severity and source', function () {
    [$user, , $project] = setUpWorkspaceUser();

    $bug = Bugreport::factory()->create([
        'project_id' => $project->id,
        'title' => 'Export button unresponsive on Safari',
        'severity' => 'critical',
        'clickup_task_id' => 'CU-123',
        'created_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('activity.last_day_events', 1)
        ->where('activity.last_day_events.0.type', 'bug')
        ->where('activity.last_day_events.0.tag', 'critical')
        ->where('activity.last_day_events.0.title', "BUG-{$bug->id} \"Export button unresponsive on Safari\" marked critical")
        ->where('activity.last_day_events.0.meta.1', 'via ClickUp sync')
    );
});

test('dashboard last day events include a completed test run with failure count', function () {
    [$user, , $project] = setUpWorkspaceUser();

    TestRun::factory()->create([
        'project_id' => $project->id,
        'name' => 'Checkout regression',
        'status' => 'completed',
        'stats' => ['passed' => 28, 'failed' => 3],
        'completed_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day_events.0.type', 'test_run')
        ->where('activity.last_day_events.0.title', 'Test run "Checkout regression" completed, 28/31 passed')
        ->where('activity.last_day_events.0.meta.0', '3 failures flagged')
    );
});

test('dashboard last day events include a completed checklist', function () {
    [$user, , $project] = setUpWorkspaceUser();

    $completer = User::factory()->create(['name' => 'Marketing Service']);
    $checklist = Checklist::factory()->create([
        'project_id' => $project->id,
        'name' => 'Pre-release smoke test',
        'created_at' => now()->subDays(10),
        'completed_by' => $completer->id,
        'completed_at' => now()->subHours(2),
    ]);
    ChecklistRow::query()->insert(
        array_fill(0, 18, ['checklist_id' => $checklist->id, 'created_at' => now(), 'updated_at' => now()])
    );

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day_events.0.type', 'checklist')
        ->where('activity.last_day_events.0.title', 'Checklist "Pre-release smoke test" completed by Marketing Service')
        ->where('activity.last_day_events.0.meta.0', '18 items')
    );
});

test('dashboard last day events include a release opening with blockers', function () {
    [$user, , $project] = setUpWorkspaceUser();

    $release = Release::factory()->create([
        'project_id' => $project->id,
        'version' => '2.14',
        'status' => 'testing',
        'planned_date' => now()->addDays(6)->toDateString(),
        'created_at' => now()->subHours(2),
    ]);
    ReleaseChecklistItem::factory()->create([
        'release_id' => $release->id,
        'is_blocker' => true,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day_events.0.type', 'release')
        ->where('activity.last_day_events.0.tag', 'testing')
        ->where('activity.last_day_events.0.title', 'Release 2.14 opened')
        ->where('activity.last_day_events.0.meta.0', '6 days until target date')
        ->where('activity.last_day_events.0.meta.1', '1 open blocker')
    );
});

test('dashboard last day events include a release being shipped', function () {
    [$user, , $project] = setUpWorkspaceUser();

    $release = Release::factory()->create([
        'project_id' => $project->id,
        'version' => '3.0',
        'status' => 'released',
        'created_at' => now()->subDays(10),
    ]);
    DB::table('releases')->where('id', $release->id)->update(['updated_at' => now()->subHours(2)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day_events.0.type', 'release')
        ->where('activity.last_day_events.0.title', 'Release 3.0 moved to Released')
    );
});

test('dashboard release event count matches releases_opened plus releases_released totals', function () {
    [$user, , $project] = setUpWorkspaceUser();

    // Opens and ships within the same window: should count (and appear) twice.
    $release = Release::factory()->create([
        'project_id' => $project->id,
        'status' => 'released',
        'created_at' => now()->subHours(3),
    ]);
    DB::table('releases')->where('id', $release->id)->update(['updated_at' => now()->subHours(1)]);

    // Touched for an unrelated reason (not opened in window, not released): should not count or appear.
    $untouched = Release::factory()->create([
        'project_id' => $project->id,
        'status' => 'testing',
        'created_at' => now()->subDays(20),
    ]);
    DB::table('releases')->where('id', $untouched->id)->update(['updated_at' => now()->subHours(1)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day.releases_opened', 1)
        ->where('activity.last_day.releases_released', 1)
        ->has('activity.last_day_events', 2)
    );
});

test('dashboard last day events include an AI coverage analysis with delta since last run', function () {
    [$user, , $project] = setUpWorkspaceUser();

    CoverageAnalysis::factory()->create([
        'project_id' => $project->id,
        'overall_coverage' => 74,
        'gaps_count' => 5,
        'analyzed_at' => now()->subDays(3),
    ]);
    CoverageAnalysis::factory()->create([
        'project_id' => $project->id,
        'overall_coverage' => 78,
        'gaps_count' => 2,
        'analyzed_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $latest = CoverageAnalysis::query()->latest('analyzed_at')->first();

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day_events.0.type', 'coverage')
        ->where('activity.last_day_events.0.tag', 'insight')
        ->where('activity.last_day_events.0.meta.0', 'Coverage 78%')
        ->where('activity.last_day_events.0.meta.1', '+4% since last run')
        ->where('activity.last_day_events.0.url', "/projects/{$project->id}/test-coverage?history={$latest->id}")
    );
});

test('dashboard last day events include a feature added to a release', function () {
    [$user, , $project] = setUpWorkspaceUser();

    $release = Release::factory()->create(['project_id' => $project->id, 'version' => '2.14']);
    DB::table('releases')->where('id', $release->id)->update(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]);
    ReleaseFeature::factory()->create([
        'release_id' => $release->id,
        'feature_name' => 'Dark Mode',
        'created_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day_events.0.type', 'feature')
        ->where('activity.last_day_events.0.title', 'Feature "Dark Mode" added to release 2.14')
        ->where('activity.last_day_events.0.url', "/projects/{$project->id}/releases/{$release->id}")
    );
});

test('dashboard last day events include a test case added to a suite', function () {
    [$user, , $project] = setUpWorkspaceUser();

    $suite = TestSuite::factory()->create(['project_id' => $project->id, 'name' => 'Checkout']);
    $testCase = ProjectTestCase::factory()->create([
        'test_suite_id' => $suite->id,
        'title' => 'Apply valid promo code',
        'priority' => 'high',
        'created_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day_events.0.type', 'test_case')
        ->where('activity.last_day_events.0.title', 'Test case "Apply valid promo code" added to Checkout')
        ->where('activity.last_day_events.0.meta.0', 'High')
        ->where('activity.last_day_events.0.url', "/projects/{$project->id}/test-suites/{$suite->id}/test-cases/{$testCase->id}")
    );
});

test('dashboard active projects recent list is not capped at three events', function () {
    [$user, , $project] = setUpWorkspaceUser();

    Bugreport::factory()->count(4)->create(['project_id' => $project->id, 'created_at' => now()->subHours(2)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('activity.projects.0.recent', 4));
});

test('dashboard active projects include recent events with links', function () {
    [$user, , $project] = setUpWorkspaceUser();

    $bug = Bugreport::factory()->create(['project_id' => $project->id, 'title' => 'Test bug', 'created_at' => now()->subHours(2)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('activity.projects.0.recent', 1)
        ->where('activity.projects.0.recent.0.title', fn ($title) => str_contains($title, 'Test bug'))
        ->where('activity.projects.0.recent.0.url', "/projects/{$project->id}/bugreports/{$bug->id}")
    );
});

test('dashboard event links point to the underlying record', function () {
    [$user, , $project] = setUpWorkspaceUser();

    $bug = Bugreport::factory()->create(['project_id' => $project->id, 'created_at' => now()->subHours(2)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.last_day_events.0.url', "/projects/{$project->id}/bugreports/{$bug->id}")
    );
});

test('dashboard counts new test cases and AI analyses over the last 7 days with week-over-week trend', function () {
    [$user, , $project] = setUpWorkspaceUser();

    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    ProjectTestCase::factory()->create(['test_suite_id' => $suite->id, 'created_at' => now()->subDays(2)]);
    ProjectTestCase::factory()->create(['test_suite_id' => $suite->id, 'created_at' => now()->subDays(10)]);

    CoverageAnalysis::factory()->create(['project_id' => $project->id, 'analyzed_at' => now()->subDays(2)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.week.test_cases_added', 1)
        ->where('activity.week_previous.test_cases_added', 1)
        ->where('activity.week.ai_analyses', 1)
    );
});

test('dashboard project total always matches the number of events in its recent list', function () {
    [$user, , $project] = setUpWorkspaceUser();

    // Checklist created AND completed in-window: 2 counted units, 2 events.
    $checklist = Checklist::factory()->create([
        'project_id' => $project->id,
        'created_at' => now()->subDays(3),
        'completed_at' => now()->subDays(1),
    ]);
    Bugreport::factory()->create(['project_id' => $project->id, 'created_at' => now()->subDays(2)]);
    TestRun::factory()->create(['project_id' => $project->id, 'status' => 'completed', 'completed_at' => now()->subDays(2)]);
    // Release opened AND released in-window: 2 counted units, 2 events.
    $release = Release::factory()->create([
        'project_id' => $project->id,
        'status' => 'released',
        'created_at' => now()->subDays(3),
    ]);
    DB::table('releases')->where('id', $release->id)->update(['updated_at' => now()->subDays(1)]);
    ReleaseFeature::factory()->create(['release_id' => $release->id, 'created_at' => now()->subDays(2)]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);
    ProjectTestCase::factory()->create(['test_suite_id' => $suite->id, 'created_at' => now()->subDays(2)]);
    CoverageAnalysis::factory()->create(['project_id' => $project->id, 'analyzed_at' => now()->subDays(2)]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('activity.projects.0.total', 9)
        ->has('activity.projects.0.recent', 9)
    );
});

test('dashboard shows achievements unlocked in the last 7 days', function () {
    [$user] = setUpWorkspaceUser();

    UserAchievement::create([
        'user_id' => $user->id,
        'achievement_key' => 'first-checklist',
        'unlocked_at' => now()->subDays(2),
    ]);
    UserAchievement::create([
        'user_id' => $user->id,
        'achievement_key' => 'legend',
        'unlocked_at' => now()->subDays(30),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('activity.achievements', 1)
        ->where('activity.achievements.0.key', 'first-checklist')
    );
});
