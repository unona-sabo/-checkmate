<?php

namespace App\Http\Controllers;

use App\Http\Requests\Note\UpsertNoteRequest;
use App\Models\Documentation;
use App\Models\Note;
use App\Models\Project;
use Inertia\Inertia;

class NoteController extends Controller
{
    /**
     * Display a listing of the notes.
     */
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        $notes = $project->notes()
            ->with('documentation')
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('Notes/Index', [
            'project' => $project,
            'notes' => $notes,
        ]);
    }

    /**
     * Show the form for creating a new note.
     */
    public function create(Project $project)
    {
        $this->authorize('update', $project);

        $documentations = $project->documentations()
            ->orderBy('title')
            ->get(['id', 'title', 'category']);

        return Inertia::render('Notes/Create', [
            'project' => $project,
            'documentations' => $documentations,
        ]);
    }

    /**
     * Store a newly created note in storage.
     */
    public function store(UpsertNoteRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $project->notes()->create($validated);

        return redirect()->route('projects.notes.index', $project)
            ->with('success', 'Note created successfully.');
    }

    /**
     * Display the specified note.
     */
    public function show(Project $project, Note $note)
    {
        $this->authorize('view', $project);

        $note->load('documentation');

        $documentations = $project->documentations()
            ->orderBy('title')
            ->get(['id', 'title', 'category']);

        return Inertia::render('Notes/Show', [
            'project' => $project,
            'note' => $note,
            'documentations' => $documentations,
        ]);
    }

    /**
     * Update the specified note in storage.
     */
    public function update(UpsertNoteRequest $request, Project $project, Note $note)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $note->update($validated);

        return redirect()->back()
            ->with('success', 'Note updated successfully.');
    }

    /**
     * Remove the specified note from storage.
     */
    public function destroy(Project $project, Note $note)
    {
        $this->authorize('update', $project);

        $note->delete();

        return redirect()->route('projects.notes.index', $project)
            ->with('success', 'Note deleted successfully.');
    }

    /**
     * Publish a note to its associated documentation.
     */
    public function publish(Project $project, Note $note)
    {
        $this->authorize('update', $project);

        if (! $note->documentation_id) {
            return redirect()->back()
                ->with('error', 'Please select a documentation to publish to.');
        }

        $documentation = Documentation::find($note->documentation_id);

        // Append note content to documentation
        $newContent = $documentation->content
            ? $documentation->content."\n\n".$note->content
            : $note->content;

        $documentation->update(['content' => $newContent]);

        $note->update(['is_draft' => false]);

        return redirect()->back()
            ->with('success', 'Note published to documentation.');
    }
}
