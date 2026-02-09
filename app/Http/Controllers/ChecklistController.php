<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChecklistController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $checklists = $project->checklists()
            ->withCount('rows')
            ->latest()
            ->get(['id', 'project_id', 'name', 'columns_config', 'created_at', 'updated_at']);

        return Inertia::render('Checklists/Index', [
            'project' => $project,
            'checklists' => $checklists,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Checklists/Create', [
            'project' => $project,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'columns_config' => 'nullable|array',
            'columns_config.*.key' => 'required|string',
            'columns_config.*.label' => 'required|string',
            'columns_config.*.type' => 'required|in:text,checkbox,select,date',
            'columns_config.*.width' => 'nullable|integer|min:50',
            'columns_config.*.options' => 'nullable|array',
            'columns_config.*.options.*.value' => 'required|string',
            'columns_config.*.options.*.label' => 'required|string',
            'columns_config.*.options.*.color' => 'nullable|string|max:7',
        ]);

        $checklist = $project->checklists()->create($validated);

        return redirect()->route('checklists.show', [$project, $checklist])
            ->with('success', 'Checklist created successfully.');
    }

    public function show(Project $project, Checklist $checklist): Response
    {
        $this->authorize('view', $project);

        $checklist->load(['rows', 'note']);

        $checklists = $project->checklists()
            ->get(['id', 'project_id', 'name', 'columns_config']);

        return Inertia::render('Checklists/Show', [
            'project' => $project,
            'checklist' => $checklist,
            'checklists' => $checklists,
        ]);
    }

    public function edit(Project $project, Checklist $checklist): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Checklists/Edit', [
            'project' => $project,
            'checklist' => $checklist,
        ]);
    }

    public function update(Request $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'columns_config' => 'nullable|array',
            'columns_config.*.key' => 'required|string',
            'columns_config.*.label' => 'required|string',
            'columns_config.*.type' => 'required|in:text,checkbox,select,date',
            'columns_config.*.width' => 'nullable|integer|min:50',
            'columns_config.*.options' => 'nullable|array',
            'columns_config.*.options.*.value' => 'required|string',
            'columns_config.*.options.*.label' => 'required|string',
            'columns_config.*.options.*.color' => 'nullable|string|max:7',
        ]);

        $checklist->update($validated);

        return redirect()->route('checklists.show', [$project, $checklist])
            ->with('success', 'Checklist updated successfully.');
    }

    public function destroy(Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $checklist->delete();

        return redirect()->route('checklists.index', $project)
            ->with('success', 'Checklist deleted successfully.');
    }

    public function updateRows(Request $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'rows' => 'required|array',
            'rows.*.id' => 'nullable|integer',
            'rows.*.data' => 'required|array',
            'rows.*.order' => 'required|integer',
            'rows.*.row_type' => 'nullable|in:normal,section_header',
            'rows.*.background_color' => 'nullable|string|max:7',
            'rows.*.font_color' => 'nullable|string|max:7',
            'rows.*.font_weight' => 'nullable|in:normal,medium,semibold,bold',
            'columns_config' => 'nullable|array',
            'columns_config.*.key' => 'required|string',
            'columns_config.*.label' => 'required|string',
            'columns_config.*.type' => 'required|in:text,checkbox,select,date',
            'columns_config.*.width' => 'nullable|integer|min:50',
            'columns_config.*.options' => 'nullable|array',
            'columns_config.*.options.*.value' => 'required|string',
            'columns_config.*.options.*.label' => 'required|string',
            'columns_config.*.options.*.color' => 'nullable|string|max:7',
        ]);

        if (isset($validated['columns_config'])) {
            $checklist->update(['columns_config' => $validated['columns_config']]);
        }

        $existingIds = [];

        foreach ($validated['rows'] as $rowData) {
            $updateData = [
                'data' => $rowData['data'],
                'order' => $rowData['order'],
                'row_type' => $rowData['row_type'] ?? 'normal',
                'background_color' => $rowData['background_color'] ?? null,
                'font_color' => $rowData['font_color'] ?? null,
                'font_weight' => $rowData['font_weight'] ?? 'normal',
            ];

            if (isset($rowData['id'])) {
                $checklist->rows()->where('id', $rowData['id'])->update($updateData);
                $existingIds[] = $rowData['id'];
            } else {
                $row = $checklist->rows()->create($updateData);
                $existingIds[] = $row->id;
            }
        }

        $checklist->rows()->whereNotIn('id', $existingIds)->delete();

        return back()->with('success', 'Checklist rows updated successfully.');
    }

    public function updateNote(Request $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'content' => 'nullable|string',
        ]);

        $checklist->note()->updateOrCreate(
            ['checklist_id' => $checklist->id],
            ['content' => $validated['content']]
        );

        return back()->with('success', 'Note updated successfully.');
    }

    public function importFromNotes(Request $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'notes' => 'required|array',
            'notes.*' => 'required|string',
            'column_key' => 'required|string',
        ]);

        $columns = $checklist->columns_config ?? [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'checkbox'],
        ];

        $columnExists = collect($columns)->contains('key', $validated['column_key']);

        if (! $columnExists) {
            return back()->withErrors(['column_key' => 'Column not found in checklist.']);
        }

        // Find the last row with content in the target column
        $existingRows = $checklist->rows()->orderBy('order')->get();
        $lastFilledOrder = -1;

        foreach ($existingRows as $row) {
            $cellValue = $row->data[$validated['column_key']] ?? null;
            if ($cellValue !== null && $cellValue !== '') {
                $lastFilledOrder = $row->order;
            }
        }

        // Insert position is after the last filled row
        $insertAfterOrder = $lastFilledOrder;
        $notesCount = count($validated['notes']);

        // Shift all rows after the insert position to make room for new notes
        $rowsToShift = $checklist->rows()
            ->where('order', '>', $insertAfterOrder)
            ->orderBy('order', 'desc')
            ->get();

        foreach ($rowsToShift as $row) {
            $row->update(['order' => $row->order + $notesCount]);
        }

        // Insert new notes starting after the last filled row
        foreach ($validated['notes'] as $index => $note) {
            $data = [];
            foreach ($columns as $col) {
                if ($col['key'] === $validated['column_key']) {
                    $data[$col['key']] = $note;
                } elseif ($col['type'] === 'checkbox') {
                    $data[$col['key']] = false;
                } else {
                    $data[$col['key']] = '';
                }
            }

            $checklist->rows()->create([
                'data' => $data,
                'order' => $insertAfterOrder + 1 + $index,
                'row_type' => 'normal',
            ]);
        }

        return redirect()->route('checklists.show', [$project, $checklist])
            ->with('success', count($validated['notes']).' items imported successfully.');
    }
}
