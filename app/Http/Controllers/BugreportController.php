<?php

namespace App\Http\Controllers;

use App\Models\Bugreport;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BugreportController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $bugreports = $project->bugreports()
            ->with(['reporter', 'assignee'])
            ->latest()
            ->get();

        return Inertia::render('Bugreports/Index', [
            'project' => $project,
            'bugreports' => $bugreports,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        $users = $project->users()->get(['users.id', 'users.name']);

        return Inertia::render('Bugreports/Create', [
            'project' => $project,
            'users' => $users,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'steps_to_reproduce' => 'nullable|string',
            'expected_result' => 'nullable|string',
            'actual_result' => 'nullable|string',
            'severity' => 'required|in:critical,major,minor,trivial',
            'priority' => 'required|in:high,medium,low',
            'status' => 'required|in:new,open,in_progress,resolved,closed,reopened',
            'environment' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $validated['reported_by'] = auth()->id();

        $bugreport = $project->bugreports()->create($validated);

        return redirect()->route('bugreports.show', [$project, $bugreport])
            ->with('success', 'Bug report created successfully.');
    }

    public function show(Project $project, Bugreport $bugreport): Response
    {
        $this->authorize('view', $project);

        $bugreport->load(['reporter', 'assignee']);

        return Inertia::render('Bugreports/Show', [
            'project' => $project,
            'bugreport' => $bugreport,
        ]);
    }

    public function edit(Project $project, Bugreport $bugreport): Response
    {
        $this->authorize('update', $project);

        $users = $project->users()->get(['users.id', 'users.name']);

        return Inertia::render('Bugreports/Edit', [
            'project' => $project,
            'bugreport' => $bugreport,
            'users' => $users,
        ]);
    }

    public function update(Request $request, Project $project, Bugreport $bugreport)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'steps_to_reproduce' => 'nullable|string',
            'expected_result' => 'nullable|string',
            'actual_result' => 'nullable|string',
            'severity' => 'required|in:critical,major,minor,trivial',
            'priority' => 'required|in:high,medium,low',
            'status' => 'required|in:new,open,in_progress,resolved,closed,reopened',
            'environment' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $bugreport->update($validated);

        return redirect()->route('bugreports.show', [$project, $bugreport])
            ->with('success', 'Bug report updated successfully.');
    }

    public function destroy(Project $project, Bugreport $bugreport)
    {
        $this->authorize('update', $project);

        $bugreport->delete();

        return redirect()->route('bugreports.index', $project)
            ->with('success', 'Bug report deleted successfully.');
    }
}
