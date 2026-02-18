<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bugreport\StoreBugreportRequest;
use App\Http\Requests\Bugreport\UpdateBugreportRequest;
use App\Models\Attachment;
use App\Models\Bugreport;
use App\Models\ChecklistRow;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
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
            'users' => Inertia::defer(fn () => $project->users()->get(['users.id', 'users.name'])),
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        $users = $project->users()->get(['users.id', 'users.name']);

        $features = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module', 'priority']);

        return Inertia::render('Bugreports/Create', [
            'project' => $project,
            'users' => $users,
            'features' => $features,
        ]);
    }

    public function store(StoreBugreportRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $validated['reported_by'] = auth()->id();

        $checklistFields = ['checklist_id', 'checklist_row_ids', 'checklist_link_column'];
        $bugreport = $project->bugreports()->create(
            collect($validated)->except(['attachments', 'feature_ids', ...$checklistFields])->toArray()
        );

        if (! empty($validated['feature_ids'])) {
            $bugreport->projectFeatures()->sync($validated['feature_ids']);
        }

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/bugreports', 'public');
                $bugreport->attachments()->create([
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        $this->linkBugreportToChecklistRow($project, $bugreport, $validated);

        return redirect()->route('bugreports.show', [$project, $bugreport])
            ->with('success', 'Bug report created successfully.');
    }

    public function show(Project $project, Bugreport $bugreport): Response
    {
        $this->authorize('view', $project);

        $bugreport->load(['reporter', 'assignee', 'attachments']);

        return Inertia::render('Bugreports/Show', [
            'project' => $project,
            'bugreport' => $bugreport,
        ]);
    }

    public function edit(Project $project, Bugreport $bugreport): Response
    {
        $this->authorize('update', $project);

        $users = $project->users()->get(['users.id', 'users.name']);
        $bugreport->load(['attachments', 'projectFeatures:id']);

        $features = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module', 'priority']);

        return Inertia::render('Bugreports/Edit', [
            'project' => $project,
            'bugreport' => $bugreport,
            'users' => $users,
            'features' => $features,
        ]);
    }

    public function update(UpdateBugreportRequest $request, Project $project, Bugreport $bugreport)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $bugreport->update(collect($validated)->except(['attachments', 'feature_ids'])->toArray());
        $bugreport->projectFeatures()->sync($validated['feature_ids'] ?? []);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/bugreports', 'public');
                $bugreport->attachments()->create([
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        return redirect()->route('bugreports.show', [$project, $bugreport])
            ->with('success', 'Bug report updated successfully.');
    }

    public function destroy(Project $project, Bugreport $bugreport)
    {
        $this->authorize('update', $project);

        foreach ($bugreport->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->stored_path);
        }

        $bugreport->delete();

        return redirect()->route('bugreports.index', $project)
            ->with('success', 'Bug report deleted successfully.');
    }

    public function destroyAttachment(Project $project, Bugreport $bugreport, Attachment $attachment)
    {
        $this->authorize('update', $project);

        Storage::disk('public')->delete($attachment->stored_path);
        $attachment->delete();

        return back()->with('success', 'Attachment deleted successfully.');
    }

    /**
     * Link the bugreport back to the originating checklist row.
     *
     * @param  array<string, mixed>  $validated
     */
    private function linkBugreportToChecklistRow(Project $project, Bugreport $bugreport, array $validated): void
    {
        $checklistId = $validated['checklist_id'] ?? null;
        $rowIds = $validated['checklist_row_ids'] ?? null;
        $linkColumn = $validated['checklist_link_column'] ?? null;

        if (! $checklistId || ! $rowIds || ! $linkColumn) {
            return;
        }

        $checklist = $project->checklists()->find($checklistId);
        if (! $checklist) {
            return;
        }

        $columnsConfig = $checklist->columns_config ?? [];
        $columnExists = collect($columnsConfig)->contains('key', $linkColumn);
        if (! $columnExists) {
            return;
        }

        $firstRowId = (int) explode(',', $rowIds)[0];
        $row = ChecklistRow::where('checklist_id', $checklist->id)->find($firstRowId);
        if (! $row) {
            return;
        }

        $data = $row->data ?? [];
        $data[$linkColumn] = url("/projects/{$project->id}/bugreports/{$bugreport->id}");
        $row->update(['data' => $data]);
    }
}
