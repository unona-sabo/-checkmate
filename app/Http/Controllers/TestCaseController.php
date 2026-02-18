<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestCase\BulkDeleteTestCasesRequest;
use App\Http\Requests\TestCase\BulkUpdateTestCasesRequest;
use App\Http\Requests\TestCase\MoveTestCasesRequest;
use App\Http\Requests\TestCase\ReorderTestCasesRequest;
use App\Http\Requests\TestCase\StoreTestCaseNoteRequest;
use App\Http\Requests\TestCase\StoreTestCaseRequest;
use App\Http\Requests\TestCase\UpdateTestCaseRequest;
use App\Models\Attachment;
use App\Models\ChecklistRow;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TestCaseController extends Controller
{
    public function create(Project $project, TestSuite $testSuite): Response
    {
        $this->authorize('update', $project);

        $features = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module', 'priority']);

        return Inertia::render('TestCases/Create', [
            'project' => $project,
            'testSuite' => $testSuite,
            'features' => $features,
        ]);
    }

    public function store(StoreTestCaseRequest $request, Project $project, TestSuite $testSuite)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $checklistFields = ['checklist_id', 'checklist_row_ids', 'checklist_link_column'];

        $maxOrder = $testSuite->testCases()->max('order') ?? 0;
        $validated['order'] = $maxOrder + 1;
        $validated['created_by'] = auth()->id();

        $testCase = $testSuite->testCases()->create(collect($validated)->except(['attachments', 'feature_ids', ...$checklistFields])->toArray());

        if (! empty($validated['feature_ids'])) {
            $testCase->projectFeatures()->sync($validated['feature_ids']);
        }

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/test-cases', 'public');
                $testCase->attachments()->create([
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        $this->linkTestCaseToChecklistRows($project, $testSuite, $testCase, $validated);

        return redirect("/projects/{$project->id}/test-suites/{$testSuite->id}/test-cases/{$testCase->id}")
            ->with('success', 'Test case created successfully.');
    }

    public function show(Project $project, TestSuite $testSuite, TestCase $testCase): Response
    {
        $this->authorize('view', $project);

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

        $testCase->load(['attachments', 'projectFeatures:id']);

        $features = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module', 'priority']);

        return Inertia::render('TestCases/Edit', [
            'project' => $project,
            'testSuite' => $testSuite,
            'testCase' => $testCase,
            'features' => $features,
        ]);
    }

    public function update(UpdateTestCaseRequest $request, Project $project, TestSuite $testSuite, TestCase $testCase)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $testCase->update(collect($validated)->except(['attachments', 'feature_ids'])->toArray());
        $testCase->projectFeatures()->sync($validated['feature_ids'] ?? []);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/test-cases', 'public');
                $testCase->attachments()->create([
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        return redirect("/projects/{$project->id}/test-suites/{$testSuite->id}/test-cases/{$testCase->id}")
            ->with('success', 'Test case updated successfully.');
    }

    public function destroy(Project $project, TestSuite $testSuite, TestCase $testCase)
    {
        $this->authorize('update', $project);

        foreach ($testCase->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->stored_path);
        }

        $testCase->delete();

        return redirect()->route('test-suites.show', [$project, $testSuite])
            ->with('success', 'Test case deleted successfully.');
    }

    public function destroyAttachment(Project $project, TestSuite $testSuite, TestCase $testCase, Attachment $attachment)
    {
        $this->authorize('update', $project);

        Storage::disk('public')->delete($attachment->stored_path);
        $attachment->delete();
        $testCase->touch();

        return back()->with('success', 'Attachment deleted successfully.');
    }

    public function updateNote(StoreTestCaseNoteRequest $request, Project $project, TestSuite $testSuite, TestCase $testCase)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $testCase->note()->updateOrCreate(
            ['test_case_id' => $testCase->id],
            ['content' => $validated['content']]
        );

        $testCase->touch();

        return back()->with('success', 'Note updated successfully.');
    }

    public function reorder(ReorderTestCasesRequest $request, Project $project, TestSuite $testSuite)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        foreach ($validated['cases'] as $caseData) {
            $updateData = ['order' => $caseData['order']];
            if (isset($caseData['test_suite_id'])) {
                $updateData['test_suite_id'] = $caseData['test_suite_id'];
            }
            TestCase::where('id', $caseData['id'])->update($updateData);
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
            foreach ($testCase->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->stored_path);
            }
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
                foreach ($testCase->attachments as $attachment) {
                    if (Storage::disk('public')->exists($attachment->stored_path)) {
                        $extension = pathinfo($attachment->stored_path, PATHINFO_EXTENSION);
                        $newPath = 'attachments/test-cases/'.uniqid().'.'.$extension;
                        Storage::disk('public')->copy($attachment->stored_path, $newPath);

                        $replica->attachments()->create([
                            'original_filename' => $attachment->original_filename,
                            'stored_path' => $newPath,
                            'mime_type' => $attachment->mime_type,
                            'size' => $attachment->size,
                        ]);
                    }
                }
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

        foreach ($validated['cases'] as $caseData) {
            TestCase::where('id', $caseData['id'])->update([
                'order' => $caseData['order'],
                'test_suite_id' => $caseData['test_suite_id'],
            ]);
        }

        return back()->with('success', 'Test cases reordered successfully.');
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
