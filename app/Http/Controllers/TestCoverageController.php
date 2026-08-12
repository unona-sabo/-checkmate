<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestCoverage\ApproveGeneratedTestCasesRequest;
use App\Http\Requests\TestCoverage\AttachChecklistRequest;
use App\Http\Requests\TestCoverage\AttachTestCaseRequest;
use App\Http\Requests\TestCoverage\RunCoverageAnalysisRequest;
use App\Http\Requests\TestCoverage\StoreCoverageFeatureRequest;
use App\Http\Requests\TestCoverage\StoreCoverageGapRequest;
use App\Http\Requests\TestCoverage\UpdateCoverageFeatureRequest;
use App\Models\AiGeneratedTestCase;
use App\Models\AiSetting;
use App\Models\CoverageAnalysis;
use App\Models\Project;
use App\Models\ProjectFeature;
use App\Models\TestCase;
use App\Services\AchievementService;
use App\Services\CoverageAnalysisService;
use App\Services\CoverageCalculator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TestCoverageController extends Controller
{
    public function __construct(
        private CoverageCalculator $calculator,
        private AchievementService $achievements,
    ) {}

    /**
     * Build an AI client for the given provider using the current project's
     * own workspace key — AI keys are workspace-scoped, so this can't be a
     * constructor-injected singleton.
     */
    private function coverageServiceFor(Project $project, ?string $provider = null): CoverageAnalysisService
    {
        $settings = $project->workspace ? AiSetting::forWorkspace($project->workspace) : null;

        $provider ??= $settings?->default_provider ?? config('services.ai.default_provider', 'gemini');

        return new CoverageAnalysisService($provider, $settings?->apiKeyFor($provider), $settings?->modelFor($provider));
    }

    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $stats = $this->calculator->getStatistics($project);
        $coverageByModule = $this->calculator->getCoverageByModule($project);

        $latestAnalysis = $project->latestCoverageAnalysis;

        $features = $project->features()
            ->where('is_active', true)
            ->withCount(['testCases', 'checklists'])
            ->with('testCases:id,title,test_suite_id,module', 'testCases.testSuite:id,name', 'checklists:id,name,module')
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
            ->select('id', 'name', 'module')
            ->orderBy('name')
            ->get();

        $testSuites = $project->testSuites()
            ->orderBy('name')
            ->get(['id', 'name']);

        $aiSettings = $project->workspace ? AiSetting::forWorkspace($project->workspace) : null;

        return Inertia::render('TestCoverage/Index', [
            'project' => $project,
            'statistics' => $stats,
            'coverageByModule' => $coverageByModule,
            'latestAnalysis' => $latestAnalysis,
            'features' => $features,
            'gaps' => $gaps,
            'defaultAiProvider' => $aiSettings?->default_provider ?? config('services.ai.default_provider', 'gemini'),
            'hasGeminiKey' => $aiSettings?->apiKeyFor('gemini') !== null,
            'hasClaudeKey' => $aiSettings?->apiKeyFor('claude') !== null,
            'hasOpenaiKey' => $aiSettings?->apiKeyFor('openai') !== null,
            'allTestCases' => $allTestCases,
            'allChecklists' => $allChecklists,
            'testSuites' => $testSuites,
        ]);
    }

    public function runAIAnalysis(RunCoverageAnalysisRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        // Only title/suite/priority are needed to judge whether a feature is
        // covered — omitting full step text keeps the prompt well under the
        // AI provider's context window on projects with hundreds of cases.
        $testCases = $project->testSuites()
            ->with('testCases')
            ->get()
            ->flatMap(fn ($suite) => $suite->testCases->map(fn ($testCase) => [
                'title' => $testCase->title,
                'suite' => $suite->name,
                'priority' => $testCase->priority,
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
                'content' => Str::limit(strip_tags($doc->content ?? ''), 2000),
            ])
            ->toArray();

        // Coverage is measured against features — with none defined, there's
        // nothing to send the AI, and asking it to analyze an empty project
        // anyway just invites it to invent plausible-sounding fake findings
        // instead of reporting that honestly.
        if ($features === []) {
            return response()->json([
                'message' => 'This project has no active features yet. Add features first so there\'s something to measure test coverage against.',
            ], 422);
        }

        try {
            $analysis = $this->coverageServiceFor($project, $validated['provider'] ?? null)
                ->analyzeCoverage($testCases, $features, $documentation, $validated['custom_instructions'] ?? null);
        } catch (\RuntimeException|ConnectionException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $coverageAnalysis = $project->coverageAnalyses()->create([
            'analysis_data' => $analysis,
            'overall_coverage' => $analysis['overall_coverage'] ?? $this->calculator->calculateOverallCoverage($project),
            'total_features' => count($features),
            'covered_features' => count(array_filter($features, fn ($f) => $f['test_cases_count'] > 0)),
            'total_test_cases' => count($testCases),
            'gaps_count' => count($analysis['gaps'] ?? []),
            'analyzed_at' => now(),
        ]);

        $this->achievements->checkPerfectionist(auth()->user(), $project);

        return response()->json([
            'analysis' => $analysis,
            'coverage_analysis_id' => $coverageAnalysis->id,
        ]);
    }

    public function generateTestCases(StoreCoverageGapRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $gap = $request->validated();

        $feature = $project->features()->where('name', $gap['feature'])->first();

        $existingTestCases = $feature
            ? $feature->testCases()->get()->map(fn ($testCase) => [
                'title' => $testCase->title,
                'steps' => $testCase->steps ?? [],
            ])->toArray()
            : [];

        $documentation = $feature
            ? $feature->documentations()->get()->map(fn ($doc) => [
                'title' => $doc->title,
                'content' => Str::limit(strip_tags($doc->content ?? ''), 2000),
            ])->toArray()
            : [];

        try {
            $generatedCases = $this->coverageServiceFor($project, $gap['provider'] ?? null)
                ->generateTestCases($gap, $existingTestCases, $documentation);
        } catch (\RuntimeException|ConnectionException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $createdCases = collect($generatedCases)->map(fn ($testCase) => AiGeneratedTestCase::query()->create([
            'project_id' => $project->id,
            'feature_id' => $feature?->id,
            'title' => $testCase['title'],
            'preconditions' => $testCase['preconditions'] ?? null,
            'test_steps' => $testCase['test_steps'],
            'expected_result' => $testCase['expected_result'],
            'priority' => $testCase['priority'] ?? 'medium',
            'type' => $testCase['type'] ?? 'positive',
        ]));

        return response()->json([
            'test_cases' => $createdCases,
            'gap' => $gap,
        ]);
    }

    /**
     * Convert selected AI-generated test cases into real test cases inside
     * a (new or existing) test suite, and mark the source rows approved.
     */
    public function approveGeneratedTestCases(ApproveGeneratedTestCasesRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $cases = AiGeneratedTestCase::query()
            ->whereIn('id', $validated['ids'])
            ->where('project_id', $project->id)
            ->get();

        abort_if($cases->isEmpty(), 404);

        if (! empty($validated['test_suite_id'])) {
            $testSuite = $project->testSuites()->findOrFail($validated['test_suite_id']);
        } else {
            $testSuite = $project->testSuites()->create([
                'name' => $validated['test_suite_name'],
                'type' => 'functional',
            ]);
        }

        $maxOrder = TestCase::query()->where('test_suite_id', $testSuite->id)->max('order') ?? -1;

        foreach ($cases as $index => $case) {
            $testCase = TestCase::query()->create([
                'test_suite_id' => $testSuite->id,
                'title' => $case->title,
                'preconditions' => $case->preconditions,
                'steps' => collect($case->test_steps ?? [])
                    ->map(fn ($step) => ['action' => $step, 'expected' => null])
                    ->all(),
                'expected_result' => $case->expected_result,
                'priority' => in_array($case->priority, ['low', 'medium', 'high', 'critical'], true) ? $case->priority : 'medium',
                'severity' => 'major',
                'type' => 'functional',
                'automation_status' => 'not_automated',
                'order' => $maxOrder + $index + 1,
                'created_by' => $request->user()->id,
            ]);

            if ($case->feature_id) {
                $testCase->projectFeatures()->syncWithoutDetaching([$case->feature_id]);
            }

            $case->update([
                'is_approved' => true,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        }

        return redirect()->route('test-suites.show', [$project, $testSuite])
            ->with('success', $cases->count().' test case(s) approved and added to '.$testSuite->name.'.');
    }

    public function coverageHistory(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        // Fetch one extra (oldest) analysis beyond the 30 we display, purely
        // to serve as the diff baseline for the oldest displayed entry.
        $analyses = $project->coverageAnalyses()
            ->orderBy('analyzed_at', 'desc')
            ->limit(31)
            ->get()
            ->reverse()
            ->values();

        $baselineIncluded = $analyses->count() > 30;

        $history = $analyses
            ->values()
            ->map(function ($analysis, int $index) use ($analyses) {
                $previous = $index > 0 ? $analyses->get($index - 1) : null;

                return [
                    'id' => $analysis->id,
                    'date' => $analysis->analyzed_at?->format('Y-m-d'),
                    'coverage' => $analysis->overall_coverage,
                    'features' => $analysis->total_features,
                    'gaps' => $analysis->gaps_count,
                    'diff' => $this->diffAnalyses($analysis, $previous),
                ];
            });

        if ($baselineIncluded) {
            $history = $history->slice(1)->values();
        }

        return response()->json($history->reverse()->values());
    }

    /**
     * The full AI response snapshot for one past analysis run, so a history
     * entry can be browsed read-only without affecting the project's
     * current/latest analysis.
     */
    public function showHistoryEntry(Project $project, CoverageAnalysis $coverageAnalysis): JsonResponse
    {
        $this->authorize('view', $project);

        abort_unless($coverageAnalysis->project_id === $project->id, 404);

        return response()->json([
            'id' => $coverageAnalysis->id,
            'date' => $coverageAnalysis->analyzed_at?->format('Y-m-d'),
            'analysis_data' => $coverageAnalysis->analysis_data,
        ]);
    }

    /**
     * @return array{
     *     coverage_delta: int|float|null,
     *     features_delta: int|null,
     *     gaps_delta: int|null,
     *     gaps_added: list<string>,
     *     gaps_resolved: list<string>,
     * }|null
     */
    private function diffAnalyses(CoverageAnalysis $current, ?CoverageAnalysis $previous): ?array
    {
        if (! $previous) {
            return null;
        }

        $currentGaps = collect($current->analysis_data['gaps'] ?? [])->pluck('feature')->filter()->unique();
        $previousGaps = collect($previous->analysis_data['gaps'] ?? [])->pluck('feature')->filter()->unique();

        return [
            'coverage_delta' => $current->overall_coverage - $previous->overall_coverage,
            'features_delta' => $current->total_features - $previous->total_features,
            'gaps_delta' => $current->gaps_count - $previous->gaps_count,
            'gaps_added' => $currentGaps->diff($previousGaps)->values()->all(),
            'gaps_resolved' => $previousGaps->diff($currentGaps)->values()->all(),
        ];
    }

    public function storeFeature(StoreCoverageFeatureRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $feature = $project->features()->create($validated);

        $this->autoLinkFeature($project, $feature);

        return back();
    }

    public function updateFeature(UpdateCoverageFeatureRequest $request, Project $project, int $featureId): RedirectResponse
    {
        $this->authorize('update', $project);

        $feature = $project->features()->findOrFail($featureId);

        $validated = $request->validated();

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

    public function linkTestCase(AttachTestCaseRequest $request, Project $project, int $featureId): RedirectResponse
    {
        $this->authorize('update', $project);

        $feature = $project->features()->findOrFail($featureId);

        $validated = $request->validated();

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

    public function linkChecklist(AttachChecklistRequest $request, Project $project, int $featureId): RedirectResponse
    {
        $this->authorize('update', $project);

        $feature = $project->features()->findOrFail($featureId);

        $validated = $request->validated();

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
