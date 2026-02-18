<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestRunTemplate\UpsertTestRunTemplateRequest;
use App\Models\Project;
use App\Models\TestRunTemplate;
use Illuminate\Http\RedirectResponse;

class TestRunTemplateController extends Controller
{
    public function store(UpsertTestRunTemplateRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->testRunTemplates()->create($validated);

        return back();
    }

    public function update(UpsertTestRunTemplateRequest $request, Project $project, TestRunTemplate $template): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($template->project_id === $project->id, 404);

        $validated = $request->validated();

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
