<?php

namespace App\Http\Controllers;

use App\Http\Requests\AIGenerator\GenerateTestCasesRequest;
use App\Http\Requests\AIGenerator\ImportTestCasesRequest;
use App\Models\AiGeneration;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Services\AITestGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AIGeneratorController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $testSuites = $project->testSuites()
            ->select('id', 'name', 'parent_id')
            ->orderBy('name')
            ->get();

        $defaultProvider = config('services.ai.default_provider', 'gemini');
        $hasGeminiKey = ! empty(config('services.gemini.api_key'));
        $hasClaudeKey = ! empty(config('services.anthropic.api_key'));

        return Inertia::render('AIGenerator/Index', [
            'project' => $project,
            'testSuites' => $testSuites,
            'defaultProvider' => $defaultProvider,
            'hasGeminiKey' => $hasGeminiKey,
            'hasClaudeKey' => $hasClaudeKey,
        ]);
    }

    public function generate(GenerateTestCasesRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();
        $provider = $validated['provider'] ?? null;
        $count = $validated['count'] ?? null;
        $customPrompt = $validated['custom_prompt'] ?? null;

        $service = new AITestGeneratorService($provider);

        $options = array_filter([
            'count' => $count,
            'custom_prompt' => $customPrompt,
        ], fn ($v) => $v !== null);

        $testCases = match ($validated['input_type']) {
            'text' => $service->generateFromText($validated['text'], $options),
            'file' => $service->generateFromFile($request->file('file')->getRealPath(), $options),
            'image' => $service->generateFromImage($request->file('image')->getRealPath(), $options),
        };

        $generation = AiGeneration::query()->create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'provider' => $service->getProvider(),
            'model' => $service->getModel(),
            'input_type' => $validated['input_type'],
            'test_cases_generated' => count($testCases),
        ]);

        return response()->json([
            'test_cases' => $testCases,
            'generation_id' => $generation->id,
            'provider' => $service->getProvider(),
            'model' => $service->getModel(),
        ]);
    }

    public function save(ImportTestCasesRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        // Resolve or create test suite
        if (! empty($validated['test_suite_id'])) {
            $testSuite = TestSuite::query()->findOrFail($validated['test_suite_id']);
            abort_unless($testSuite->project_id === $project->id, 422, 'Test suite does not belong to this project.');
        } else {
            $testSuite = $project->testSuites()->create([
                'name' => $validated['test_suite_name'],
                'type' => 'functional',
            ]);
        }

        $maxOrder = TestCase::query()
            ->where('test_suite_id', $testSuite->id)
            ->max('order') ?? -1;

        foreach ($validated['test_cases'] as $index => $caseData) {
            $steps = $this->parseStepsToArray($caseData['steps'] ?? '');

            TestCase::query()->create([
                'test_suite_id' => $testSuite->id,
                'title' => $caseData['title'],
                'description' => $caseData['description'] ?? null,
                'preconditions' => $caseData['preconditions'] ?? null,
                'steps' => $steps,
                'expected_result' => $caseData['expected_result'] ?? null,
                'priority' => $caseData['priority'] ?? 'medium',
                'severity' => $caseData['severity'] ?? 'major',
                'type' => $caseData['type'] ?? 'functional',
                'automation_status' => $caseData['automation_status'] ?? 'not_automated',
                'order' => $maxOrder + $index + 1,
                'created_by' => $request->user()->id,
            ]);
        }

        // Update ai_generation record if provided
        if (! empty($validated['ai_generation_id'])) {
            AiGeneration::query()
                ->where('id', $validated['ai_generation_id'])
                ->where('project_id', $project->id)
                ->update([
                    'test_cases_approved' => count($validated['test_cases']),
                    'test_cases_imported' => count($validated['test_cases']),
                    'test_suite_id' => $testSuite->id,
                ]);
        }

        return redirect()->route('test-suites.show', [$project, $testSuite])
            ->with('success', count($validated['test_cases']).' test case(s) imported successfully.');
    }

    /**
     * Parse step text into structured array. Handles per-step expected results
     * in the format "Expected: ..." on indented lines after each action.
     *
     * @return list<array{action: string, expected: string|null}>
     */
    private function parseStepsToArray(string $stepsText): array
    {
        if (empty(trim($stepsText))) {
            return [];
        }

        $lines = preg_split('/\r?\n/', trim($stepsText));
        $steps = [];
        $currentStep = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            // Check if this is an "Expected:" annotation for the current step
            if ($currentStep !== null && preg_match('/^\s*Expected:\s*(.+)/i', $line, $match)) {
                $steps[$currentStep]['expected'] = trim($match[1]);

                continue;
            }

            // This is a new action step (may start with "1." numbering)
            $action = preg_replace('/^\d+\.\s*/', '', $trimmed);
            $steps[] = ['action' => $action, 'expected' => null];
            $currentStep = array_key_last($steps);
        }

        return array_values($steps);
    }
}
