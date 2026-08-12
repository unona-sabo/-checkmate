<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bugreport;
use App\Models\ClickupSetting;
use App\Models\Workspace;
use App\Services\ClickupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClickupWebhookController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $settings = ClickupSetting::forWorkspace($workspace);

        if (! $this->verifySignature($request, $settings->webhook_secret ?? '')) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');

        if ($event === 'taskStatusUpdated') {
            $this->handleStatusUpdate($request, $settings);
        }

        return response()->json(['ok' => true]);
    }

    private function verifySignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Signature');

        if (! $signature || ! $secret) {
            return false;
        }

        $computed = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }

    /**
     * Fetch the task's current status directly from the ClickUp API rather
     * than trusting `history_items` off the webhook payload — ClickUp
     * batches multiple field changes into one webhook call, and the status
     * change isn't guaranteed to be at a fixed index, so reading it out of
     * the diff is unreliable. This mirrors the manual "sync" action, which
     * always resolves correctly because it does the same live lookup.
     */
    private function handleStatusUpdate(Request $request, ClickupSetting $settings): void
    {
        $taskId = $request->input('task_id');

        if (! $taskId) {
            Log::warning('ClickUp webhook: taskStatusUpdated event missing task_id.');

            return;
        }

        $bugreport = Bugreport::where('clickup_task_id', $taskId)->first();

        if (! $bugreport) {
            Log::info("ClickUp webhook: no bugreport is linked to ClickUp task {$taskId}.");

            return;
        }

        if (! $settings->isConfigured()) {
            Log::warning("ClickUp webhook: received status update for task {$taskId} but the integration is not configured.");

            return;
        }

        try {
            $task = ClickupService::fromSettings($settings)->getTask($taskId);
        } catch (\Throwable $e) {
            Log::error("ClickUp webhook: failed to fetch task {$taskId} from the ClickUp API: {$e->getMessage()}");

            return;
        }

        $clickupStatus = $task['status']['status'] ?? '';
        $appStatus = ClickupService::resolveAppStatus($settings->status_mapping ?? [], $clickupStatus);

        if (! $appStatus) {
            Log::info("ClickUp webhook: no status mapping configured for ClickUp status \"{$clickupStatus}\" (task {$taskId}).");

            return;
        }

        if ($appStatus !== $bugreport->status) {
            $bugreport->update(['status' => $appStatus]);
        }
    }
}
