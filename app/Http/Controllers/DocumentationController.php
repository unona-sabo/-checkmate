<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documentation\ReorderDocumentationsRequest;
use App\Http\Requests\Documentation\StoreDocImageRequest;
use App\Http\Requests\Documentation\StoreDocumentationRequest;
use App\Http\Requests\Documentation\UpdateDocumentationRequest;
use App\Models\Attachment;
use App\Models\Documentation;
use App\Models\Project;
use App\Services\DocumentParserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function create(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        $defaultParentId = $request->integer('parent_id') ?: null;

        $query = $project->documentations()->orderBy('order');

        if ($defaultParentId) {
            $query->where(function ($q) use ($defaultParentId) {
                $q->where('id', $defaultParentId)
                    ->orWhere('parent_id', $defaultParentId);
            });
        }

        $parentOptions = $query->get(['id', 'title', 'parent_id']);

        return Inertia::render('Documentations/Create', [
            'project' => $project,
            'parentOptions' => $parentOptions,
            'defaultParentId' => $defaultParentId,
        ]);
    }

    public function store(StoreDocumentationRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $maxOrder = $project->documentations()
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->max('order') ?? -1;

        $validated['order'] = $maxOrder + 1;

        $documentation = $project->documentations()->create(
            collect($validated)->except('attachments')->toArray()
        );

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/documentations', 'public');
                $documentation->attachments()->create([
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        return redirect()->route('documentations.show', [$project, $documentation])
            ->with('success', 'Documentation created successfully.');
    }

    public function show(Project $project, Documentation $documentation): Response
    {
        $this->authorize('view', $project);

        $documentation->load(['children.children.children', 'attachments']);

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

        $documentation->load('attachments');

        $parentOptions = $project->documentations()
            ->where('id', '!=', $documentation->id)
            ->with('parent')
            ->orderBy('order')
            ->get(['id', 'title', 'parent_id']);

        return Inertia::render('Documentations/Edit', [
            'project' => $project,
            'documentation' => $documentation,
            'parentOptions' => $parentOptions,
        ]);
    }

    public function update(UpdateDocumentationRequest $request, Project $project, Documentation $documentation)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $documentation->update(
            collect($validated)->except('attachments')->toArray()
        );

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/documentations', 'public');
                $documentation->attachments()->create([
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        return redirect()->route('documentations.show', [$project, $documentation])
            ->with('success', 'Documentation updated successfully.');
    }

    public function destroy(Project $project, Documentation $documentation)
    {
        $this->authorize('update', $project);

        foreach ($documentation->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->stored_path);
        }

        $documentation->delete();

        return redirect()->route('documentations.index', $project)
            ->with('success', 'Documentation deleted successfully.');
    }

    public function destroyAttachment(Project $project, Documentation $documentation, Attachment $attachment)
    {
        $this->authorize('update', $project);

        Storage::disk('public')->delete($attachment->stored_path);
        $attachment->delete();

        return back()->with('success', 'Attachment deleted.');
    }

    public function uploadImage(StoreDocImageRequest $request, Project $project, Documentation $documentation)
    {
        $this->authorize('update', $project);

        $request->validated();

        $path = $request->file('image')->store('attachments/documentations/images', 'public');

        $documentation->attachments()->create([
            'original_filename' => $request->file('image')->getClientOriginalName(),
            'stored_path' => $path,
            'mime_type' => $request->file('image')->getMimeType(),
            'size' => $request->file('image')->getSize(),
        ]);

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function uploadNewImage(StoreDocImageRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $request->validated();

        $path = $request->file('image')->store('attachments/documentations/images', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function export(Project $project, Documentation $documentation): StreamedResponse
    {
        $this->authorize('view', $project);

        $documentation->load(['children.children.children', 'attachments']);

        $data = $this->serializeDocumentation($documentation);

        $filename = str($documentation->title)->slug().'_'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function import(Request $request, Project $project, Documentation $documentation): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'file' => 'required|file|max:5120',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $maxOrder = $project->documentations()
            ->where('parent_id', $documentation->id)
            ->max('order') ?? -1;

        // JSON files: import as documentation tree (existing behavior)
        if ($extension === 'json') {
            $content = file_get_contents($file->getRealPath());

            if ($content === false) {
                return back()->withErrors(['file' => 'Unable to read the uploaded file.']);
            }

            $data = json_decode($content, true);

            if (! is_array($data) || ! isset($data['title'])) {
                return back()->withErrors(['file' => 'Invalid documentation JSON format.']);
            }

            $count = $this->importDocumentation($data, $project, $documentation->id, $maxOrder + 1);

            return back()->with('success', "{$count} document(s) imported successfully.");
        }

        // Other formats: parse content and create single documentation page
        try {
            $parser = new DocumentParserService;
            $parsed = $parser->parse($file->getRealPath(), $originalName);

            $project->documentations()->create([
                'title' => $parsed['title'],
                'content' => $parsed['content'],
                'parent_id' => $documentation->id,
                'order' => $maxOrder + 1,
            ]);

            return back()->with('success', "Document \"{$parsed['title']}\" imported successfully.");
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Documentation import failed: '.$e->getMessage());

            return back()->withErrors(['file' => 'Failed to parse the uploaded file.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDocumentation(Documentation $documentation): array
    {
        $data = [
            'title' => $documentation->title,
            'content' => $documentation->content,
            'category' => $documentation->category,
        ];

        if ($documentation->children && $documentation->children->isNotEmpty()) {
            $data['children'] = $documentation->children
                ->sortBy('order')
                ->values()
                ->map(fn (Documentation $child) => $this->serializeDocumentation($child))
                ->toArray();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importDocumentation(array $data, Project $project, int $parentId, int $order): int
    {
        $doc = $project->documentations()->create([
            'title' => $data['title'] ?? 'Untitled',
            'content' => $data['content'] ?? null,
            'category' => $data['category'] ?? null,
            'parent_id' => $parentId,
            'order' => $order,
        ]);

        $count = 1;

        if (! empty($data['children']) && is_array($data['children'])) {
            foreach ($data['children'] as $index => $child) {
                $count += $this->importDocumentation($child, $project, $doc->id, $index);
            }
        }

        return $count;
    }

    public function reorder(ReorderDocumentationsRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        foreach ($validated['items'] as $item) {
            Documentation::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return back()->with('success', 'Order updated successfully.');
    }
}
