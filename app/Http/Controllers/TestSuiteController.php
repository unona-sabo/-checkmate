<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestSuite\CopySuitesRequest;
use App\Http\Requests\TestSuite\ReorderTestSuitesRequest;
use App\Http\Requests\TestSuite\StoreTestSuiteRequest;
use App\Http\Requests\TestSuite\UpdateTestSuiteRequest;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
                'projectFeatures:id,name,module',
                'children.testCases' => fn ($q) => $q->with(['creator:id,name', 'projectFeatures:id,name,module'])->orderBy('order'),
                'children.projectFeatures:id,name,module',
                'testCases' => fn ($q) => $q->with(['creator:id,name', 'projectFeatures:id,name,module'])->orderBy('order'),
            ])
            ->withCount('testCases')
            ->orderBy('order')
            ->get();

        $users = User::query()->select('id', 'name')->orderBy('name')->get();

        $availableFeatures = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module']);

        return Inertia::render('TestSuites/Index', [
            'project' => $project,
            'testSuites' => $testSuites,
            'users' => $users,
            'availableFeatures' => $availableFeatures,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        $parentSuites = $project->testSuites()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        $features = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module', 'priority']);

        return Inertia::render('TestSuites/Create', [
            'project' => $project,
            'parentSuites' => $parentSuites,
            'features' => $features,
        ]);
    }

    public function store(StoreTestSuiteRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $testCaseIds = $validated['test_case_ids'] ?? [];
        $featureIds = $validated['feature_ids'] ?? [];
        unset($validated['feature_ids'], $validated['test_case_ids']);

        $maxOrder = $project->testSuites()
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->max('order') ?? 0;

        $validated['order'] = $validated['order'] ?? ($maxOrder + 1);

        $testSuite = $project->testSuites()->create($validated);
        $testSuite->projectFeatures()->sync($featureIds);

        if ($testCaseIds) {
            $projectSuiteIds = $project->testSuites()->pluck('id');

            TestCase::whereIn('id', $testCaseIds)
                ->whereIn('test_suite_id', $projectSuiteIds)
                ->update(['test_suite_id' => $testSuite->id]);

            // Re-order sequentially
            TestCase::where('test_suite_id', $testSuite->id)
                ->orderBy('order')
                ->get()
                ->each(fn (TestCase $tc, int $i) => $tc->update(['order' => $i + 1]));

            return redirect()->route('test-suites.index', $project)
                ->with('success', 'Test suite created and test cases moved successfully.');
        }

        return redirect()->route('test-suites.show', [$project, $testSuite])
            ->with('success', 'Test suite created successfully.');
    }

    public function show(Project $project, TestSuite $testSuite): Response
    {
        $this->authorize('view', $project);

        $testSuite->load(['children.testCases', 'testCases.note', 'parent', 'projectFeatures:id,name,module']);

        return Inertia::render('TestSuites/Show', [
            'project' => $project,
            'testSuite' => $testSuite,
        ]);
    }

    public function edit(Project $project, TestSuite $testSuite): Response
    {
        $this->authorize('update', $project);

        $testSuite->load('projectFeatures:id');

        $parentSuites = $project->testSuites()
            ->whereNull('parent_id')
            ->where('id', '!=', $testSuite->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $features = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module', 'priority']);

        return Inertia::render('TestSuites/Edit', [
            'project' => $project,
            'testSuite' => $testSuite,
            'parentSuites' => $parentSuites,
            'features' => $features,
        ]);
    }

    public function update(UpdateTestSuiteRequest $request, Project $project, TestSuite $testSuite)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $featureIds = $validated['feature_ids'] ?? [];
        unset($validated['feature_ids']);

        $testSuite->update($validated);
        $testSuite->projectFeatures()->sync($featureIds);

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

    public function copyProjects(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $workspace = $request->attributes->get('workspace');

        $projects = $workspace
            ? $workspace->projects()->select('id', 'name')->orderBy('name')->get()
            : $request->user()->projects()->select('id', 'name')->orderBy('name')->get();

        return response()->json($projects);
    }

    public function copySuites(CopySuitesRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();

        $targetProject = Project::findOrFail($validated['project_id']);
        $this->authorize('update', $targetProject);

        $suites = $targetProject->testSuites()
            ->whereNull('parent_id')
            ->with('children:id,parent_id,name')
            ->orderBy('name')
            ->get(['id', 'project_id', 'parent_id', 'name']);

        return response()->json($suites);
    }

    public function reorder(ReorderTestSuitesRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        foreach ($validated['suites'] as $suiteData) {
            TestSuite::where('id', $suiteData['id'])->update([
                'order' => $suiteData['order'],
                'parent_id' => $suiteData['parent_id'],
            ]);
        }

        return back()->with('success', 'Test suites reordered successfully.');
    }
}
