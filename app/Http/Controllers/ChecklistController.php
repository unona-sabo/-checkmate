<?php

namespace App\Http\Controllers;

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
            ->withCount('rows')
            ->with(['sectionHeaders:id,checklist_id,data,order'])
            ->orderBy('order')
            ->get(['id', 'project_id', 'name', 'columns_config', 'order', 'category', 'created_at', 'updated_at']);

        return Inertia::render('Checklists/Index', [
            'project' => $project,
            'checklists' => $checklists,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        $templates = $project->checklists()
            ->get(['id', 'name', 'columns_config']);

        return Inertia::render('Checklists/Create', [
            'project' => $project,
            'templates' => $templates,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
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

        $validated['order'] = ($project->checklists()->max('order') ?? -1) + 1;

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

    public function reorder(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:checklists,id',
            'items.*.order' => 'required|integer|min:0',
            'items.*.category' => 'nullable|string|max:255',
        ]);

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
            ];

            if (isset($rowData['id'])) {
                $existing = $existingRows->get($rowData['id']);

                // Only bump updated_at when content or appearance actually changed
                if ($existing && $this->rowContentChanged($existing, $updateData)) {
                    $updateData['updated_at'] = $now;
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
            'section_row_id' => 'nullable|integer|exists:checklist_rows,id',
        ]);

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

        $rows = $checklist->rows()->orderBy('order')->get();

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
                    // Convert boolean to string for checkboxes, leave empty if not checked
                    if ($col['type'] === 'checkbox') {
                        $value = $value ? 'Yes' : '';
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
     * Check if a row's content or appearance changed compared to the incoming data.
     *
     * @param  array<string, mixed>  $updateData
     */
    private function rowContentChanged(\App\Models\ChecklistRow $existing, array $updateData): bool
    {
        if ($existing->data != $updateData['data']) {
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

        return false;
    }
}
