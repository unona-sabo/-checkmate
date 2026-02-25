<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestRun\AddCasesRequest;
use App\Http\Requests\TestRun\StoreTestRunFromChecklistRequest;
use App\Http\Requests\TestRun\StoreTestRunRequest;
use App\Http\Requests\TestRun\UpdateTestRunRequest;
use App\Models\Project;
use App\Models\TestRun;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class TestRunController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $testRuns = $project->testRuns()
            ->with(['completedByUser:id,name', 'creator:id,name'])
            ->withCount('testRunCases')
            ->latest()
            ->get()
            ->each(function (TestRun $run) {
                $run->setAttribute('elapsed_seconds', $run->getElapsedSeconds());
                $run->setAttribute('is_paused', $run->isPaused());
            });

        return Inertia::render('TestRuns/Index', [
            'project' => $project,
            'testRuns' => $testRuns,
            'users' => Inertia::defer(fn () => User::query()
                ->whereIn('id', fn ($q) => $q->select('created_by')->from('test_runs')
                    ->where('project_id', $project->id)->whereNotNull('created_by')->distinct())
                ->get(['id', 'name'])),
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        $source = request()->query('source', 'test-cases');

        $testSuites = $project->testSuites()
            ->whereNull('parent_id')
            ->with(['children.testCases', 'testCases'])
            ->get();

        $props = [
            'project' => $project,
            'testSuites' => $testSuites,
            'source' => $source,
        ];

        if ($source === 'checklist') {
            $props['checklists'] = $project->checklists()
                ->with('rows')
                ->get();
        }

        return Inertia::render('TestRuns/Create', $props);
    }

    public function store(StoreTestRunRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $testRun = $project->testRuns()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'environment' => $validated['environment'] ?? null,
            'milestone' => $validated['milestone'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'status' => 'active',
            'source' => 'test-cases',
            'created_by' => auth()->id(),
        ]);

        foreach ($validated['test_case_ids'] as $testCaseId) {
            $testRun->testRunCases()->create([
                'test_case_id' => $testCaseId,
                'status' => 'untested',
            ]);
        }

        $testRun->updateStats();

        return redirect()->route('test-runs.show', [$project, $testRun])
            ->with('success', 'Test run created successfully.');
    }

    public function storeFromChecklist(StoreTestRunFromChecklistRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $testRun = $project->testRuns()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'environment' => $validated['environment'] ?? null,
            'milestone' => $validated['milestone'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'status' => 'active',
            'source' => 'checklist',
            'checklist_id' => $validated['checklist_id'],
            'created_by' => auth()->id(),
        ]);

        $expectedResults = $validated['expected_results'] ?? [];

        foreach ($validated['titles'] as $title) {
            $testRun->testRunCases()->create([
                'title' => $title,
                'expected_result' => $expectedResults[$title] ?? null,
                'status' => 'untested',
            ]);
        }

        $testRun->updateStats();

        return redirect()->route('test-runs.show', [$project, $testRun])
            ->with('success', 'Test run created from checklist.');
    }

    public function show(Project $project, TestRun $testRun): Response
    {
        $this->authorize('view', $project);

        $testRun->load([
            'testRunCases.testCase.testSuite',
            'testRunCases.assignedUser',
            'creator:id,name',
            'checklist:id,name',
        ]);

        $testRun->setAttribute('elapsed_seconds', $testRun->getElapsedSeconds());
        $testRun->setAttribute('is_paused', $testRun->isPaused());

        $props = [
            'project' => $project,
            'testRun' => $testRun,
        ];

        if ($testRun->source === 'test-cases') {
            $props['testSuites'] = Inertia::defer(fn () => $project->testSuites()
                ->whereNull('parent_id')
                ->with(['children.testCases', 'testCases'])
                ->get());
        } elseif ($testRun->source === 'checklist') {
            $props['checklists'] = Inertia::defer(fn () => $project->checklists()
                ->with('rows')
                ->get());
        }

        return Inertia::render('TestRuns/Show', $props);
    }

    public function edit(Project $project, TestRun $testRun): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('TestRuns/Edit', [
            'project' => $project,
            'testRun' => $testRun,
        ]);
    }

    public function update(UpdateTestRunRequest $request, Project $project, TestRun $testRun)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        if ($validated['status'] === 'completed' && $testRun->status !== 'completed') {
            $validated['completed_at'] = now();
            $validated['completed_by'] = auth()->id();
        }

        $testRun->update($validated);

        return redirect()->route('test-runs.show', [$project, $testRun])
            ->with('success', 'Test run updated successfully.');
    }

    public function destroy(Project $project, TestRun $testRun)
    {
        $this->authorize('update', $project);

        $testRun->delete();

        return redirect()->route('test-runs.index', $project)
            ->with('success', 'Test run deleted successfully.');
    }

    public function complete(Project $project, TestRun $testRun)
    {
        $this->authorize('update', $project);

        $data = [
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ];

        if ($testRun->isPaused()) {
            $data['total_paused_seconds'] = $testRun->total_paused_seconds + (int) $testRun->paused_at->diffInSeconds(now());
            $data['paused_at'] = null;
        }

        $testRun->update($data);

        return back()->with('success', 'Test run completed.');
    }

    public function archive(Project $project, TestRun $testRun)
    {
        $this->authorize('update', $project);

        $data = ['status' => 'archived'];

        if ($testRun->isPaused()) {
            $data['total_paused_seconds'] = $testRun->total_paused_seconds + (int) $testRun->paused_at->diffInSeconds(now());
            $data['paused_at'] = null;
        }

        $testRun->update($data);

        return back()->with('success', 'Test run archived.');
    }

    public function pause(Project $project, TestRun $testRun)
    {
        $this->authorize('update', $project);

        if ($testRun->status !== 'active' || $testRun->isPaused()) {
            return back()->with('error', 'Cannot pause this test run.');
        }

        $testRun->update(['paused_at' => now()]);

        return back()->with('success', 'Test run paused.');
    }

    public function addCases(AddCasesRequest $request, Project $project, TestRun $testRun)
    {
        $this->authorize('update', $project);

        if ($testRun->status !== 'active') {
            return back()->with('error', 'Can only add cases to active test runs.');
        }

        $validated = $request->validated();
        $added = 0;

        if ($testRun->source === 'test-cases') {
            $existingIds = $testRun->testRunCases()->whereNotNull('test_case_id')->pluck('test_case_id')->toArray();

            foreach ($validated['test_case_ids'] ?? [] as $testCaseId) {
                if (in_array($testCaseId, $existingIds)) {
                    continue;
                }

                $testRun->testRunCases()->create([
                    'test_case_id' => $testCaseId,
                    'status' => 'untested',
                ]);
                $added++;
            }
        } elseif ($testRun->source === 'checklist') {
            $existingTitles = $testRun->testRunCases()->pluck('title')->toArray();
            $expectedResults = $validated['expected_results'] ?? [];

            foreach ($validated['titles'] ?? [] as $title) {
                if (in_array($title, $existingTitles)) {
                    continue;
                }

                $testRun->testRunCases()->create([
                    'title' => $title,
                    'expected_result' => $expectedResults[$title] ?? null,
                    'status' => 'untested',
                ]);
                $added++;
            }
        }

        $testRun->updateStats();
        $testRun->updateProgress();

        return back()->with('success', "{$added} case(s) added to the test run.");
    }

    public function resume(Project $project, TestRun $testRun)
    {
        $this->authorize('update', $project);

        if (! $testRun->isPaused()) {
            return back()->with('error', 'Test run is not paused.');
        }

        $testRun->update([
            'total_paused_seconds' => $testRun->total_paused_seconds + (int) $testRun->paused_at->diffInSeconds(now()),
            'paused_at' => null,
        ]);

        return back()->with('success', 'Test run resumed.');
    }
}
