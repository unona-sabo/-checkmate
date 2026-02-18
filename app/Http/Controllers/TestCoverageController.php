<?php

namespace App\Http\Controllers;

use App\Models\AiGeneratedTestCase;
use App\Models\Project;
use App\Models\ProjectFeature;
use App\Services\ClaudeAIService;
use App\Services\CoverageCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestCoverageController extends Controller
{
    public function __construct(
        private CoverageCalculator $calculator,
        private ClaudeAIService $aiService,
    ) {}

    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $stats = $this->calculator->getStatistics($project);
        $coverageByModule = $this->calculator->getCoverageByModule($project);

        $latestAnalysis = $project->latestCoverageAnalysis;

        $features = $project->features()
            ->where('is_active', true)
            ->withCount(['testCases', 'checklists'])
            ->with('testCases:id,title,test_suite_id', 'testCases.testSuite:id,name', 'checklists:id,name')
            ->orderBy('module')
            ->orderBy('priority')
            ->get();

        $gaps = $this->calculator->getGaps($project);

        $allTestCases = $project->testSuites()
            ->with('testCases:id,title,test_suite_id')
            ->get()
            ->flatMap(fn ($suite) => $suite->testCases->map(fn ($tc) => [
                'id' => $tc->id,
                'title' => $tc->title,
                'test_suite' => ['id' => $suite->id, 'name' => $suite->name],
            ]))
            ->values();

        $allChecklists = $project->checklists()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('TestCoverage/Index', [
            'project' => $project,
            'statistics' => $stats,
            'coverageByModule' => $coverageByModule,
            'latestAnalysis' => $latestAnalysis,
            'features' => $features,
            'gaps' => $gaps,
            'hasAnthropicKey' => ! empty(config('services.anthropic.api_key')),
            'allTestCases' => $allTestCases,
            'allChecklists' => $allChecklists,
        ]);
    }

    public function runAIAnalysis(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $testCases = $project->testSuites()
            ->with('testCases')
            ->get()
            ->flatMap(fn ($suite) => $suite->testCases->map(fn ($testCase) => [
                'title' => $testCase->title,
                'suite' => $suite->name,
                'priority' => $testCase->priority,
                'steps' => $testCase->steps ?? [],
            ]))
            ->toArray();

        $features = $project->features()
            ->where('is_active', true)
            ->get()
            ->map(fn ($feature) => [
                'id' => $feature->id,
                'name' => $feature->name,
                'description' => $feature->description,
                'module' => $feature->module,
                'category' => $feature->category,
                'priority' => $feature->priority,
                'test_cases_count' => $feature->testCases()->count(),
            ])
            ->toArray();

        $documentation = $project->documentations()
            ->get()
            ->map(fn ($doc) => [
                'title' => $doc->title,
                'content' => strip_tags($doc->content ?? ''),
            ])
            ->toArray();

        $analysis = $this->aiService->analyzeCoverage($testCases, $features, $documentation);

        $coverageAnalysis = $project->coverageAnalyses()->create([
            'analysis_data' => $analysis,
            'overall_coverage' => $analysis['overall_coverage'] ?? $this->calculator->calculateOverallCoverage($project),
            'total_features' => count($features),
            'covered_features' => count(array_filter($features, fn ($f) => $f['test_cases_count'] > 0)),
            'total_test_cases' => count($testCases),
            'gaps_count' => count($analysis['gaps'] ?? []),
            'analyzed_at' => now(),
        ]);

        return response()->json([
            'analysis' => $analysis,
            'coverage_analysis_id' => $coverageAnalysis->id,
        ]);
    }

    public function generateTestCases(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $gap = $request->validate([
            'id' => 'required|string',
            'feature' => 'required|string',
            'description' => 'required|string',
            'module' => 'nullable|string',
            'category' => 'nullable|string',
            'priority' => 'required|string',
        ]);

        $generatedCases = $this->aiService->generateTestCases($gap);

        $feature = $project->features()->where('name', $gap['feature'])->first();

        foreach ($generatedCases as $testCase) {
            AiGeneratedTestCase::query()->create([
                'project_id' => $project->id,
                'feature_id' => $feature?->id,
                'title' => $testCase['title'],
                'preconditions' => $testCase['preconditions'] ?? null,
                'test_steps' => $testCase['test_steps'],
                'expected_result' => $testCase['expected_result'],
                'priority' => $testCase['priority'] ?? 'medium',
                'type' => $testCase['type'] ?? 'positive',
            ]);
        }

        return response()->json([
            'test_cases' => $generatedCases,
            'gap' => $gap,
        ]);
    }

    public function coverageHistory(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $history = $project->coverageAnalyses()
            ->orderBy('analyzed_at', 'desc')
            ->limit(30)
            ->get()
            ->map(fn ($analysis) => [
                'date' => $analysis->analyzed_at?->format('Y-m-d'),
                'coverage' => $analysis->overall_coverage,
                'features' => $analysis->total_features,
                'gaps' => $analysis->gaps_count,
            ]);

        return response()->json($history);
    }

    public function storeFeature(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module' => 'nullable|array',
            'module.*' => 'string|max:100',
            'category' => 'nullable|string|max:100',
            'priority' => 'required|in:critical,high,medium,low',
        ]);

        $feature = $project->features()->create($validated);

        $this->autoLinkFeature($project, $feature);

        return back();
    }

    public function updateFeature(Request $request, Project $project, int $featureId): RedirectResponse
    {
        $this->authorize('update', $project);

        $feature = $project->features()->findOrFail($featureId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'module' => 'nullable|array',
            'module.*' => 'string|max:100',
            'category' => 'nullable|string|max:100',
            'priority' => 'sometimes|in:critical,high,medium,low',
            'is_active' => 'sometimes|boolean',
        ]);

        $feature->update($validated);

        return back();
    }

    public function destroyFeature(Project $project, int $featureId): RedirectResponse
    {
        $this->authorize('update', $project);

        $feature = $project->features()->findOrFail($featureId);
        $feature->delete();

        return back();
    }

    public function linkTestCase(Request $request, Project $project, int $featureId): RedirectResponse
    {
        $this->authorize('update', $project);

        $feature = $project->features()->findOrFail($featureId);

        $validated = $request->validate([
            'test_case_id' => 'required|exists:test_cases,id',
        ]);

        $projectTestCaseIds = $this->getProjectTestCaseIds($project);

        if (! in_array((int) $validated['test_case_id'], $projectTestCaseIds, true)) {
            abort(422, 'Test case does not belong to this project.');
        }

        $feature->testCases()->syncWithoutDetaching([$validated['test_case_id']]);

        return back();
    }

    public function unlinkTestCase(Project $project, int $featureId, int $testCaseId): RedirectResponse
    {
        $this->authorize('update', $project);

        $feature = $project->features()->findOrFail($featureId);
        $feature->testCases()->detach($testCaseId);

        return back();
    }

    public function linkChecklist(Request $request, Project $project, int $featureId): RedirectResponse
    {
        $this->authorize('update', $project);

        $feature = $project->features()->findOrFail($featureId);

        $validated = $request->validate([
            'checklist_id' => 'required|exists:checklists,id',
        ]);

        $projectChecklistIds = $project->checklists()->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        if (! in_array((int) $validated['checklist_id'], $projectChecklistIds, true)) {
            abort(422, 'Checklist does not belong to this project.');
        }

        $feature->checklists()->syncWithoutDetaching([$validated['checklist_id']]);

        return back();
    }

    public function unlinkChecklist(Project $project, int $featureId, int $checklistId): RedirectResponse
    {
        $this->authorize('update', $project);

        $feature = $project->features()->findOrFail($featureId);
        $feature->checklists()->detach($checklistId);

        return back();
    }

    public function autoLinkAll(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $features = $project->features()->where('is_active', true)->get();

        foreach ($features as $feature) {
            $this->autoLinkFeature($project, $feature);
        }

        return back();
    }

    public function autoLinkSingle(Project $project, ProjectFeature $feature): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless($feature->project_id === $project->id, 404);

        $this->autoLinkFeature($project, $feature);

        return back();
    }

    public function getTestCases(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $testCases = $project->testSuites()
            ->with('testCases:id,title,test_suite_id')
            ->get()
            ->flatMap(fn ($suite) => $suite->testCases->map(fn ($tc) => [
                'id' => $tc->id,
                'title' => $tc->title,
                'test_suite' => ['id' => $suite->id, 'name' => $suite->name],
            ]))
            ->values();

        return response()->json($testCases);
    }

    private function autoLinkFeature(Project $project, ProjectFeature $feature): void
    {
        $testCases = $project->testSuites()
            ->with('testCases')
            ->get()
            ->flatMap(fn ($suite) => $suite->testCases);

        $matchingIds = $testCases
            ->filter(fn ($tc) => str_contains(mb_strtolower($tc->title), mb_strtolower($feature->name)))
            ->pluck('id')
            ->toArray();

        if ($matchingIds !== []) {
            $feature->testCases()->syncWithoutDetaching($matchingIds);
        }
    }

    /**
     * @return list<int>
     */
    private function getProjectTestCaseIds(Project $project): array
    {
        return $project->testSuites()
            ->with('testCases:id,test_suite_id')
            ->get()
            ->flatMap(fn ($suite) => $suite->testCases->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }
}
