<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TestRun;
use App\Models\TestRunCase;
use Illuminate\Http\Request;

class TestRunCaseController extends Controller
{
    public function update(Request $request, Project $project, TestRun $testRun, TestRunCase $testRunCase)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'status' => 'required|in:untested,passed,failed,blocked,skipped,retest',
            'actual_result' => 'nullable|string',
            'time_spent' => 'nullable|integer|min:0',
            'clickup_link' => 'nullable|url|max:255',
            'qase_link' => 'nullable|url|max:255',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validated['status'] !== 'untested' && $testRunCase->status === 'untested') {
            $validated['tested_at'] = now();
        }

        $testRunCase->update($validated);

        if ($validated['status'] !== 'untested' && $testRun->started_at === null) {
            $testRun->update(['started_at' => now()]);
        }

        $testRun->updateProgress();
        $testRun->updateStats();

        return back()->with('success', 'Test case result updated.');
    }

    public function bulkUpdate(Request $request, Project $project, TestRun $testRun)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'test_run_case_ids' => 'required|array|min:1',
            'test_run_case_ids.*' => 'exists:test_run_cases,id',
            'status' => 'nullable|in:untested,passed,failed,blocked,skipped,retest',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $updateData = [];

        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
            if ($validated['status'] !== 'untested') {
                $updateData['tested_at'] = now();
            }
        }

        if (array_key_exists('assigned_to', $validated)) {
            $updateData['assigned_to'] = $validated['assigned_to'];
        }

        if (! empty($updateData)) {
            TestRunCase::whereIn('id', $validated['test_run_case_ids'])
                ->where('test_run_id', $testRun->id)
                ->update($updateData);
        }

        if (isset($validated['status']) && $validated['status'] !== 'untested' && $testRun->started_at === null) {
            $testRun->update(['started_at' => now()]);
        }

        $testRun->updateProgress();
        $testRun->updateStats();

        return back()->with('success', 'Test cases updated.');
    }

    public function assignToMe(Request $request, Project $project, TestRun $testRun, TestRunCase $testRunCase)
    {
        $this->authorize('update', $project);

        $testRunCase->update(['assigned_to' => auth()->id()]);

        return back()->with('success', 'Test case assigned to you.');
    }
}
