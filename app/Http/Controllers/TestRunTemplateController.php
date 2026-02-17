<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TestRunTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TestRunTemplateController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'environment_id' => 'nullable|exists:test_environments,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'tag_mode' => 'string|in:or,and',
            'file_pattern' => 'nullable|string|max:500',
            'options' => 'nullable|array',
        ]);

        $project->testRunTemplates()->create($validated);

        return back();
    }

    public function update(Request $request, Project $project, TestRunTemplate $template): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($template->project_id === $project->id, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'environment_id' => 'nullable|exists:test_environments,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'tag_mode' => 'string|in:or,and',
            'file_pattern' => 'nullable|string|max:500',
            'options' => 'nullable|array',
        ]);

        $template->update($validated);

        return back();
    }

    public function destroy(Project $project, TestRunTemplate $template): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($template->project_id === $project->id, 404);

        $template->delete();

        return back();
    }
}
