<?php

namespace App\Http\Controllers;

use App\Models\DesignLink;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
        ]);

        $project->designLinks()->create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Design link created successfully.');
    }

    public function update(Request $request, Project $project, DesignLink $designLink): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
        ]);

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
