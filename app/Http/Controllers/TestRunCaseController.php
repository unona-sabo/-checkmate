<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestRunCase\BulkUpdateTestRunCasesRequest;
use App\Http\Requests\TestRunCase\UpdateTestRunCaseRequest;
use App\Models\Project;
use App\Models\TestRun;
use App\Models\TestRunCase;
use Illuminate\Http\Request;

class TestRunCaseController extends Controller
{
    public function update(UpdateTestRunCaseRequest $request, Project $project, TestRun $testRun, TestRunCase $testRunCase)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

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

    public function bulkUpdate(BulkUpdateTestRunCasesRequest $request, Project $project, TestRun $testRun)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

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

    public function destroy(Project $project, TestRun $testRun, TestRunCase $testRunCase)
    {
        $this->authorize('update', $project);

        if ($testRun->status !== 'active') {
            return back()->with('error', 'Can only remove cases from active test runs.');
        }

        $testRunCase->delete();

        $testRun->updateStats();
        $testRun->updateProgress();

        return back()->with('success', 'Item removed from test run.');
    }
}
