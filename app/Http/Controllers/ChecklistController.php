<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checklist\BulkCreateRowsRequest;
use App\Http\Requests\Checklist\CopyRowsRequest;
use App\Http\Requests\Checklist\PatchChecklistRowsRequest;
use App\Http\Requests\Checklist\ReorderChecklistsRequest;
use App\Http\Requests\Checklist\StoreChecklistNoteRequest;
use App\Http\Requests\Checklist\StoreChecklistRequest;
use App\Http\Requests\Checklist\UpdateChecklistRequest;
use App\Http\Requests\Checklist\UpdateChecklistRowsRequest;
use App\Models\Checklist;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChecklistController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $checklists = $project->checklists()
            ->with(['rows:id,checklist_id,data,row_type', 'sectionHeaders:id,checklist_id,data,order', 'projectFeatures:id,name,module'])
            ->orderBy('order')
            ->get(['id', 'project_id', 'name', 'columns_config', 'order', 'category', 'created_at', 'updated_at']);

        foreach ($checklists as $checklist) {
            $textKeys = collect($checklist->columns_config ?? [])
                ->whereIn('type', ['text', 'date'])
                ->pluck('key')
                ->toArray();

            $checklist->setAttribute('rows_count', $checklist->rows
                ->where('row_type', 'normal')
                ->filter(function ($row) use ($textKeys): bool {
                    foreach ($textKeys as $key) {
                        $value = $row->data[$key] ?? null;
                        if (is_string($value) && trim($value) !== '') {
                            return true;
                        }
                    }

                    return false;
                })
                ->count());

            $checklist->unsetRelation('rows');
        }

        $availableFeatures = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module']);

        return Inertia::render('Checklists/Index', [
            'project' => $project,
            'checklists' => $checklists,
            'availableFeatures' => $availableFeatures,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        $templates = $project->checklists()
            ->get(['id', 'name', 'columns_config']);

        $categories = $project->checklists()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        $features = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module', 'priority']);

        return Inertia::render('Checklists/Create', [
            'project' => $project,
            'templates' => $templates,
            'categories' => $categories,
            'features' => $features,
        ]);
    }

    public function store(StoreChecklistRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $validated['order'] = ($project->checklists()->max('order') ?? -1) + 1;

        $checklist = $project->checklists()->create(collect($validated)->except('feature_ids')->toArray());

        if (! empty($validated['feature_ids'])) {
            $checklist->projectFeatures()->sync($validated['feature_ids']);
        }

        return redirect()->route('checklists.show', [$project, $checklist])
            ->with('success', 'Checklist created successfully.');
    }

    public function show(Project $project, Checklist $checklist): Response
    {
        $this->authorize('view', $project);

        $checklist->load(['note', 'projectFeatures:id,name,module']);

        $checklists = $project->checklists()
            ->with(['sectionHeaders:id,checklist_id,data,order'])
            ->get(['id', 'project_id', 'name', 'columns_config']);

        $testSuites = $project->testSuites()
            ->whereNull('parent_id')
            ->with(['children:id,parent_id,name,order'])
            ->orderBy('order')
            ->get(['id', 'project_id', 'parent_id', 'name', 'order']);

        return Inertia::render('Checklists/Show', [
            'project' => $project,
            'checklist' => $checklist,
            'checklists' => $checklists,
            'testSuites' => $testSuites,
            'rows' => Inertia::defer(fn () => $checklist->rows()->orderBy('order')->get()),
        ]);
    }

    public function edit(Project $project, Checklist $checklist): Response
    {
        $this->authorize('update', $project);

        $checklist->load('projectFeatures:id');

        $features = $project->features()->where('is_active', true)
            ->orderBy('module')->orderBy('name')
            ->get(['id', 'name', 'module', 'priority']);

        return Inertia::render('Checklists/Edit', [
            'project' => $project,
            'checklist' => $checklist,
            'features' => $features,
        ]);
    }

    public function update(UpdateChecklistRequest $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $checklist->update(collect($validated)->except('feature_ids')->toArray());
        $checklist->projectFeatures()->sync($validated['feature_ids'] ?? []);

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

    public function reorder(ReorderChecklistsRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        foreach ($validated['items'] as $item) {
            Checklist::where('id', $item['id'])
                ->where('project_id', $project->id)
                ->update([
                    'order' => $item['order'],
                    'category' => $item['category'] ?? null,
                ]);
        }

        return back()->with('success', 'Checklists reordered successfully.');
    }

    public function updateRows(UpdateChecklistRowsRequest $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        if (isset($validated['columns_config'])) {
            $checklist->update(['columns_config' => $validated['columns_config']]);
        }

        // Load existing rows for change detection (to update updated_at only when data changes)
        $existingRows = $checklist->rows()->get()->keyBy('id');
        $existingIds = [];
        $now = now()->format('Y-m-d H:i:s');

        foreach ($validated['rows'] as $rowData) {
            $updateData = [
                'data' => $rowData['data'],
                'order' => $rowData['order'],
                'row_type' => $rowData['row_type'] ?? 'normal',
                'background_color' => $rowData['background_color'] ?? null,
                'font_color' => $rowData['font_color'] ?? null,
                'font_weight' => $rowData['font_weight'] ?? 'normal',
                'module' => $rowData['module'] ?? null,
            ];

            if (isset($rowData['id'])) {
                $existing = $existingRows->get($rowData['id']);

                // Explicitly set updated_at: bump when changed, preserve when unchanged.
                // Without this, Eloquent Builder auto-sets updated_at on every update() call.
                if ($existing && $this->rowContentChanged($existing, $updateData)) {
                    $updateData['updated_at'] = $now;
                } elseif ($existing) {
                    $updateData['updated_at'] = $existing->updated_at;
                }

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

    public function patchRows(PatchChecklistRowsRequest $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $existingRows = $checklist->rows()
            ->whereIn('id', array_column($validated['rows'], 'id'))
            ->get()
            ->keyBy('id');

        $now = now()->format('Y-m-d H:i:s');

        foreach ($validated['rows'] as $rowData) {
            $existing = $existingRows->get($rowData['id']);

            if (! $existing) {
                continue;
            }

            $updateData = [
                'data' => $rowData['data'],
                'order' => $rowData['order'],
                'row_type' => $rowData['row_type'] ?? 'normal',
                'background_color' => $rowData['background_color'] ?? null,
                'font_color' => $rowData['font_color'] ?? null,
                'font_weight' => $rowData['font_weight'] ?? 'normal',
                'module' => $rowData['module'] ?? null,
            ];

            if ($this->rowContentChanged($existing, $updateData)) {
                $updateData['updated_at'] = $now;
            } else {
                $updateData['updated_at'] = $existing->updated_at;
            }

            $checklist->rows()->where('id', $rowData['id'])->update($updateData);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Rows updated successfully.');
    }

    public function updateNote(StoreChecklistNoteRequest $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $checklist->note()->updateOrCreate(
            ['checklist_id' => $checklist->id],
            ['content' => $validated['content']]
        );

        return back()->with('success', 'Note updated successfully.');
    }

    public function importFromNotes(BulkCreateRowsRequest $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $columns = $checklist->columns_config ?? [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'checkbox'],
        ];

        $columnExists = collect($columns)->contains('key', $validated['column_key']);

        if (! $columnExists) {
            return back()->withErrors(['column_key' => 'Column not found in checklist.']);
        }

        $existingRows = $checklist->rows()->orderBy('order')->get();
        $notesCount = count($validated['notes']);
        $columnKey = $validated['column_key'];

        if (! empty($validated['section_row_id'])) {
            // Insert after the last filled row in the specified section
            $sectionRow = $existingRows->firstWhere('id', $validated['section_row_id']);

            if (! $sectionRow || $sectionRow->row_type !== 'section_header') {
                return back()->withErrors(['section_row_id' => 'Section not found.']);
            }

            $insertAfterOrder = $sectionRow->order;
            foreach ($existingRows as $row) {
                if ($row->order <= $sectionRow->order) {
                    continue;
                }
                if ($row->row_type === 'section_header') {
                    break;
                }
                $cellValue = $row->data[$columnKey] ?? null;
                if ($cellValue !== null && $cellValue !== '') {
                    $insertAfterOrder = $row->order;
                }
            }
        } else {
            // Find the last section header
            $lastSectionOrder = null;
            foreach ($existingRows as $row) {
                if ($row->row_type === 'section_header') {
                    $lastSectionOrder = $row->order;
                }
            }

            if ($lastSectionOrder !== null) {
                // Insert after the last filled row in the last section
                $insertAfterOrder = $lastSectionOrder;
                foreach ($existingRows as $row) {
                    if ($row->order <= $lastSectionOrder) {
                        continue;
                    }
                    $cellValue = $row->data[$columnKey] ?? null;
                    if ($cellValue !== null && $cellValue !== '') {
                        $insertAfterOrder = $row->order;
                    }
                }
            } else {
                // No sections — insert after the last filled row in the whole checklist
                $insertAfterOrder = -1;
                foreach ($existingRows as $row) {
                    $cellValue = $row->data[$columnKey] ?? null;
                    if ($cellValue !== null && $cellValue !== '') {
                        $insertAfterOrder = $row->order;
                    }
                }
            }
        }

        // Shift all rows after the insert position to make room for new notes
        $rowsToShift = $checklist->rows()
            ->where('order', '>', $insertAfterOrder)
            ->orderBy('order', 'desc')
            ->get();

        foreach ($rowsToShift as $row) {
            $row->update(['order' => $row->order + $notesCount]);
        }

        // Insert new notes starting after the insert position
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

    /**
     * Export checklist data to CSV file.
     */
    public function export(Project $project, Checklist $checklist): StreamedResponse
    {
        $this->authorize('view', $project);

        $columns = $checklist->columns_config ?? [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'checkbox'],
        ];

        $query = $checklist->rows()->orderBy('order');

        $ids = request()->query('ids');
        if ($ids) {
            $query->whereIn('id', explode(',', $ids));
        }

        $rows = $query->get();

        $filename = str_replace(' ', '_', $checklist->name).'_'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($columns, $rows) {
            $output = fopen('php://output', 'w');

            // Add BOM for UTF-8 Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write header row with column labels
            $headers = array_map(fn ($col) => $col['label'], $columns);
            fputcsv($output, $headers);

            // Write data rows
            foreach ($rows as $row) {
                $rowData = [];
                foreach ($columns as $col) {
                    $value = $row->data[$col['key']] ?? '';
                    if ($col['type'] === 'checkbox') {
                        $value = $value ? 'Yes' : '';
                    } elseif ($col['type'] === 'select' && $value !== '' && ! empty($col['options'])) {
                        foreach ($col['options'] as $option) {
                            if ($option['value'] === $value) {
                                $value = $option['label'];
                                break;
                            }
                        }
                    }
                    $rowData[] = $value;
                }
                fputcsv($output, $rowData);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import data from CSV file to checklist.
     */
    public function import(Request $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:5120', // 5MB max
        ], [
            'file.required' => 'Please select a file to import.',
            'file.mimes' => 'The file must be a CSV file.',
            'file.max' => 'The file size must not exceed 5MB.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());

        // Remove BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Parse CSV
        $lines = array_filter(explode("\n", $content), fn ($line) => trim($line) !== '');

        if (count($lines) < 1) {
            return back()->withErrors(['file' => 'The CSV file is empty.']);
        }

        // Get checklist columns
        $checklistColumns = $checklist->columns_config ?? [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'checkbox'],
        ];

        // Parse header row
        $headerLine = array_shift($lines);
        // Remove any remaining BOM or invisible characters from header
        $headerLine = preg_replace('/[\x00-\x1F\x7F]/u', '', $headerLine);
        $csvHeaders = str_getcsv($headerLine);
        $csvHeaders = array_map(fn ($h) => trim(preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/u', '', $h)), $csvHeaders);

        // Map CSV headers to checklist columns
        $columnMapping = [];
        $matchedColKeys = [];

        // First try exact matching by label or key
        foreach ($csvHeaders as $csvIndex => $csvHeader) {
            if ($csvHeader === '') {
                continue;
            }

            foreach ($checklistColumns as $col) {
                $label = trim($col['label'] ?? '');
                $key = trim($col['key'] ?? '');

                // Skip if this column was already matched
                if (in_array($key, $matchedColKeys)) {
                    continue;
                }

                if (
                    strcasecmp($label, $csvHeader) === 0 ||
                    strcasecmp($key, $csvHeader) === 0
                ) {
                    $columnMapping[$csvIndex] = $col;
                    $matchedColKeys[] = $key;
                    break;
                }
            }
        }

        // If column count matches, fill in any missing mappings by position
        if (count($csvHeaders) === count($checklistColumns)) {
            foreach ($checklistColumns as $index => $col) {
                if (! isset($columnMapping[$index])) {
                    $columnMapping[$index] = $col;
                }
            }
        }

        // Sort by index to ensure correct order
        ksort($columnMapping);

        if (empty($columnMapping)) {
            $expectedLabels = implode(', ', array_map(fn ($c) => $c['label'], $checklistColumns));

            return back()->withErrors(['file' => "No matching columns found. Expected: {$expectedLabels}"]);
        }

        // Find the last row order
        $maxOrder = $checklist->rows()->max('order') ?? -1;

        // Import data rows
        $importedCount = 0;
        foreach ($lines as $line) {
            // Skip completely empty lines
            if (trim($line) === '') {
                continue;
            }

            // Clean line from invisible characters
            $line = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $line);
            $csvRow = str_getcsv($line);

            // Clean first cell from any remaining BOM
            if (isset($csvRow[0])) {
                $csvRow[0] = preg_replace('/^[\xEF\xBB\xBF]+/u', '', $csvRow[0]);
            }

            // Build row data
            $data = [];
            foreach ($checklistColumns as $col) {
                if ($col['type'] === 'checkbox') {
                    $data[$col['key']] = false;
                } else {
                    $data[$col['key']] = '';
                }
            }

            // Fill in values from CSV
            foreach ($columnMapping as $csvIndex => $col) {
                if (! isset($csvRow[$csvIndex])) {
                    continue;
                }

                $value = $csvRow[$csvIndex];
                // Clean value from BOM and invisible characters
                $value = preg_replace('/^[\xEF\xBB\xBF]+/u', '', $value);
                $value = trim($value);

                if ($col['type'] === 'checkbox') {
                    $value = in_array(strtolower($value), ['yes', 'true', '1', 'x', '+']);
                } elseif ($col['type'] === 'select' && $value !== '' && ! empty($col['options'])) {
                    foreach ($col['options'] as $option) {
                        if (strcasecmp($option['label'], $value) === 0) {
                            $value = $option['value'];
                            break;
                        }
                    }
                }

                $data[$col['key']] = $value;
            }

            // Import row (allow empty columns)
            $maxOrder++;
            $checklist->rows()->create([
                'data' => $data,
                'order' => $maxOrder,
                'row_type' => 'normal',
            ]);
            $importedCount++;
        }

        if ($importedCount === 0) {
            return back()->withErrors(['file' => 'No data rows found in the CSV file.']);
        }

        return redirect()->route('checklists.show', [$project, $checklist])
            ->with('success', $importedCount.' rows imported successfully.');
    }

    /**
     * Copy rows from the request body into the target checklist.
     */
    public function copyRows(CopyRowsRequest $request, Project $project, Checklist $checklist)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $rowCount = count($validated['rows']);

        if (! empty($validated['section_row_id'])) {
            $existingRows = $checklist->rows()->orderBy('order')->get();
            $sectionRow = $existingRows->firstWhere('id', $validated['section_row_id']);

            if (! $sectionRow || $sectionRow->row_type !== 'section_header') {
                return back()->withErrors(['section_row_id' => 'Section not found.']);
            }

            // Find the last filled row in this section (same pattern as importFromNotes)
            $insertAfterOrder = $sectionRow->order;
            foreach ($existingRows as $row) {
                if ($row->order <= $sectionRow->order) {
                    continue;
                }
                if ($row->row_type === 'section_header') {
                    break;
                }
                $hasContent = collect($row->data)->contains(fn ($value) => $value !== null && $value !== '' && $value !== false);
                if ($hasContent) {
                    $insertAfterOrder = $row->order;
                }
            }

            // Shift subsequent rows to make room
            $checklist->rows()
                ->where('order', '>', $insertAfterOrder)
                ->orderBy('order', 'desc')
                ->get()
                ->each(fn ($row) => $row->update(['order' => $row->order + $rowCount]));

            $startOrder = $insertAfterOrder;
        } else {
            $startOrder = $checklist->rows()->max('order') ?? -1;
        }

        // Build column mapping when source has different columns than target
        $columnMap = null;
        $targetColumns = $checklist->columns_config ?? [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'checkbox'],
        ];

        if (! empty($validated['source_columns_config'])) {
            $sourceColumns = $validated['source_columns_config'];
            $columnMap = [];
            $mappedTargetKeys = [];

            // Pass 1: match by key
            foreach ($sourceColumns as $srcCol) {
                foreach ($targetColumns as $tgtCol) {
                    if ($srcCol['key'] === $tgtCol['key'] && ! in_array($tgtCol['key'], $mappedTargetKeys, true)) {
                        $columnMap[$srcCol['key']] = $tgtCol['key'];
                        $mappedTargetKeys[] = $tgtCol['key'];
                        break;
                    }
                }
            }

            // Pass 2: match remaining by label (case-insensitive)
            foreach ($sourceColumns as $srcCol) {
                if (isset($columnMap[$srcCol['key']])) {
                    continue;
                }
                foreach ($targetColumns as $tgtCol) {
                    if (in_array($tgtCol['key'], $mappedTargetKeys, true)) {
                        continue;
                    }
                    if (strcasecmp($srcCol['label'], $tgtCol['label']) === 0) {
                        $columnMap[$srcCol['key']] = $tgtCol['key'];
                        $mappedTargetKeys[] = $tgtCol['key'];
                        break;
                    }
                }
            }
        }

        foreach ($validated['rows'] as $rowData) {
            $startOrder++;
            $data = $rowData['data'];

            if ($columnMap !== null) {
                $mapped = [];
                foreach ($targetColumns as $tgtCol) {
                    $mapped[$tgtCol['key']] = $tgtCol['type'] === 'checkbox' ? false : '';
                }
                foreach ($data as $srcKey => $value) {
                    if (isset($columnMap[$srcKey])) {
                        $mapped[$columnMap[$srcKey]] = $value;
                    }
                }
                $data = $mapped;
            }

            $checklist->rows()->create([
                'data' => $data,
                'order' => $startOrder,
                'row_type' => $rowData['row_type'] ?? 'normal',
                'background_color' => $rowData['background_color'] ?? null,
                'font_color' => $rowData['font_color'] ?? null,
                'font_weight' => $rowData['font_weight'] ?? 'normal',
                'module' => $rowData['module'] ?? null,
            ]);
        }

        return redirect()->route('checklists.show', [$project, $checklist])
            ->with('success', count($validated['rows']).' rows copied successfully.');
    }

    /**
     * Check if a row's content or appearance changed compared to the incoming data.
     *
     * @param  array<string, mixed>  $updateData
     */
    private function rowContentChanged(\App\Models\ChecklistRow $existing, array $updateData): bool
    {
        if (json_encode($existing->data) !== json_encode($updateData['data'])) {
            return true;
        }

        if ($existing->row_type !== $updateData['row_type']) {
            return true;
        }

        if ($existing->background_color !== $updateData['background_color']) {
            return true;
        }

        if ($existing->font_color !== $updateData['font_color']) {
            return true;
        }

        if ($existing->font_weight !== $updateData['font_weight']) {
            return true;
        }

        if (json_encode($existing->module ?? []) !== json_encode($updateData['module'] ?? [])) {
            return true;
        }

        return false;
    }
}
