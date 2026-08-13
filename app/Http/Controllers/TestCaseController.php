<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestCase\ArchiveTestCasesRequest;
use App\Http\Requests\TestCase\BulkDeleteTestCasesRequest;
use App\Http\Requests\TestCase\BulkUpdateTestCasesRequest;
use App\Http\Requests\TestCase\ImportTestCasesFromFileRequest;
use App\Http\Requests\TestCase\ImportTestCasesFromFileToNewSuiteRequest;
use App\Http\Requests\TestCase\MoveTestCasesRequest;
use App\Http\Requests\TestCase\ReorderTestCasesRequest;
use App\Http\Requests\TestCase\StoreTestCaseNoteAsNewSuiteRequest;
use App\Http\Requests\TestCase\StoreTestCaseNoteRequest;
use App\Http\Requests\TestCase\StoreTestCaseRequest;
use App\Http\Requests\TestCase\UnarchiveTestCasesRequest;
use App\Http\Requests\TestCase\UpdateTestCaseRequest;
use App\Models\Attachment;
use App\Models\Bugreport;
use App\Models\ChecklistRow;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Services\AchievementService;
use App\Services\AttachmentService;
use App\Services\FeatureLinkingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TestCaseController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachmentService,
        private readonly FeatureLinkingService $featureLinkingService,
        private readonly AchievementService $achievements,
    ) {}

    public function create(Project $project, TestSuite $testSuite): Response
    {
        $this->authorize('update', $project);
        abort_unless($testSuite->project_id === $project->id, 404);

        $features = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module', 'priority']);

        $bugreportAttachments = [];
        $bugreportId = request()->query('bugreport_id');
        if ($bugreportId) {
            $bugreport = $project->bugreports()->with('attachments')->find($bugreportId);
            if ($bugreport) {
                $bugreportAttachments = $bugreport->attachments;
            }
        }

        return Inertia::render('TestCases/Create', [
            'project' => $project,
            'testSuite' => $testSuite,
            'features' => $features,
            'bugreportAttachments' => $bugreportAttachments,
        ]);
    }

    public function store(StoreTestCaseRequest $request, Project $project, TestSuite $testSuite)
    {
        $this->authorize('update', $project);
        abort_unless($testSuite->project_id === $project->id, 404);

        $validated = $request->validated();

        $checklistFields = ['checklist_id', 'checklist_row_ids', 'checklist_link_column'];

        $maxOrder = $testSuite->testCases()->max('order') ?? 0;
        $validated['order'] = $maxOrder + 1;
        $validated['created_by'] = auth()->id();

        $testCase = $testSuite->testCases()->create(collect($validated)->except(['attachments', 'feature_ids', ...$checklistFields])->toArray());

        if (! empty($validated['feature_ids'])) {
            $this->featureLinkingService->sync($testCase, $validated['feature_ids']);
        }

        $this->attachmentService->storeFromRequest($testCase, $request, 'attachments/test-cases');

        if (! empty($validated['bugreport_id'])) {
            $this->copyBugreportAttachments($project, $testCase, (int) $validated['bugreport_id']);
        }

        $this->linkTestCaseToChecklistRows($project, $testSuite, $testCase, $validated);

        $this->achievements->checkFirstFiveTestCases($request->user());

        return redirect("/projects/{$project->id}/test-suites/{$testSuite->id}/test-cases/{$testCase->id}")
            ->with('success', 'Test case created successfully.');
    }

    public function show(Project $project, TestSuite $testSuite, TestCase $testCase): Response
    {
        $this->authorize('view', $project);
        abort_unless($testSuite->project_id === $project->id, 404);
        abort_unless($testCase->test_suite_id === $testSuite->id, 404);

        $testCase->load(['note', 'attachments', 'projectFeatures:id,name,module']);

        return Inertia::render('TestCases/Show', [
            'project' => $project,
            'testSuite' => $testSuite,
            'testCase' => $testCase,
        ]);
    }

    public function edit(Project $project, TestSuite $testSuite, TestCase $testCase): Response
    {
        $this->authorize('update', $project);
        abort_unless($testSuite->project_id === $project->id, 404);
        abort_unless($testCase->test_suite_id === $testSuite->id, 404);

        $testCase->load(['attachments', 'projectFeatures:id']);

        $features = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module', 'priority']);

        $allTestSuites = $project->testSuites()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'is_archived']);

        $archiveSuites = $allTestSuites->where('is_archived', true)->values();

        return Inertia::render('TestCases/Edit', [
            'project' => $project,
            'testSuite' => $testSuite,
            'testCase' => $testCase,
            'features' => $features,
            'allTestSuites' => $allTestSuites,
            'archiveSuites' => $archiveSuites,
        ]);
    }

    public function update(UpdateTestCaseRequest $request, Project $project, TestSuite $testSuite, TestCase $testCase)
    {
        $this->authorize('update', $project);
        abort_unless($testSuite->project_id === $project->id, 404);
        abort_unless($testCase->test_suite_id === $testSuite->id, 404);

        $validated = $request->validated();

        $testCase->update(collect($validated)->except(['attachments', 'feature_ids'])->toArray());
        $this->featureLinkingService->sync($testCase, $validated['feature_ids'] ?? []);

        $this->attachmentService->storeFromRequest($testCase, $request, 'attachments/test-cases');

        return redirect("/projects/{$project->id}/test-suites/{$testSuite->id}/test-cases/{$testCase->id}")
            ->with('success', 'Test case updated successfully.');
    }

    public function destroy(Project $project, TestSuite $testSuite, TestCase $testCase)
    {
        $this->authorize('update', $project);
        abort_unless($testSuite->project_id === $project->id, 404);
        abort_unless($testCase->test_suite_id === $testSuite->id, 404);

        $this->attachmentService->deleteAll($testCase);
        $testCase->delete();

        return redirect()->route('test-suites.show', [$project, $testSuite])
            ->with('success', 'Test case deleted successfully.');
    }

    public function destroyAttachment(Project $project, TestSuite $testSuite, TestCase $testCase, Attachment $attachment)
    {
        $this->authorize('update', $project);
        abort_unless($testSuite->project_id === $project->id, 404);
        abort_unless($testCase->test_suite_id === $testSuite->id, 404);
        abort_unless(
            $attachment->attachable_type === TestCase::class && $attachment->attachable_id === $testCase->id,
            404
        );

        $this->attachmentService->deleteOne($attachment);
        $testCase->touch();

        return back()->with('success', 'Attachment deleted successfully.');
    }

    public function updateNote(StoreTestCaseNoteRequest $request, Project $project, TestSuite $testSuite, TestCase $testCase)
    {
        $this->authorize('update', $project);
        abort_unless($testSuite->project_id === $project->id, 404);
        abort_unless($testCase->test_suite_id === $testSuite->id, 404);

        $validated = $request->validated();

        $testCase->note()->updateOrCreate(
            ['test_case_id' => $testCase->id],
            ['content' => $validated['content'], 'updated_by' => $request->user()->id]
        );

        $testCase->touch();

        $this->achievements->checkDetailOriented($request->user());

        return back()->with('success', 'Note updated successfully.');
    }

    public function reorder(ReorderTestCasesRequest $request, Project $project, TestSuite $testSuite)
    {
        $this->authorize('update', $project);
        abort_unless($testSuite->project_id === $project->id, 404);

        $validated = $request->validated();

        $projectSuiteIds = $project->testSuites()->pluck('id');

        foreach ($validated['cases'] as $caseData) {
            if (isset($caseData['test_suite_id']) && ! $projectSuiteIds->contains($caseData['test_suite_id'])) {
                continue;
            }

            $updateData = ['order' => $caseData['order']];
            if (isset($caseData['test_suite_id'])) {
                $updateData['test_suite_id'] = $caseData['test_suite_id'];
            }

            TestCase::whereIn('test_suite_id', $projectSuiteIds)
                ->where('id', $caseData['id'])
                ->update($updateData);
        }

        return back()->with('success', 'Test cases reordered successfully.');
    }

    public function bulkDestroy(BulkDeleteTestCasesRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $projectSuiteIds = $project->testSuites()->pluck('id');

        $testCases = TestCase::whereIn('id', $validated['test_case_ids'])
            ->whereIn('test_suite_id', $projectSuiteIds)
            ->with('attachments')
            ->get();

        foreach ($testCases as $testCase) {
            $this->attachmentService->deleteAll($testCase);
            $testCase->delete();
        }

        return back()->with('success', $testCases->count().' test case(s) deleted.');
    }

    public function bulkCopy(MoveTestCasesRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $copyAttachments = $validated['copy_attachments'] ?? false;
        $copyFeatures = $validated['copy_features'] ?? false;
        $copyNotes = $validated['copy_notes'] ?? false;

        $targetProjectId = $validated['target_project_id'] ?? $project->id;
        $isCrossProject = $targetProjectId !== $project->id;

        if ($isCrossProject) {
            $targetProject = Project::findOrFail($targetProjectId);
            $this->authorize('update', $targetProject);
            $targetSuiteIds = $targetProject->testSuites()->pluck('id');
        } else {
            $targetSuiteIds = $project->testSuites()->pluck('id');
        }

        if (! $targetSuiteIds->contains($validated['target_suite_id'])) {
            return back()->withErrors(['target_suite_id' => 'Target suite does not belong to the target project.']);
        }

        $projectSuiteIds = $project->testSuites()->pluck('id');

        $testCases = TestCase::whereIn('id', $validated['test_case_ids'])
            ->whereIn('test_suite_id', $projectSuiteIds)
            ->with(['attachments', 'projectFeatures', 'note'])
            ->orderBy('order')
            ->get();

        $maxOrder = TestCase::where('test_suite_id', $validated['target_suite_id'])->max('order') ?? 0;

        $targetFeatureMap = null;
        if ($copyFeatures && $isCrossProject) {
            $targetFeatureMap = Project::findOrFail($targetProjectId)
                ->features()
                ->where('is_active', true)
                ->pluck('id', 'name');
        }

        foreach ($testCases as $testCase) {
            $maxOrder++;
            $replica = $testCase->replicate(['id', 'created_at', 'updated_at']);
            $replica->test_suite_id = $validated['target_suite_id'];
            $replica->order = $maxOrder;
            $replica->created_by = auth()->id();
            $replica->save();

            if ($copyAttachments && $testCase->attachments->isNotEmpty()) {
                $this->attachmentService->copyTo($replica, $testCase->attachments, 'attachments/test-cases');
            }

            if ($copyFeatures && $testCase->projectFeatures->isNotEmpty()) {
                if ($isCrossProject && $targetFeatureMap) {
                    $matchedIds = $testCase->projectFeatures
                        ->map(fn ($f) => $targetFeatureMap->get($f->name))
                        ->filter()
                        ->values()
                        ->all();
                    if ($matchedIds) {
                        $replica->projectFeatures()->sync($matchedIds);
                    }
                } else {
                    $replica->projectFeatures()->sync($testCase->projectFeatures->pluck('id'));
                }
            }

            if ($copyNotes && $testCase->note) {
                $replica->note()->create(['content' => $testCase->note->content]);
            }
        }

        return back()->with('success', $testCases->count().' test case(s) copied.');
    }

    public function reorderAcrossSuites(BulkUpdateTestCasesRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $projectSuiteIds = $project->testSuites()->pluck('id');

        foreach ($validated['cases'] as $caseData) {
            if (! $projectSuiteIds->contains($caseData['test_suite_id'])) {
                continue;
            }

            TestCase::whereIn('test_suite_id', $projectSuiteIds)
                ->where('id', $caseData['id'])
                ->update([
                    'order' => $caseData['order'],
                    'test_suite_id' => $caseData['test_suite_id'],
                ]);
        }

        return back()->with('success', 'Test cases reordered successfully.');
    }

    public function archive(ArchiveTestCasesRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        if (! empty($validated['archive_suite_id'])) {
            $archiveSuite = TestSuite::query()->findOrFail($validated['archive_suite_id']);
            abort_unless($archiveSuite->project_id === $project->id && $archiveSuite->is_archived, 403);
        } else {
            $maxOrder = $project->testSuites()->whereNull('parent_id')->max('order') ?? 0;

            $archiveSuite = $project->testSuites()->create([
                'name' => $validated['archive_suite_name'],
                'type' => 'functional',
                'is_archived' => true,
                'order' => $maxOrder + 1,
            ]);
        }

        $projectSuiteIds = $project->testSuites()->pluck('id');

        $testCases = TestCase::query()
            ->whereIn('id', $validated['test_case_ids'])
            ->whereIn('test_suite_id', $projectSuiteIds)
            ->get();

        $nextOrder = (TestCase::query()->where('test_suite_id', $archiveSuite->id)->max('order') ?? 0) + 1;

        foreach ($testCases as $testCase) {
            $testCase->update([
                'archived_from_suite_id' => $testCase->test_suite_id,
                'test_suite_id' => $archiveSuite->id,
                'order' => $nextOrder,
            ]);
            $nextOrder++;
        }

        return back()->with('success', $testCases->count()." test case(s) archived to \"{$archiveSuite->name}\".");
    }

    public function unarchive(UnarchiveTestCasesRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $projectSuiteIds = $project->testSuites()->pluck('id');

        $testCases = TestCase::query()
            ->whereIn('id', $validated['test_case_ids'])
            ->whereIn('test_suite_id', $projectSuiteIds)
            ->get();

        if ($validated['mode'] === 'choose') {
            $targetSuite = TestSuite::query()->findOrFail($validated['target_suite_id']);
            abort_unless($targetSuite->project_id === $project->id && ! $targetSuite->is_archived, 403);

            $nextOrder = (TestCase::query()->where('test_suite_id', $targetSuite->id)->max('order') ?? 0) + 1;

            foreach ($testCases as $testCase) {
                $testCase->update([
                    'test_suite_id' => $targetSuite->id,
                    'archived_from_suite_id' => null,
                    'order' => $nextOrder,
                ]);
                $nextOrder++;
            }

            return back()->with('success', $testCases->count()." test case(s) unarchived to \"{$targetSuite->name}\".");
        }

        $movedCount = 0;
        $skippedCount = 0;

        foreach ($testCases->groupBy('archived_from_suite_id') as $originalSuiteId => $group) {
            $originalSuite = $originalSuiteId ? TestSuite::query()->find($originalSuiteId) : null;

            if (! $originalSuite || $originalSuite->project_id !== $project->id) {
                $skippedCount += $group->count();

                continue;
            }

            $nextOrder = (TestCase::query()->where('test_suite_id', $originalSuite->id)->max('order') ?? 0) + 1;

            foreach ($group as $testCase) {
                $testCase->update([
                    'test_suite_id' => $originalSuite->id,
                    'archived_from_suite_id' => null,
                    'order' => $nextOrder,
                ]);
                $nextOrder++;
                $movedCount++;
            }
        }

        $response = back();

        if ($skippedCount > 0) {
            $response = $response->withErrors([
                'mode' => $skippedCount === 1
                    ? 'The original suite for 1 test case no longer exists. Choose a suite for it below.'
                    : "The original suite for {$skippedCount} test case(s) no longer exists. Choose a suite for them below.",
            ]);
        }

        if ($movedCount > 0) {
            $response = $response->with('success', "{$movedCount} test case(s) returned to their original suite.");
        }

        return $response;
    }

    public function export(Request $request, Project $project): StreamedResponse
    {
        $this->authorize('view', $project);

        $suiteIds = $project->testSuites()->pluck('id');

        $query = TestCase::whereIn('test_suite_id', $suiteIds)
            ->with(['testSuite', 'testSuite.parent']);

        if ($request->filled('ids')) {
            $ids = array_map('intval', explode(',', $request->input('ids')));
            $query->whereIn('id', $ids);
        }

        $testCases = $query->orderBy('test_suite_id')->orderBy('order')->get();

        $filename = str_replace(' ', '_', $project->name).'_test_cases_'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($testCases) {
            $output = fopen('php://output', 'w');

            // Add BOM for UTF-8 Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, ['Suite', 'Title', 'Description', 'Preconditions', 'Steps', 'Expected Result', 'Priority', 'Severity', 'Type', 'Automation Status', 'Tags']);

            foreach ($testCases as $tc) {
                $suiteName = $tc->testSuite->parent
                    ? $tc->testSuite->parent->name.' / '.$tc->testSuite->name
                    : $tc->testSuite->name;

                $stepsText = '';
                if (is_array($tc->steps)) {
                    $stepsText = collect($tc->steps)->map(function ($step, $i) {
                        $line = ($i + 1).'. '.$step['action'];
                        if (! empty($step['expected'])) {
                            $line .= ' | Expected: '.$step['expected'];
                        }

                        return $line;
                    })->implode("\n");
                }

                $tagsText = is_array($tc->tags) ? implode(', ', $tc->tags) : '';

                fputcsv($output, [
                    $suiteName,
                    $tc->title,
                    $tc->description ?? '',
                    $tc->preconditions ?? '',
                    $stepsText,
                    $tc->expected_result ?? '',
                    $tc->priority ?? 'medium',
                    $tc->severity ?? 'major',
                    $tc->type ?? 'functional',
                    $tc->automation_status ?? 'not_automated',
                    $tagsText,
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importFromFile(ImportTestCasesFromFileRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        // Verify suite belongs to this project
        $suite = TestSuite::where('id', $validated['test_suite_id'])
            ->where(function ($q) use ($project) {
                $q->whereIn('id', $project->testSuites()->pluck('id'));
            })
            ->first();

        if (! $suite) {
            return back()->withErrors(['test_suite_id' => 'The selected test suite does not belong to this project.']);
        }

        $created = $this->importRowsIntoSuite($suite, $validated['headers'], $validated['rows']);

        return back()->with('success', $created.' test case(s) imported successfully.');
    }

    /**
     * Create a new test suite from a CSV file, importing its rows as test cases.
     */
    public function importFromFileToNewSuite(ImportTestCasesFromFileToNewSuiteRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $maxOrder = $project->testSuites()->whereNull('parent_id')->max('order') ?? 0;

        $suite = $project->testSuites()->create([
            'name' => $validated['name'],
            'order' => $maxOrder + 1,
        ]);

        $created = $this->importRowsIntoSuite($suite, $validated['headers'], $validated['rows']);

        if ($created === 0) {
            $suite->delete();

            return back()->withErrors(['rows' => 'No valid test cases found in the file (each row needs a title).']);
        }

        return redirect()->route('test-suites.show', [$project, $suite])
            ->with('success', $created.' test case(s) imported into new suite "'.$suite->name.'".');
    }

    /**
     * Import CSV rows as test cases into the given suite.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function importRowsIntoSuite(TestSuite $suite, array $headers, array $rows): int
    {
        $fieldMap = $this->buildFieldMap($headers);
        $maxOrder = $suite->testCases()->max('order') ?? 0;
        $created = 0;

        foreach ($rows as $row) {
            $mapped = $this->mapRowToFields($row, $fieldMap);

            // Skip rows without a title
            if (empty($mapped['title'])) {
                continue;
            }

            $maxOrder++;
            $suite->testCases()->create([
                'title' => $mapped['title'],
                'description' => $mapped['description'] ?? null,
                'preconditions' => $mapped['preconditions'] ?? null,
                'steps' => $mapped['steps'] ?? null,
                'expected_result' => $mapped['expected_result'] ?? null,
                'priority' => $this->validateEnum($mapped['priority'] ?? null, ['low', 'medium', 'high', 'critical'], 'medium'),
                'severity' => $this->validateEnum($mapped['severity'] ?? null, ['trivial', 'minor', 'major', 'critical', 'blocker'], 'major'),
                'type' => $this->validateEnum($mapped['type'] ?? null, ['functional', 'smoke', 'regression', 'integration', 'acceptance', 'performance', 'security', 'usability', 'other'], 'functional'),
                'automation_status' => $this->validateEnum($mapped['automation_status'] ?? null, ['not_automated', 'to_be_automated', 'automated'], 'not_automated'),
                'tags' => $mapped['tags'] ?? null,
                'order' => $maxOrder,
                'created_by' => auth()->id(),
            ]);

            $created++;
        }

        if ($created > 0 && auth()->user()) {
            $this->achievements->checkFirstFiveTestCases(auth()->user());
        }

        return $created;
    }

    /**
     * Create a new test suite along with a single test case built from
     * pasted note lines (each line becomes a test step).
     */
    public function storeNoteAsNewSuite(StoreTestCaseNoteAsNewSuiteRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $maxOrder = $project->testSuites()->whereNull('parent_id')->max('order') ?? 0;

        $suite = $project->testSuites()->create([
            'name' => $validated['suite_name'],
            'order' => $maxOrder + 1,
        ]);

        $testCase = $suite->testCases()->create([
            'title' => $validated['title'],
            'steps' => $validated['steps'],
            'priority' => 'medium',
            'severity' => 'major',
            'type' => 'functional',
            'automation_status' => 'not_automated',
            'order' => 1,
            'created_by' => auth()->id(),
        ]);

        $this->achievements->checkFirstFiveTestCases($request->user());

        return redirect("/projects/{$project->id}/test-suites/{$suite->id}/test-cases/{$testCase->id}")
            ->with('success', 'New test suite and test case created successfully.');
    }

    /**
     * Build a mapping from column index to internal field name.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function buildFieldMap(array $headers): array
    {
        $aliases = [
            'title' => ['title', 'name', 'test case name', 'test name', 'case name'],
            'description' => ['description', 'summary', 'details'],
            'preconditions' => ['preconditions', 'pre-conditions', 'prerequisites', 'pre conditions'],
            'steps' => ['steps', 'test steps', 'steps to reproduce', 'step'],
            'expected_result' => ['expected result', 'expected', 'expected results', 'expected outcome'],
            'priority' => ['priority'],
            'severity' => ['severity'],
            'type' => ['type', 'test type', 'case type'],
            'automation_status' => ['automation status', 'automation', 'automated'],
            'tags' => ['tags', 'labels', 'keywords'],
        ];

        $map = [];
        foreach ($headers as $index => $header) {
            $normalized = strtolower(trim($header));
            foreach ($aliases as $field => $aliasList) {
                if (in_array($normalized, $aliasList, true)) {
                    $map[$index] = $field;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Map a data row to internal fields using the field map.
     *
     * @param  array<int, mixed>  $row
     * @param  array<int, string>  $fieldMap
     * @return array<string, mixed>
     */
    private function mapRowToFields(array $row, array $fieldMap): array
    {
        $result = [];

        foreach ($fieldMap as $index => $field) {
            $value = $row[$index] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $value = $this->fixDoubleEncodedUtf8((string) $value);

            if ($field === 'steps') {
                $result['steps'] = $this->parseStepsFromText($value);
            } elseif ($field === 'tags') {
                $result['tags'] = array_map('trim', explode(',', $value));
            } else {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    /**
     * Detect and fix double-encoded UTF-8 text.
     *
     * This occurs when UTF-8 bytes are read as Latin-1/ISO-8859-1 and then
     * re-encoded to UTF-8, producing sequences like C390C2A0 instead of D0A0.
     * Common when importing CSV files from external tools (TestRail, etc.).
     */
    private function fixDoubleEncodedUtf8(string $text): string
    {
        // Check for the telltale pattern of double-encoded UTF-8:
        // Sequences like \xC3\x90-\xC3\x91 followed by \xC2\x80-\xC2\xBF
        if (! preg_match('/\xC3[\x80-\xBF]\xC2[\x80-\xBF]/', $text)) {
            return $text;
        }

        $decoded = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

        if (mb_check_encoding($decoded, 'UTF-8')) {
            return $decoded;
        }

        return $text;
    }

    /**
     * Parse steps text into structured array.
     *
     * Supports formats like:
     * - "1. action | Expected: result"
     * - "1. action"
     * - Plain text lines
     *
     * @return array<int, array{action: string, expected: string}>
     */
    private function parseStepsFromText(string $text): array
    {
        $lines = preg_split('/\r?\n/', trim($text));
        $steps = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Remove leading number + dot/paren: "1. ", "1) ", "1 "
            $line = preg_replace('/^\d+[\.\)]\s*/', '', $line);

            $action = $line;
            $expected = '';

            // Check for "| Expected: result" pattern
            if (preg_match('/^(.*?)\s*\|\s*Expected:\s*(.*)$/i', $line, $matches)) {
                $action = trim($matches[1]);
                $expected = trim($matches[2]);
            }

            $steps[] = ['action' => $action, 'expected' => $expected];
        }

        return $steps;
    }

    private function validateEnum(?string $value, array $validValues, string $default): string
    {
        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim(str_replace(' ', '_', $value)));

        if (in_array($normalized, $validValues, true)) {
            return $normalized;
        }

        return $default;
    }

    /**
     * Copy attachments from a bugreport to a test case.
     */
    private function copyBugreportAttachments(Project $project, TestCase $testCase, int $bugreportId): void
    {
        $bugreport = $project->bugreports()->with('attachments')->find($bugreportId);
        if (! $bugreport) {
            return;
        }

        $this->attachmentService->copyTo($testCase, $bugreport->attachments, 'attachments/test-cases');
    }

    /**
     * Link the test case back to the originating checklist rows.
     *
     * @param  array<string, mixed>  $validated
     */
    private function linkTestCaseToChecklistRows(Project $project, TestSuite $testSuite, TestCase $testCase, array $validated): void
    {
        $checklistId = $validated['checklist_id'] ?? null;
        $rowIds = $validated['checklist_row_ids'] ?? null;
        $linkColumn = $validated['checklist_link_column'] ?? null;

        if (! $checklistId || ! $rowIds || ! $linkColumn) {
            return;
        }

        $checklist = $project->checklists()->find($checklistId);
        if (! $checklist) {
            return;
        }

        $columnsConfig = $checklist->columns_config ?? [];
        $columnExists = collect($columnsConfig)->contains('key', $linkColumn);
        if (! $columnExists) {
            return;
        }

        $url = url("/projects/{$project->id}/test-suites/{$testSuite->id}/test-cases/{$testCase->id}");
        $ids = array_map('intval', explode(',', $rowIds));

        $rows = ChecklistRow::where('checklist_id', $checklist->id)
            ->whereIn('id', $ids)
            ->get();

        foreach ($rows as $row) {
            $data = $row->data ?? [];
            $data[$linkColumn] = $url;
            $row->update(['data' => $data]);
        }
    }
}
