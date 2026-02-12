<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TestCaseController extends Controller
{
    public function create(Project $project, TestSuite $testSuite): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('TestCases/Create', [
            'project' => $project,
            'testSuite' => $testSuite,
        ]);
    }

    public function store(Request $request, Project $project, TestSuite $testSuite)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'preconditions' => 'nullable|string',
            'steps' => 'nullable|array',
            'steps.*.action' => 'required|string',
            'steps.*.expected' => 'nullable|string',
            'expected_result' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'severity' => 'required|in:trivial,minor,major,critical,blocker',
            'type' => 'required|in:functional,smoke,regression,integration,acceptance,performance,security,usability,other',
            'automation_status' => 'required|in:not_automated,to_be_automated,automated',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip',
        ]);

        $maxOrder = $testSuite->testCases()->max('order') ?? 0;
        $validated['order'] = $maxOrder + 1;

        $testCase = $testSuite->testCases()->create(collect($validated)->except('attachments')->toArray());

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
            ->with('success', 'Test case created successfully.');
    }

    public function show(Project $project, TestSuite $testSuite, TestCase $testCase): Response
    {
        $this->authorize('view', $project);

        $testCase->load(['note', 'attachments']);

        return Inertia::render('TestCases/Show', [
            'project' => $project,
            'testSuite' => $testSuite,
            'testCase' => $testCase,
        ]);
    }

    public function edit(Project $project, TestSuite $testSuite, TestCase $testCase): Response
    {
        $this->authorize('update', $project);

        $testCase->load('attachments');

        return Inertia::render('TestCases/Edit', [
            'project' => $project,
            'testSuite' => $testSuite,
            'testCase' => $testCase,
        ]);
    }

    public function update(Request $request, Project $project, TestSuite $testSuite, TestCase $testCase)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'preconditions' => 'nullable|string',
            'steps' => 'nullable|array',
            'steps.*.action' => 'required|string',
            'steps.*.expected' => 'nullable|string',
            'expected_result' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'severity' => 'required|in:trivial,minor,major,critical,blocker',
            'type' => 'required|in:functional,smoke,regression,integration,acceptance,performance,security,usability,other',
            'automation_status' => 'required|in:not_automated,to_be_automated,automated',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip',
        ]);

        $testCase->update(collect($validated)->except('attachments')->toArray());

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

        return back()->with('success', 'Attachment deleted successfully.');
    }

    public function updateNote(Request $request, Project $project, TestSuite $testSuite, TestCase $testCase)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'content' => 'nullable|string',
        ]);

        $testCase->note()->updateOrCreate(
            ['test_case_id' => $testCase->id],
            ['content' => $validated['content']]
        );

        return back()->with('success', 'Note updated successfully.');
    }

    public function reorder(Request $request, Project $project, TestSuite $testSuite)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'cases' => 'required|array',
            'cases.*.id' => 'required|exists:test_cases,id',
            'cases.*.order' => 'required|integer',
            'cases.*.test_suite_id' => 'nullable|exists:test_suites,id',
        ]);

        foreach ($validated['cases'] as $caseData) {
            $updateData = ['order' => $caseData['order']];
            if (isset($caseData['test_suite_id'])) {
                $updateData['test_suite_id'] = $caseData['test_suite_id'];
            }
            TestCase::where('id', $caseData['id'])->update($updateData);
        }

        return back()->with('success', 'Test cases reordered successfully.');
    }

    public function reorderAcrossSuites(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'cases' => 'required|array',
            'cases.*.id' => 'required|exists:test_cases,id',
            'cases.*.order' => 'required|integer',
            'cases.*.test_suite_id' => 'required|exists:test_suites,id',
        ]);

        foreach ($validated['cases'] as $caseData) {
            TestCase::where('id', $caseData['id'])->update([
                'order' => $caseData['order'],
                'test_suite_id' => $caseData['test_suite_id'],
            ]);
        }

        return back()->with('success', 'Test cases reordered successfully.');
    }
}
