<?php

namespace App\Http\Controllers;

use App\Http\Requests\DesignLink\UpsertDesignLinkRequest;
use App\Models\DesignLink;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DesignLinkController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $designLinks = $project->designLinks()
            ->with('creator:id,name')
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Design/Index', [
            'project' => $project,
            'designLinks' => $designLinks,
        ]);
    }

    public function store(UpsertDesignLinkRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->designLinks()->create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Design link created successfully.');
    }

    public function update(UpsertDesignLinkRequest $request, Project $project, DesignLink $designLink): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $designLink->update($validated);

        return back()->with('success', 'Design link updated successfully.');
    }

    public function destroy(Project $project, DesignLink $designLink): RedirectResponse
    {
        $this->authorize('update', $project);

        $designLink->delete();

        return back()->with('success', 'Design link deleted successfully.');
    }
}
