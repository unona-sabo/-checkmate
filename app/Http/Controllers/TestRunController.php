<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TestRun;
use Illuminate\Http\Request;
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
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        $testSuites = $project->testSuites()
            ->whereNull('parent_id')
            ->with(['children.testCases', 'testCases'])
            ->get();

        return Inertia::render('TestRuns/Create', [
            'project' => $project,
            'testSuites' => $testSuites,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'environment' => 'nullable|string|max:255',
            'milestone' => 'nullable|string|max:255',
            'test_case_ids' => 'required|array|min:1',
            'test_case_ids.*' => 'exists:test_cases,id',
        ]);

        $testRun = $project->testRuns()->create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'environment' => $validated['environment'],
            'milestone' => $validated['milestone'],
            'status' => 'active',
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

    public function show(Project $project, TestRun $testRun): Response
    {
        $this->authorize('view', $project);

        $testRun->load([
            'testRunCases.testCase.testSuite',
            'testRunCases.assignedUser',
            'creator:id,name',
        ]);

        $testRun->setAttribute('elapsed_seconds', $testRun->getElapsedSeconds());
        $testRun->setAttribute('is_paused', $testRun->isPaused());

        return Inertia::render('TestRuns/Show', [
            'project' => $project,
            'testRun' => $testRun,
        ]);
    }

    public function edit(Project $project, TestRun $testRun): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('TestRuns/Edit', [
            'project' => $project,
            'testRun' => $testRun,
        ]);
    }

    public function update(Request $request, Project $project, TestRun $testRun)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'environment' => 'nullable|string|max:255',
            'milestone' => 'nullable|string|max:255',
            'status' => 'required|in:active,completed,archived',
        ]);

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
