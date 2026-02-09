<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use Illuminate\Http\Request;
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
        ]);

        $maxOrder = $testSuite->testCases()->max('order') ?? 0;
        $validated['order'] = $maxOrder + 1;

        $testCase = $testSuite->testCases()->create($validated);

        return redirect()->route('test-cases.show', [$project, $testSuite, $testCase])
            ->with('success', 'Test case created successfully.');
    }

    public function show(Project $project, TestSuite $testSuite, TestCase $testCase): Response
    {
        $this->authorize('view', $project);

        $testCase->load('note');

        return Inertia::render('TestCases/Show', [
            'project' => $project,
            'testSuite' => $testSuite,
            'testCase' => $testCase,
        ]);
    }

    public function edit(Project $project, TestSuite $testSuite, TestCase $testCase): Response
    {
        $this->authorize('update', $project);

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
        ]);

        $testCase->update($validated);

        return redirect()->route('test-cases.show', [$project, $testSuite, $testCase])
            ->with('success', 'Test case updated successfully.');
    }

    public function destroy(Project $project, TestSuite $testSuite, TestCase $testCase)
    {
        $this->authorize('update', $project);

        $testCase->delete();

        return redirect()->route('test-suites.show', [$project, $testSuite])
            ->with('success', 'Test case deleted successfully.');
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
        ]);

        foreach ($validated['cases'] as $caseData) {
            TestCase::where('id', $caseData['id'])->update([
                'order' => $caseData['order'],
            ]);
        }

        return back()->with('success', 'Test cases reordered successfully.');
    }
}
