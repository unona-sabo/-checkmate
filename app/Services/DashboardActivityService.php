<?php

namespace App\Services;

use App\Models\Bugreport;
use App\Models\Checklist;
use App\Models\CoverageAnalysis;
use App\Models\Release;
use App\Models\ReleaseFeature;
use App\Models\TestCase as ProjectTestCase;
use App\Models\TestRun;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardActivityService
{
    /**
     * @return array{
     *     last_day: array<string, int>,
     *     last_day_events: list<array<string, mixed>>,
     *     week: array<string, int>,
     *     week_previous: array<string, int>,
     *     projects: list<array{id: int, name: string, counts: array<string, int>, total: int, recent: list<array<string, mixed>>}>,
     *     achievements: list<array{key: string, unlocked_at: string}>,
     * }
     */
    public function build(Workspace $workspace, User $user): array
    {
        $projects = $workspace->projects()->get(['id', 'name'])->keyBy('id');
        $projectIds = $projects->keys();

        $lastDayStart = now()->subDay();
        $weekStart = now()->subDays(7)->startOfDay();
        $previousWeekStart = now()->subDays(14)->startOfDay();
        $now = now();

        $events = $this->collectEvents($projects, $projectIds, $weekStart, $now);
        $lastDayEvents = $events->filter(fn (array $event) => $event['timestamp']->gte($lastDayStart))->values();

        return [
            'last_day' => $this->totalsFor($projectIds, $lastDayStart, $now),
            'last_day_events' => $lastDayEvents->take(20)->map(fn (array $event) => $this->serializeEvent($event))->all(),
            'week' => $this->totalsFor($projectIds, $weekStart, $now),
            'week_previous' => $this->totalsFor($projectIds, $previousWeekStart, $weekStart),
            'projects' => $this->projectBreakdown($projects, $projectIds, $weekStart, $now, $events),
            'achievements' => $this->recentAchievements($user, $weekStart),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
            'checklists' => 0,
            'checklists_completed' => 0,
            'bugreports' => 0,
            'test_runs_completed' => 0,
            'releases_opened' => 0,
            'releases_released' => 0,
            'features_added' => 0,
            'test_cases_added' => 0,
            'ai_analyses' => 0,
        ];
    }

    /**
     * @param  Collection<int, int>  $projectIds
     * @return array<string, int>
     */
    private function totalsFor(Collection $projectIds, CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            'checklists' => Checklist::query()->whereIn('project_id', $projectIds)->whereBetween('created_at', [$from, $to])->count(),
            'checklists_completed' => Checklist::query()->whereIn('project_id', $projectIds)->whereNotNull('completed_at')->whereBetween('completed_at', [$from, $to])->count(),
            'bugreports' => Bugreport::query()->whereIn('project_id', $projectIds)->whereBetween('created_at', [$from, $to])->count(),
            'test_runs_completed' => TestRun::query()->whereIn('project_id', $projectIds)->whereBetween('completed_at', [$from, $to])->count(),
            'releases_opened' => Release::query()->whereIn('project_id', $projectIds)->whereBetween('created_at', [$from, $to])->count(),
            'releases_released' => Release::query()->whereIn('project_id', $projectIds)->where('status', 'released')->whereBetween('updated_at', [$from, $to])->count(),
            'features_added' => ReleaseFeature::query()->whereHas('release', fn ($q) => $q->whereIn('project_id', $projectIds))->whereBetween('created_at', [$from, $to])->count(),
            'test_cases_added' => ProjectTestCase::query()->whereHas('testSuite', fn ($q) => $q->whereIn('project_id', $projectIds))->whereBetween('created_at', [$from, $to])->count(),
            'ai_analyses' => CoverageAnalysis::query()->whereIn('project_id', $projectIds)->whereBetween('analyzed_at', [$from, $to])->count(),
        ];
    }

    /**
     * @param  Collection<int, \App\Models\Project>  $projects
     * @param  Collection<int, int>  $projectIds
     * @param  Collection<int, array<string, mixed>>  $events
     * @return list<array{id: int, name: string, counts: array<string, int>, total: int, recent: list<array<string, mixed>>}>
     */
    private function projectBreakdown(Collection $projects, Collection $projectIds, CarbonInterface $from, CarbonInterface $to, Collection $events): array
    {
        $counts = $projectIds->mapWithKeys(fn (int $id) => [$id => $this->emptyCounts()])->all();

        $this->tally($counts, 'checklists', Checklist::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('project_id, COUNT(*) as aggregate')
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id'));

        $this->tally($counts, 'checklists_completed', Checklist::query()
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('project_id, COUNT(*) as aggregate')
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id'));

        $this->tally($counts, 'bugreports', Bugreport::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('project_id, COUNT(*) as aggregate')
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id'));

        $this->tally($counts, 'test_runs_completed', TestRun::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('project_id, COUNT(*) as aggregate')
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id'));

        $this->tally($counts, 'releases_opened', Release::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('project_id, COUNT(*) as aggregate')
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id'));

        $this->tally($counts, 'releases_released', Release::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', 'released')
            ->whereBetween('updated_at', [$from, $to])
            ->selectRaw('project_id, COUNT(*) as aggregate')
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id'));

        $this->tally($counts, 'features_added', ReleaseFeature::query()
            ->join('releases', 'releases.id', '=', 'release_features.release_id')
            ->whereIn('releases.project_id', $projectIds)
            ->whereBetween('release_features.created_at', [$from, $to])
            ->selectRaw('releases.project_id as project_id, COUNT(*) as aggregate')
            ->groupBy('releases.project_id')
            ->pluck('aggregate', 'project_id'));

        $this->tally($counts, 'test_cases_added', ProjectTestCase::query()
            ->join('test_suites', 'test_suites.id', '=', 'test_cases.test_suite_id')
            ->whereIn('test_suites.project_id', $projectIds)
            ->whereBetween('test_cases.created_at', [$from, $to])
            ->selectRaw('test_suites.project_id as project_id, COUNT(*) as aggregate')
            ->groupBy('test_suites.project_id')
            ->pluck('aggregate', 'project_id'));

        $this->tally($counts, 'ai_analyses', CoverageAnalysis::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('analyzed_at', [$from, $to])
            ->selectRaw('project_id, COUNT(*) as aggregate')
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id'));

        return collect($counts)
            ->map(function (array $projectCounts, int $id) use ($projects, $events) {
                return [
                    'id' => $id,
                    'name' => $projects[$id]->name,
                    'counts' => $projectCounts,
                    'total' => array_sum($projectCounts),
                    'recent' => $events->where('project_id', $id)
                        ->take(50)
                        ->map(fn (array $event) => $this->serializeEvent($event))
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $row) => $row['total'] > 0)
            ->sortByDesc('total')
            ->values()
            ->take(8)
            ->all();
    }

    /**
     * @param  array<int, array<string, int>>  $counts
     * @param  Collection<int, int>  $aggregates
     */
    private function tally(array &$counts, string $key, Collection $aggregates): void
    {
        foreach ($aggregates as $projectId => $count) {
            if (isset($counts[$projectId])) {
                $counts[$projectId][$key] = (int) $count;
            }
        }
    }

    /**
     * Build a normalized, chronologically-sorted feed of individual workspace
     * events (checklists created and completed, bugs reported, test runs
     * completed, AI coverage analyses, release opens and status changes,
     * features added, test cases added) for the given window. Each event
     * corresponds 1:1 with a unit counted in totalsFor()/projectBreakdown(),
     * so a project's total count always matches the length of its event list.
     *
     * @param  Collection<int, \App\Models\Project>  $projects
     * @param  Collection<int, int>  $projectIds
     * @return Collection<int, array<string, mixed>>
     */
    private function collectEvents(Collection $projects, Collection $projectIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $events = collect();

        Bugreport::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('created_at', [$from, $to])
            ->with('reporter:id,name')
            ->get()
            ->each(function (Bugreport $bug) use (&$events, $projects) {
                $events->push([
                    'type' => 'bug',
                    'project_id' => $bug->project_id,
                    'project_name' => $projects[$bug->project_id]->name,
                    'timestamp' => $bug->created_at,
                    'title' => "BUG-{$bug->id} \"{$bug->title}\" marked {$bug->severity}",
                    'meta' => array_values(array_filter([
                        $bug->reporter?->name,
                        $bug->clickup_task_id ? 'via ClickUp sync' : 'Manually reported',
                    ])),
                    'tag' => $bug->severity,
                    'url' => "/projects/{$bug->project_id}/bugreports/{$bug->id}",
                ]);
            });

        TestRun::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->get()
            ->each(function (TestRun $run) use (&$events, $projects) {
                $stats = $run->stats ?? [];
                $total = array_sum($stats);
                $passed = $stats['passed'] ?? 0;
                $failed = $stats['failed'] ?? 0;

                $events->push([
                    'type' => 'test_run',
                    'project_id' => $run->project_id,
                    'project_name' => $projects[$run->project_id]->name,
                    'timestamp' => $run->completed_at,
                    'title' => "Test run \"{$run->name}\" completed, {$passed}/{$total} passed",
                    'meta' => array_values(array_filter([
                        $failed > 0 ? $failed.($failed === 1 ? ' failure' : ' failures').' flagged' : null,
                    ])),
                    'tag' => null,
                    'url' => "/projects/{$run->project_id}/test-runs/{$run->id}",
                ]);
            });

        CoverageAnalysis::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('analyzed_at', [$from, $to])
            ->orderBy('analyzed_at')
            ->get()
            ->groupBy('project_id')
            ->each(function (Collection $analyses, int $projectId) use (&$events, $projects, $from) {
                $previous = CoverageAnalysis::query()
                    ->where('project_id', $projectId)
                    ->where('analyzed_at', '<', $from)
                    ->orderByDesc('analyzed_at')
                    ->first();

                foreach ($analyses as $analysis) {
                    $coverageDelta = $previous ? $analysis->overall_coverage - $previous->overall_coverage : null;
                    $gapsCount = $analysis->gaps_count;

                    $events->push([
                        'type' => 'coverage',
                        'project_id' => $projectId,
                        'project_name' => $projects[$projectId]->name,
                        'timestamp' => $analysis->analyzed_at,
                        'title' => "AI coverage analysis found {$gapsCount} new ".($gapsCount === 1 ? 'gap' : 'gaps')." in {$projects[$projectId]->name}",
                        'meta' => array_values(array_filter([
                            "Coverage {$analysis->overall_coverage}%",
                            $coverageDelta !== null ? sprintf('%+d%% since last run', $coverageDelta) : null,
                        ])),
                        'tag' => 'insight',
                        'url' => "/projects/{$projectId}/test-coverage?history={$analysis->id}",
                    ]);

                    $previous = $analysis;
                }
            });

        Checklist::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('created_at', [$from, $to])
            ->withCount('rows')
            ->get()
            ->each(function (Checklist $checklist) use (&$events, $projects) {
                $events->push([
                    'type' => 'checklist_created',
                    'project_id' => $checklist->project_id,
                    'project_name' => $projects[$checklist->project_id]->name,
                    'timestamp' => $checklist->created_at,
                    'title' => "Checklist \"{$checklist->name}\" created",
                    'meta' => array_values(array_filter([
                        $checklist->rows_count > 0 ? "{$checklist->rows_count} items" : null,
                    ])),
                    'tag' => null,
                    'url' => "/projects/{$checklist->project_id}/checklists/{$checklist->id}",
                ]);
            });

        Checklist::query()
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->with('completedBy:id,name')
            ->withCount('rows')
            ->get()
            ->each(function (Checklist $checklist) use (&$events, $projects) {
                $events->push([
                    'type' => 'checklist',
                    'project_id' => $checklist->project_id,
                    'project_name' => $projects[$checklist->project_id]->name,
                    'timestamp' => $checklist->completed_at,
                    'title' => "Checklist \"{$checklist->name}\" completed".($checklist->completedBy ? " by {$checklist->completedBy->name}" : ''),
                    'meta' => array_values(array_filter([
                        $checklist->rows_count > 0 ? "{$checklist->rows_count} items" : null,
                    ])),
                    'tag' => null,
                    'url' => "/projects/{$checklist->project_id}/checklists/{$checklist->id}",
                ]);
            });

        // One event per "opened" (created) and per "released" (status =
        // released, touched) occurrence — kept in exact lockstep with
        // totalsFor()'s releases_opened/releases_released so a release that
        // both opened and shipped within the window contributes two events,
        // matching its two counted units, and a release merely edited for
        // unrelated reasons (e.g. decision notes) contributes none.
        Release::query()
            ->whereIn('project_id', $projectIds)
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('created_at', [$from, $to])
                    ->orWhere(function ($query) use ($from, $to) {
                        $query->where('status', 'released')->whereBetween('updated_at', [$from, $to]);
                    });
            })
            ->get()
            ->each(function (Release $release) use (&$events, $projects, $from, $to) {
                $daysToTarget = $release->planned_date
                    ? (int) ceil((strtotime($release->planned_date->toDateString()) - strtotime(now()->toDateString())) / 86400)
                    : null;

                $blockers = $release->checklistItems()
                    ->where('is_blocker', true)
                    ->where('status', '!=', 'completed')
                    ->count();

                $meta = array_values(array_filter([
                    $daysToTarget !== null && $daysToTarget >= 0 ? "{$daysToTarget} days until target date" : null,
                    $blockers > 0 ? "{$blockers} open ".($blockers === 1 ? 'blocker' : 'blockers') : null,
                ]));

                $url = "/projects/{$release->project_id}/releases/{$release->id}";

                if ($release->created_at->between($from, $to)) {
                    $events->push([
                        'type' => 'release',
                        'project_id' => $release->project_id,
                        'project_name' => $projects[$release->project_id]->name,
                        'timestamp' => $release->created_at,
                        'title' => "Release {$release->version} opened",
                        'meta' => $meta,
                        'tag' => $release->status,
                        'url' => $url,
                    ]);
                }

                if ($release->status === 'released' && $release->updated_at->between($from, $to)) {
                    $events->push([
                        'type' => 'release',
                        'project_id' => $release->project_id,
                        'project_name' => $projects[$release->project_id]->name,
                        'timestamp' => $release->updated_at,
                        'title' => "Release {$release->version} moved to ".Str::headline($release->status),
                        'meta' => $meta,
                        'tag' => $release->status,
                        'url' => $url,
                    ]);
                }
            });

        ReleaseFeature::query()
            ->join('releases', 'releases.id', '=', 'release_features.release_id')
            ->whereIn('releases.project_id', $projectIds)
            ->whereBetween('release_features.created_at', [$from, $to])
            ->with('release:id,project_id,version,name')
            ->select('release_features.*')
            ->get()
            ->each(function (ReleaseFeature $feature) use (&$events, $projects) {
                $projectId = $feature->release->project_id;

                $events->push([
                    'type' => 'feature',
                    'project_id' => $projectId,
                    'project_name' => $projects[$projectId]->name,
                    'timestamp' => $feature->created_at,
                    'title' => "Feature \"{$feature->feature_name}\" added to release {$feature->release->version}",
                    'meta' => [],
                    'tag' => null,
                    'url' => "/projects/{$projectId}/releases/{$feature->release_id}",
                ]);
            });

        ProjectTestCase::query()
            ->join('test_suites', 'test_suites.id', '=', 'test_cases.test_suite_id')
            ->whereIn('test_suites.project_id', $projectIds)
            ->whereBetween('test_cases.created_at', [$from, $to])
            ->with('testSuite:id,project_id,name')
            ->select('test_cases.*')
            ->get()
            ->each(function (ProjectTestCase $testCase) use (&$events, $projects) {
                $projectId = $testCase->testSuite->project_id;

                $events->push([
                    'type' => 'test_case',
                    'project_id' => $projectId,
                    'project_name' => $projects[$projectId]->name,
                    'timestamp' => $testCase->created_at,
                    'title' => "Test case \"{$testCase->title}\" added to {$testCase->testSuite->name}",
                    'meta' => array_values(array_filter([
                        ucfirst($testCase->priority ?? ''),
                    ])),
                    'tag' => null,
                    'url' => "/projects/{$projectId}/test-suites/{$testCase->test_suite_id}/test-cases/{$testCase->id}",
                ]);
            });

        return $events->sortByDesc(fn (array $event) => $event['timestamp'])->values();
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function serializeEvent(array $event): array
    {
        return [
            'type' => $event['type'],
            'project_id' => $event['project_id'],
            'project_name' => $event['project_name'],
            'timestamp' => $event['timestamp']->toIso8601String(),
            'title' => $event['title'],
            'meta' => $event['meta'],
            'tag' => $event['tag'],
            'url' => $event['url'],
        ];
    }

    /**
     * @return list<array{key: string, unlocked_at: string}>
     */
    private function recentAchievements(User $user, CarbonInterface $since): array
    {
        return $user->achievements()
            ->where('unlocked_at', '>=', $since)
            ->orderByDesc('unlocked_at')
            ->get(['achievement_key', 'unlocked_at'])
            ->map(fn ($achievement) => [
                'key' => $achievement->achievement_key,
                'unlocked_at' => $achievement->unlocked_at->toIso8601String(),
            ])
            ->all();
    }
}
