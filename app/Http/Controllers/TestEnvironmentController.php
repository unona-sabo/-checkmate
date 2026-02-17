<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TestEnvironment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TestEnvironmentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'nullable|string|max:500',
            'variables' => 'nullable|array',
            'workers' => 'integer|min:1|max:32',
            'retries' => 'integer|min:0|max:10',
            'browser' => 'string|in:chromium,firefox,webkit',
            'headed' => 'boolean',
            'timeout' => 'integer|min:1000|max:300000',
            'description' => 'nullable|string|max:1000',
            'is_default' => 'boolean',
        ]);

        if (! empty($validated['is_default'])) {
            $project->testEnvironments()->where('is_default', true)->update(['is_default' => false]);
        }

        $project->testEnvironments()->create($validated);

        return back();
    }

    public function update(Request $request, Project $project, TestEnvironment $environment): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($environment->project_id === $project->id, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'nullable|string|max:500',
            'variables' => 'nullable|array',
            'workers' => 'integer|min:1|max:32',
            'retries' => 'integer|min:0|max:10',
            'browser' => 'string|in:chromium,firefox,webkit',
            'headed' => 'boolean',
            'timeout' => 'integer|min:1000|max:300000',
            'description' => 'nullable|string|max:1000',
            'is_default' => 'boolean',
        ]);

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
