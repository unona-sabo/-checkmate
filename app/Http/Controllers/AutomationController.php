<?php

namespace App\Http\Controllers;

use App\Http\Requests\Automation\LinkAutomationTestCaseRequest;
use App\Http\Requests\Automation\StoreAutomationResultsRequest;
use App\Http\Requests\Automation\UnlinkAutomationTestCaseRequest;
use App\Http\Requests\Automation\UpdateAutomationConfigRequest;
use App\Models\Project;
use App\Services\PlaywrightRunner;
use App\Services\PlaywrightScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function __construct(
        private PlaywrightScanner $scanner,
        private PlaywrightRunner $runner,
    ) {}

    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $recentResults = $project->automationTestResults()
            ->with('testCase:id,title', 'environment:id,name')
            ->latest('executed_at')
            ->cursorPaginate(50);

        // Compute stats from most recent run
        $latestExecutedAt = $project->automationTestResults()->max('executed_at');
        $latestRunStats = [];
        if ($latestExecutedAt) {
            $stats = $project->automationTestResults()
                ->where('executed_at', $latestExecutedAt)
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed, SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped, SUM(CASE WHEN status = 'timedout' THEN 1 ELSE 0 END) as timedout")
                ->first();
            $latestRunStats = [
                'total' => (int) $stats->total,
                'passed' => (int) $stats->passed,
                'failed' => (int) $stats->failed,
                'skipped' => (int) $stats->skipped,
                'timedout' => (int) $stats->timedout,
                'executed_at' => $latestExecutedAt,
            ];
        }

        return Inertia::render('Automation/Index', [
            'project' => $project,
            'recentResults' => $recentResults,
            'latestRunStats' => $latestRunStats,
            'environments' => Inertia::defer(fn () => $project->testEnvironments()
                ->orderBy('name')
                ->get(), 'sidebar'),
            'templates' => Inertia::defer(fn () => $project->testRunTemplates()
                ->with('environment:id,name')
                ->orderBy('name')
                ->get(), 'sidebar'),
        ]);
    }

    public function updateConfig(UpdateAutomationConfigRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->update($validated);

        return back();
    }

    public function scan(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        if (! $project->automation_tests_path) {
            return response()->json(['error' => 'Automation tests path not configured'], 400);
        }

        try {
            $results = $this->scanner->scanDirectory($project->automation_tests_path);

            return response()->json($results);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function run(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        try {
            $options = $request->only(['file', 'grep', 'environment_id', 'template_id', 'tags', 'tag_mode']);
            $results = $this->runner->runTests($project, $options);

            $meta = [
                'environment_id' => $options['environment_id'] ?? null,
                'template_id' => $options['template_id'] ?? null,
                'tags' => $options['tags'] ?? null,
            ];
            $imported = $this->runner->importResults($project, $results, $meta);

            return response()->json([
                'message' => "Tests executed. {$imported} results imported.",
                'results' => $results,
                'imported' => $imported,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function importResults(StoreAutomationResultsRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        try {
            $imported = $this->runner->importResults($project, $validated['results']);

            return response()->json([
                'message' => "{$imported} results imported successfully",
                'imported' => $imported,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function linkTestCase(LinkAutomationTestCaseRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $testCase = \App\Models\TestCase::findOrFail($validated['test_case_id']);
        $testCase->update([
            'playwright_file' => $validated['playwright_file'],
            'playwright_test_name' => $validated['playwright_test_name'],
            'is_automated' => true,
        ]);

        return back();
    }

    public function unlinkTestCase(UnlinkAutomationTestCaseRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $testCase = \App\Models\TestCase::findOrFail($validated['test_case_id']);
        $testCase->update([
            'playwright_file' => null,
            'playwright_test_name' => null,
            'is_automated' => false,
        ]);

        return back();
    }

    public function clearResults(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->automationTestResults()->delete();

        return back();
    }
}
