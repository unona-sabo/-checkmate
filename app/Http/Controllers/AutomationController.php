<?php

namespace App\Http\Controllers;

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
            ->limit(50)
            ->get();

        // Compute stats from most recent run
        $latestExecutedAt = $project->automationTestResults()->max('executed_at');
        $latestRunStats = [];
        if ($latestExecutedAt) {
            $latestRun = $project->automationTestResults()
                ->where('executed_at', $latestExecutedAt)
                ->get();
            $latestRunStats = [
                'total' => $latestRun->count(),
                'passed' => $latestRun->where('status', 'passed')->count(),
                'failed' => $latestRun->where('status', 'failed')->count(),
                'skipped' => $latestRun->where('status', 'skipped')->count(),
                'timedout' => $latestRun->where('status', 'timedout')->count(),
                'executed_at' => $latestExecutedAt,
            ];
        }

        $environments = $project->testEnvironments()
            ->orderBy('name')
            ->get();

        $templates = $project->testRunTemplates()
            ->with('environment:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Automation/Index', [
            'project' => $project,
            'recentResults' => $recentResults,
            'latestRunStats' => $latestRunStats,
            'environments' => $environments,
            'templates' => $templates,
        ]);
    }

    public function updateConfig(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'automation_tests_path' => 'required|string|max:500',
        ]);

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

    public function importResults(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'results' => 'required|array',
        ]);

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

    public function linkTestCase(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'test_case_id' => 'required|exists:test_cases,id',
            'playwright_file' => 'required|string',
            'playwright_test_name' => 'required|string',
        ]);

        $testCase = \App\Models\TestCase::findOrFail($validated['test_case_id']);
        $testCase->update([
            'playwright_file' => $validated['playwright_file'],
            'playwright_test_name' => $validated['playwright_test_name'],
            'is_automated' => true,
        ]);

        return back();
    }

    public function unlinkTestCase(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'test_case_id' => 'required|exists:test_cases,id',
        ]);

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
