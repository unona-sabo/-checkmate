<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestEnvironment\UpsertTestEnvironmentRequest;
use App\Models\Project;
use App\Models\TestEnvironment;
use Illuminate\Http\RedirectResponse;

class TestEnvironmentController extends Controller
{
    public function store(UpsertTestEnvironmentRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        if (! empty($validated['is_default'])) {
            $project->testEnvironments()->where('is_default', true)->update(['is_default' => false]);
        }

        $project->testEnvironments()->create($validated);

        return back();
    }

    public function update(UpsertTestEnvironmentRequest $request, Project $project, TestEnvironment $environment): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($environment->project_id === $project->id, 404);

        $validated = $request->validated();

        if (! empty($validated['is_default']) && ! $environment->is_default) {
            $project->testEnvironments()->where('is_default', true)->update(['is_default' => false]);
        }

        $environment->update($validated);

        return back();
    }

    public function destroy(Project $project, TestEnvironment $environment): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($environment->project_id === $project->id, 404);

        $environment->delete();

        return back();
    }
}
