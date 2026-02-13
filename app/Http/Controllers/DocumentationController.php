<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Documentation;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:documentations,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip',
        ]);

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

    public function update(Request $request, Project $project, Documentation $documentation)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:documentations,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip',
        ]);

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

    public function uploadImage(Request $request, Project $project, Documentation $documentation)
    {
        $this->authorize('update', $project);

        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

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

    public function uploadNewImage(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $path = $request->file('image')->store('attachments/documentations/images', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
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
