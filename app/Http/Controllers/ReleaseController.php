<?php

namespace App\Http\Controllers;

use App\Http\Requests\Release\LinkTestRunRequest;
use App\Http\Requests\Release\StoreReleaseChecklistItemRequest;
use App\Http\Requests\Release\StoreReleaseFeatureRequest;
use App\Http\Requests\Release\StoreReleaseRequest;
use App\Http\Requests\Release\UpdateReleaseChecklistItemRequest;
use App\Http\Requests\Release\UpdateReleaseFeatureRequest;
use App\Http\Requests\Release\UpdateReleaseRequest;
use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseChecklistItem;
use App\Models\ReleaseFeature;
use App\Models\TestRun;
use App\Services\ReleaseMetricsCalculator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseController extends Controller
{
    public function __construct(private ReleaseMetricsCalculator $metricsCalculator) {}

    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $releases = $project->releases()
            ->withCount([
                'features',
                'checklistItems',
                'checklistItems as completed_checklist_items_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->with('latestMetrics')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Release $release) {
                $totalItems = $release->checklist_items_count;
                $completedItems = $release->completed_checklist_items_count;

                return [
                    ...$release->toArray(),
                    'checklist_progress' => $totalItems > 0 ? (int) round(($completedItems / $totalItems) * 100) : 0,
                ];
            });

        return Inertia::render('Releases/Index', [
            'project' => $project,
            'releases' => $releases,
        ]);
    }

    public function store(StoreReleaseRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $release = $project->releases()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $this->createDefaultChecklistItems($release);

        return back();
    }

    public function show(Project $project, Release $release): Response
    {
        $this->authorize('view', $project);
        abort_unless($release->project_id === $project->id, 404);

        $release->load([
            'features',
            'checklistItems' => fn ($q) => $q->orderBy('category')->orderBy('order'),
            'checklistItems.assignee:id,name',
            'metricsSnapshots' => fn ($q) => $q->orderByDesc('snapshot_at')->limit(10),
            'testRuns',
            'creator:id,name',
        ]);

        // Auto-refresh metrics on every page load
        $snapshot = $this->metricsCalculator->createSnapshot($release);
        $health = $this->metricsCalculator->determineHealth($snapshot->toArray());
        $release->update(['health' => $health]);
        $release->setRelation('latestMetrics', $snapshot);

        $blockers = $release->checklistItems
            ->where('is_blocker', true)
            ->where('status', '!=', 'completed')
            ->count();

        $snapshotArray = $snapshot->toArray();
        $liveMetrics = [
            'readiness' => $this->metricsCalculator->calculateReadinessScore($release, $snapshotArray),
            'blockers_and_risks' => $this->metricsCalculator->getBlockersAndRisks($release, $snapshotArray),
            'comparison' => $this->metricsCalculator->compareWithPreviousRelease($release, $snapshotArray),
        ];

        return Inertia::render('Releases/Show', [
            'project' => $project,
            'release' => $release,
            'blockers' => $blockers,
            'liveMetrics' => $liveMetrics,
            'projectFeatures' => Inertia::defer(fn () => $project->features()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'module']), 'sidebar'),
            'projectTestRuns' => Inertia::defer(fn () => $project->testRuns()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'status', 'environment']), 'sidebar'),
            'workspaceMembers' => Inertia::defer(function () use ($project) {
                if (! $project->workspace_id) {
                    return [];
                }
                $workspace = $project->workspace;
                if (! $workspace) {
                    return [];
                }

                return $workspace->members()
                    ->select('users.id', 'users.name', 'users.email')
                    ->get()
                    ->toArray();
            }, 'sidebar'),
        ]);
    }

    public function update(UpdateReleaseRequest $request, Project $project, Release $release): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);

        $validated = $request->validated();

        $release->update($validated);

        return back();
    }

    public function destroy(Project $project, Release $release): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);

        $release->delete();

        return redirect()->route('releases.index', $project);
    }

    public function refreshMetrics(Project $project, Release $release): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);

        $snapshot = $this->metricsCalculator->createSnapshot($release);
        $health = $this->metricsCalculator->determineHealth($snapshot->toArray());

        $release->update(['health' => $health]);

        return back();
    }

    public function storeFeature(StoreReleaseFeatureRequest $request, Project $project, Release $release): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);

        $validated = $request->validated();

        $release->features()->create($validated);

        return back();
    }

    public function updateFeature(UpdateReleaseFeatureRequest $request, Project $project, Release $release, ReleaseFeature $releaseFeature): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);
        abort_unless($releaseFeature->release_id === $release->id, 404);

        $validated = $request->validated();

        $releaseFeature->update($validated);

        return back();
    }

    public function destroyFeature(Project $project, Release $release, ReleaseFeature $releaseFeature): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);
        abort_unless($releaseFeature->release_id === $release->id, 404);

        $releaseFeature->delete();

        return back();
    }

    public function storeChecklistItem(StoreReleaseChecklistItemRequest $request, Project $project, Release $release): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);

        $validated = $request->validated();

        $maxOrder = $release->checklistItems()
            ->where('category', $validated['category'])
            ->max('order') ?? -1;

        $release->checklistItems()->create([
            ...$validated,
            'order' => $maxOrder + 1,
        ]);

        return back();
    }

    public function updateChecklistItem(UpdateReleaseChecklistItemRequest $request, Project $project, Release $release, ReleaseChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);
        abort_unless($item->release_id === $release->id, 404);

        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $validated['completed_at'] = now();
        } elseif (isset($validated['status']) && $validated['status'] !== 'completed') {
            $validated['completed_at'] = null;
        }

        $item->update($validated);

        return back();
    }

    public function destroyChecklistItem(Project $project, Release $release, ReleaseChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);
        abort_unless($item->release_id === $release->id, 404);

        $item->delete();

        return back();
    }

    public function linkTestRun(LinkTestRunRequest $request, Project $project, Release $release): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);

        $validated = $request->validated();

        $testRun = TestRun::findOrFail($validated['test_run_id']);
        abort_unless($testRun->project_id === $project->id, 422);

        $release->testRuns()->syncWithoutDetaching([$validated['test_run_id']]);

        return back();
    }

    public function unlinkTestRun(Project $project, Release $release, TestRun $testRun): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($release->project_id === $project->id, 404);

        $release->testRuns()->detach($testRun->id);

        return back();
    }

    private function createDefaultChecklistItems(Release $release): void
    {
        $items = [
            ['category' => 'testing', 'title' => 'All test suites passing', 'priority' => 'critical', 'is_blocker' => true, 'order' => 0],
            ['category' => 'testing', 'title' => 'Regression tests completed', 'priority' => 'critical', 'is_blocker' => true, 'order' => 1],
            ['category' => 'testing', 'title' => 'Smoke tests passing', 'priority' => 'high', 'is_blocker' => false, 'order' => 2],
            ['category' => 'testing', 'title' => 'Edge cases verified', 'priority' => 'medium', 'is_blocker' => false, 'order' => 3],
            ['category' => 'security', 'title' => 'Security scan completed', 'priority' => 'critical', 'is_blocker' => true, 'order' => 0],
            ['category' => 'security', 'title' => 'Authentication flows verified', 'priority' => 'high', 'is_blocker' => false, 'order' => 1],
            ['category' => 'security', 'title' => 'Data encryption validated', 'priority' => 'high', 'is_blocker' => false, 'order' => 2],
            ['category' => 'performance', 'title' => 'Load testing completed', 'priority' => 'high', 'is_blocker' => false, 'order' => 0],
            ['category' => 'performance', 'title' => 'Response time benchmarks met', 'priority' => 'high', 'is_blocker' => false, 'order' => 1],
            ['category' => 'performance', 'title' => 'Memory usage within limits', 'priority' => 'medium', 'is_blocker' => false, 'order' => 2],
            ['category' => 'deployment', 'title' => 'Database migrations tested', 'priority' => 'critical', 'is_blocker' => true, 'order' => 0],
            ['category' => 'deployment', 'title' => 'Rollback plan documented', 'priority' => 'high', 'is_blocker' => false, 'order' => 1],
            ['category' => 'deployment', 'title' => 'Environment variables configured', 'priority' => 'critical', 'is_blocker' => true, 'order' => 2],
            ['category' => 'deployment', 'title' => 'Monitoring alerts configured', 'priority' => 'medium', 'is_blocker' => false, 'order' => 3],
            ['category' => 'documentation', 'title' => 'Release notes drafted', 'priority' => 'high', 'is_blocker' => false, 'order' => 0],
            ['category' => 'documentation', 'title' => 'API documentation updated', 'priority' => 'medium', 'is_blocker' => false, 'order' => 1],
            ['category' => 'documentation', 'title' => 'User guide updated', 'priority' => 'medium', 'is_blocker' => false, 'order' => 2],
        ];

        foreach ($items as $item) {
            $release->checklistItems()->create($item);
        }
    }
}
