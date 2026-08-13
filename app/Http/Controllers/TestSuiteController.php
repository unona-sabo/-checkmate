<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestSuite\CopySuitesRequest;
use App\Http\Requests\TestSuite\ReorderTestSuitesRequest;
use App\Http\Requests\TestSuite\StoreTestSuiteRequest;
use App\Http\Requests\TestSuite\UpdateTestSuiteRequest;
use App\Models\AiSetting;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\FeatureLinkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestSuiteController extends Controller
{
    public function __construct(private readonly FeatureLinkingService $featureLinkingService) {}

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

        $archiveSuites = $project->testSuites()->where('is_archived', true)
            ->orderBy('name')->get(['id', 'name']);

        $aiSettings = $project->workspace ? AiSetting::forWorkspace($project->workspace) : null;

        return Inertia::render('TestSuites/Index', [
            'project' => $project,
            'testSuites' => $testSuites,
            'users' => $users,
            'availableFeatures' => $availableFeatures,
            'archiveSuites' => $archiveSuites,
            'defaultAiProvider' => $aiSettings?->default_provider ?? config('services.ai.default_provider', 'gemini'),
            'hasGeminiKey' => $aiSettings?->apiKeyFor('gemini') !== null,
            'hasClaudeKey' => $aiSettings?->apiKeyFor('claude') !== null,
            'hasOpenaiKey' => $aiSettings?->apiKeyFor('openai') !== null,
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

    public function store(StoreTestSuiteRequest $request, Project $project, AchievementService $achievements): JsonResponse|RedirectResponse
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
        $this->featureLinkingService->syncWithCascadeToTestCases($testSuite, $featureIds);
        $achievements->checkFirstTestSuite($request->user());

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

        if ($request->wantsJson()) {
            return response()->json($testSuite);
        }

        return redirect()->route('test-suites.show', [$project, $testSuite])
            ->with('success', 'Test suite created successfully.');
    }

    public function show(Project $project, TestSuite $testSuite): Response
    {
        $this->authorize('view', $project);
        abort_unless($testSuite->project_id === $project->id, 404);

        $testSuite->load([
            'children.testCases' => fn ($q) => $q->with(['creator:id,name', 'projectFeatures:id,name,module'])->orderBy('order'),
            'testCases' => fn ($q) => $q->with(['creator:id,name', 'projectFeatures:id,name,module'])->orderBy('order'),
            'testCases.note',
            'parent',
            'projectFeatures:id,name,module',
        ]);

        $users = User::query()->select('id', 'name')->orderBy('name')->get();

        $availableFeatures = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module']);

        $allTestSuites = $project->testSuites()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'is_archived']);

        $archiveSuites = $allTestSuites->where('is_archived', true)->values();

        $aiSettings = $project->workspace ? AiSetting::forWorkspace($project->workspace) : null;

        return Inertia::render('TestSuites/Show', [
            'project' => $project,
            'testSuite' => $testSuite,
            'users' => $users,
            'availableFeatures' => $availableFeatures,
            'allTestSuites' => $allTestSuites,
            'archiveSuites' => $archiveSuites,
            'defaultAiProvider' => $aiSettings?->default_provider ?? config('services.ai.default_provider', 'gemini'),
            'hasGeminiKey' => $aiSettings?->apiKeyFor('gemini') !== null,
            'hasClaudeKey' => $aiSettings?->apiKeyFor('claude') !== null,
            'hasOpenaiKey' => $aiSettings?->apiKeyFor('openai') !== null,
        ]);
    }

    public function edit(Project $project, TestSuite $testSuite): Response
    {
        $this->authorize('update', $project);
        abort_unless($testSuite->project_id === $project->id, 404);

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
        abort_unless($testSuite->project_id === $project->id, 404);

        $validated = $request->validated();

        $featureIds = $validated['feature_ids'] ?? [];
        unset($validated['feature_ids']);

        $testSuite->update($validated);
        $this->featureLinkingService->syncWithCascadeToTestCases($testSuite, $featureIds);

        return redirect()->route('test-suites.show', [$project, $testSuite])
            ->with('success', 'Test suite updated successfully.');
    }

    public function destroy(Project $project, TestSuite $testSuite)
    {
        $this->authorize('update', $project);
        abort_unless($testSuite->project_id === $project->id, 404);

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

        $projectSuiteIds = $project->testSuites()->pluck('id');

        foreach ($validated['suites'] as $suiteData) {
            if (! $projectSuiteIds->contains($suiteData['id'])) {
                continue;
            }

            if ($suiteData['parent_id'] !== null && ! $projectSuiteIds->contains($suiteData['parent_id'])) {
                continue;
            }

            TestSuite::where('id', $suiteData['id'])->update([
                'order' => $suiteData['order'],
                'parent_id' => $suiteData['parent_id'],
            ]);
        }

        return back()->with('success', 'Test suites reordered successfully.');
    }
}
