<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bugreport\StoreBugreportRequest;
use App\Http\Requests\Bugreport\UpdateBugreportRequest;
use App\Jobs\ExportBugreportToClickUp;
use App\Jobs\SyncBugreportFromClickUp;
use App\Models\Attachment;
use App\Models\Bugreport;
use App\Models\ChecklistRow;
use App\Models\ClickupSetting;
use App\Models\Project;
use App\Models\TestCase;
use App\Services\AttachmentService;
use App\Services\ClickupService;
use App\Services\FeatureLinkingService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BugreportController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachmentService,
        private readonly FeatureLinkingService $featureLinkingService,
    ) {}

    public function index(Project $project): Response
    {
        $this->authorize('view', $project);

        $bugreports = $project->bugreports()
            ->with(['reporter', 'assignee', 'projectFeatures:id,name,module'])
            ->latest()
            ->get();

        return Inertia::render('Bugreports/Index', [
            'project' => $project,
            'bugreports' => $bugreports,
            'availableFeatures' => $project->features()->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'module']),
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

        $testCaseAttachments = [];
        $testCaseId = request()->query('test_case_id');
        if ($testCaseId) {
            $testCase = TestCase::with('attachments')
                ->whereIn('test_suite_id', $project->testSuites()->pluck('id'))
                ->find($testCaseId);
            if ($testCase) {
                $testCaseAttachments = $testCase->attachments;
            }
        }

        return Inertia::render('Bugreports/Create', [
            'project' => $project,
            'users' => $users,
            'features' => $features,
            'testCaseAttachments' => $testCaseAttachments,
        ]);
    }

    public function store(StoreBugreportRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $validated['reported_by'] = auth()->id();

        $checklistFields = ['checklist_id', 'checklist_row_ids', 'checklist_link_column'];
        $bugreport = $project->bugreports()->create(
            collect($validated)->except(['attachments', 'feature_ids', 'test_case_id', ...$checklistFields])->toArray()
        );

        if (! empty($validated['feature_ids'])) {
            $this->featureLinkingService->sync($bugreport, $validated['feature_ids']);
        }

        $this->attachmentService->storeFromRequest($bugreport, $request, 'attachments/bugreports');

        if (! empty($validated['test_case_id'])) {
            $this->copyTestCaseAttachments($project, $bugreport, (int) $validated['test_case_id']);
        }

        $this->linkBugreportToChecklistRow($project, $bugreport, $validated);

        return redirect()->route('bugreports.show', [$project, $bugreport])
            ->with('success', 'Bug report created successfully.');
    }

    public function show(Project $project, Bugreport $bugreport): Response
    {
        $this->authorize('view', $project);
        abort_unless($bugreport->project_id === $project->id, 404);

        $bugreport->load(['reporter', 'assignee', 'attachments']);

        $testSuites = $project->testSuites()
            ->whereNull('parent_id')
            ->with('children:id,parent_id,name')
            ->orderBy('name')
            ->get(['id', 'project_id', 'parent_id', 'name']);

        return Inertia::render('Bugreports/Show', [
            'project' => $project,
            'bugreport' => $bugreport,
            'testSuites' => $testSuites,
        ]);
    }

    public function edit(Project $project, Bugreport $bugreport): Response
    {
        $this->authorize('update', $project);
        abort_unless($bugreport->project_id === $project->id, 404);

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
        abort_unless($bugreport->project_id === $project->id, 404);

        $validated = $request->validated();

        $bugreport->update(collect($validated)->except(['attachments', 'feature_ids'])->toArray());
        $this->featureLinkingService->sync($bugreport, $validated['feature_ids'] ?? []);

        $this->attachmentService->storeFromRequest($bugreport, $request, 'attachments/bugreports');

        return redirect()->route('bugreports.show', [$project, $bugreport])
            ->with('success', 'Bug report updated successfully.');
    }

    public function destroy(Project $project, Bugreport $bugreport)
    {
        $this->authorize('update', $project);
        abort_unless($bugreport->project_id === $project->id, 404);

        $this->attachmentService->deleteAll($bugreport);
        $bugreport->delete();

        return redirect()->route('bugreports.index', $project)
            ->with('success', 'Bug report deleted successfully.');
    }

    public function exportToClickUp(Project $project, Bugreport $bugreport)
    {
        $this->authorize('update', $project);
        abort_unless($bugreport->project_id === $project->id, 404);

        $settings = $project->workspace ? ClickupSetting::forWorkspace($project->workspace) : null;

        if (! $settings?->isConfigured()) {
            return back()->with('error', 'ClickUp integration is not configured. Go to Workspace Settings → ClickUp to set it up.');
        }

        if ($bugreport->clickup_task_id) {
            return back()->with('info', 'This bug report has already been exported to ClickUp.');
        }

        ExportBugreportToClickUp::dispatch($bugreport);

        return back()->with('success', 'Bug report is being exported to ClickUp.');
    }

    public function linkClickUp(Request $request, Project $project, Bugreport $bugreport)
    {
        $this->authorize('update', $project);
        abort_unless($bugreport->project_id === $project->id, 404);

        $validated = $request->validate([
            'clickup_link' => 'required|string|max:500',
        ]);

        $taskId = $this->extractClickupTaskId($validated['clickup_link']);

        if ($taskId === '') {
            return back()->with('error', "Couldn't find a task in that link — paste the task's own link (opened from the task itself, not a list or board), or just its ID.");
        }

        $settings = $project->workspace ? ClickupSetting::forWorkspace($project->workspace) : null;

        if ($settings?->isConfigured()) {
            try {
                ClickupService::fromSettings($settings)->getTask($taskId);
            } catch (RequestException $e) {
                // Only a clear "task not found" should block saving — this
                // usually means a typo in the pasted link/ID. Other failures
                // (e.g. 401 "Team not authorized" when the task belongs to a
                // different ClickUp team than the one this workspace's token
                // is scoped to) don't mean the ID is wrong, so don't block a
                // manual link on them; sync/export will surface the real
                // issue later if it's still a problem.
                if ($e->response->status() === 404) {
                    return back()->with('error', 'Could not find that ClickUp task — check the link and try again.');
                }
            } catch (\Throwable) {
                // Network errors etc. shouldn't block saving either.
            }
        }

        $bugreport->update(['clickup_task_id' => $taskId]);

        return back()->with('success', 'Linked to ClickUp task.');
    }

    public function unlinkClickUp(Project $project, Bugreport $bugreport)
    {
        $this->authorize('update', $project);
        abort_unless($bugreport->project_id === $project->id, 404);

        $bugreport->update(['clickup_task_id' => null]);

        return back()->with('success', 'Unlinked from ClickUp.');
    }

    /**
     * ClickUp task URLs come in several shapes ("/t/{taskId}" permalinks,
     * "/t/{teamId}/{taskId}" in-context links, board/list URLs with a
     * "?task={taskId}" query string, etc.) that all differ in how many
     * navigational segments surround the id. Rather than hardcoding every
     * shape, look for a "?task=" param first, then scan the path segments
     * for one that looks like a task id: team/list/space/folder ids in
     * ClickUp are purely numeric, while task ids (including custom ids)
     * always contain at least one letter — so a numeric-only segment is
     * never the task, and known navigational keywords are skipped too.
     */
    private function extractClickupTaskId(string $link): string
    {
        $link = trim($link);

        if (preg_match('/[?&]task=([a-zA-Z0-9_-]+)/', $link, $matches)) {
            return $matches[1];
        }

        if (! str_contains($link, '://')) {
            return trim($link, "/ \t\n\r\0\x0B");
        }

        $path = (string) parse_url($link, PHP_URL_PATH);
        $segments = array_values(array_filter(
            explode('/', $path),
            fn ($segment) => $segment !== '',
        ));

        $navigationalKeywords = ['t', 'v', 'b', 'l', 'li', 's', 'o', 'f', 'g', 'dc', 'home', 'task'];

        foreach (array_reverse($segments) as $segment) {
            if (in_array($segment, $navigationalKeywords, true)) {
                continue;
            }
            if (preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $segment) && preg_match('/[a-zA-Z]/', $segment)) {
                return $segment;
            }
        }

        return '';
    }

    public function syncFromClickUp(Project $project, Bugreport $bugreport)
    {
        $this->authorize('update', $project);
        abort_unless($bugreport->project_id === $project->id, 404);

        if (! $bugreport->clickup_task_id) {
            return back()->with('error', 'This bug report is not linked to ClickUp.');
        }

        $settings = $project->workspace ? ClickupSetting::forWorkspace($project->workspace) : null;

        if (! $settings?->isConfigured()) {
            return back()->with('error', 'ClickUp integration is not configured.');
        }

        try {
            $service = ClickupService::fromSettings($settings);
            $task = $service->getTask($bugreport->clickup_task_id);

            $clickupStatus = $task['status']['status'] ?? '';
            $appStatus = ClickupService::resolveAppStatus($settings->status_mapping ?? [], $clickupStatus);

            if ($appStatus && $appStatus !== $bugreport->status) {
                $bugreport->update(['status' => $appStatus]);

                return back()->with('success', 'Status synced from ClickUp.');
            }

            return back()->with('info', 'Status is already up to date.');
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                return back()->with('error', 'This bug report\'s ClickUp task no longer exists — it was likely deleted in ClickUp. Unlink it and re-export if you need a new one.');
            }

            return back()->with('error', 'Failed to sync from ClickUp: '.$e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to sync from ClickUp: '.$e->getMessage());
        }
    }

    public function syncAllFromClickUp(Project $project)
    {
        $this->authorize('update', $project);

        $settings = $project->workspace ? ClickupSetting::forWorkspace($project->workspace) : null;

        if (! $settings?->isConfigured()) {
            return back()->with('error', 'ClickUp integration is not configured.');
        }

        $bugreports = $project->bugreports()
            ->whereNotNull('clickup_task_id')
            ->get();

        if ($bugreports->isEmpty()) {
            return back()->with('info', 'No bug reports are linked to ClickUp yet.');
        }

        // Dispatched one job per bug report (queued, processed one at a time)
        // rather than looping synchronously here — syncing dozens of reports
        // inline would risk request timeouts and ClickUp API rate limits.
        foreach ($bugreports as $bugreport) {
            SyncBugreportFromClickUp::dispatch($bugreport);
        }

        return back()->with('success', "Queued {$bugreports->count()} bug report(s) for sync from ClickUp.");
    }

    public function destroyAttachment(Project $project, Bugreport $bugreport, Attachment $attachment)
    {
        $this->authorize('update', $project);
        abort_unless($bugreport->project_id === $project->id, 404);
        abort_unless(
            $attachment->attachable_type === Bugreport::class && $attachment->attachable_id === $bugreport->id,
            404
        );

        $this->attachmentService->deleteOne($attachment);

        return back()->with('success', 'Attachment deleted successfully.');
    }

    /**
     * Copy attachments from a test case to a bugreport.
     */
    private function copyTestCaseAttachments(Project $project, Bugreport $bugreport, int $testCaseId): void
    {
        $testCase = TestCase::with('attachments')
            ->whereIn('test_suite_id', $project->testSuites()->pluck('id'))
            ->find($testCaseId);

        if (! $testCase) {
            return;
        }

        $this->attachmentService->copyTo($bugreport, $testCase->attachments, 'attachments/bugreports');
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
