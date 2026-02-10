<?php

namespace App\Http\Controllers;

use App\Models\Documentation;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentationController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $documentations = $project->documentations()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('order')
            ->get();

        return Inertia::render('Documentations/Index', [
            'project' => $project,
            'documentations' => $documentations,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        $parentOptions = $project->documentations()
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get(['id', 'title']);

        return Inertia::render('Documentations/Create', [
            'project' => $project,
            'parentOptions' => $parentOptions,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:documentations,id',
        ]);

        $maxOrder = $project->documentations()
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->max('order') ?? -1;

        $validated['order'] = $maxOrder + 1;

        $documentation = $project->documentations()->create($validated);

        return redirect()->route('documentations.show', [$project, $documentation])
            ->with('success', 'Documentation created successfully.');
    }

    public function show(Project $project, Documentation $documentation): Response
    {
        $this->authorize('view', $project);

        $documentation->load('children');

        $allDocs = $project->documentations()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('order')
            ->get();

        return Inertia::render('Documentations/Show', [
            'project' => $project,
            'documentation' => $documentation,
            'allDocs' => $allDocs,
        ]);
    }

    public function edit(Project $project, Documentation $documentation): Response
    {
        $this->authorize('update', $project);

        $parentOptions = $project->documentations()
            ->whereNull('parent_id')
            ->where('id', '!=', $documentation->id)
            ->orderBy('order')
            ->get(['id', 'title']);

        return Inertia::render('Documentations/Edit', [
            'project' => $project,
            'documentation' => $documentation,
            'parentOptions' => $parentOptions,
        ]);
    }

    public function update(Request $request, Project $project, Documentation $documentation)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:documentations,id',
        ]);

        $documentation->update($validated);

        return redirect()->route('documentations.show', [$project, $documentation])
            ->with('success', 'Documentation updated successfully.');
    }

    public function destroy(Project $project, Documentation $documentation)
    {
        $this->authorize('update', $project);

        $documentation->delete();

        return redirect()->route('documentations.index', $project)
            ->with('success', 'Documentation deleted successfully.');
    }

    public function reorder(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:documentations,id',
            'items.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            Documentation::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return back()->with('success', 'Order updated successfully.');
    }
}
