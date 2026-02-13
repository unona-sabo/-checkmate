<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TestSuite;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestSuiteController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $testSuites = $project->testSuites()
            ->whereNull('parent_id')
            ->with([
                'children.testCases' => fn ($q) => $q->orderBy('order'),
                'testCases' => fn ($q) => $q->orderBy('order'),
            ])
            ->withCount('testCases')
            ->orderBy('order')
            ->get();

        return Inertia::render('TestSuites/Index', [
            'project' => $project,
            'testSuites' => $testSuites,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        $parentSuites = $project->testSuites()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('TestSuites/Create', [
            'project' => $project,
            'parentSuites' => $parentSuites,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:functional,smoke,regression,integration,acceptance,performance,security,usability,other',
            'parent_id' => 'nullable|exists:test_suites,id',
            'order' => 'nullable|integer',
        ]);

        $maxOrder = $project->testSuites()
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->max('order') ?? 0;

        $validated['order'] = $validated['order'] ?? ($maxOrder + 1);

        $testSuite = $project->testSuites()->create($validated);

        return redirect()->route('test-suites.show', [$project, $testSuite])
            ->with('success', 'Test suite created successfully.');
    }

    public function show(Project $project, TestSuite $testSuite): Response
    {
        $this->authorize('view', $project);

        $testSuite->load(['children.testCases', 'testCases.note', 'parent']);

        return Inertia::render('TestSuites/Show', [
            'project' => $project,
            'testSuite' => $testSuite,
        ]);
    }

    public function edit(Project $project, TestSuite $testSuite): Response
    {
        $this->authorize('update', $project);

        $parentSuites = $project->testSuites()
            ->whereNull('parent_id')
            ->where('id', '!=', $testSuite->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('TestSuites/Edit', [
            'project' => $project,
            'testSuite' => $testSuite,
            'parentSuites' => $parentSuites,
        ]);
    }

    public function update(Request $request, Project $project, TestSuite $testSuite)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:functional,smoke,regression,integration,acceptance,performance,security,usability,other',
            'parent_id' => 'nullable|exists:test_suites,id',
            'order' => 'nullable|integer',
        ]);

        $testSuite->update($validated);

        return redirect()->route('test-suites.show', [$project, $testSuite])
            ->with('success', 'Test suite updated successfully.');
    }

    public function destroy(Project $project, TestSuite $testSuite)
    {
        $this->authorize('update', $project);

        $testSuite->delete();

        return redirect()->route('test-suites.index', $project)
            ->with('success', 'Test suite deleted successfully.');
    }

    public function reorder(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'suites' => 'required|array',
            'suites.*.id' => 'required|exists:test_suites,id',
            'suites.*.order' => 'required|integer',
            'suites.*.parent_id' => 'nullable|exists:test_suites,id',
        ]);

        foreach ($validated['suites'] as $suiteData) {
            TestSuite::where('id', $suiteData['id'])->update([
                'order' => $suiteData['order'],
                'parent_id' => $suiteData['parent_id'],
            ]);
        }

        return back()->with('success', 'Test suites reordered successfully.');
    }
}
