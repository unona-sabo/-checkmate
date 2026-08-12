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
        $event = $request->input('event', 'unknown');
        Log::info("ClickUp webhook: received \"{$event}\" event for workspace {$workspace->id}.");

        $settings = ClickupSetting::forWorkspace($workspace);

        $failureReason = $this->signatureFailureReason($request, $settings->webhook_secret ?? '');

        if ($failureReason) {
            Log::warning("ClickUp webhook: rejected \"{$event}\" event for workspace {$workspace->id} — {$failureReason}");

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        if ($event === 'taskStatusUpdated') {
            $this->handleStatusUpdate($request, $settings);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Verify the HMAC signature and, when it fails, say exactly why — this
     * is the only way to tell "ClickUp sent no signature header at all"
     * apart from "it sent one, but it doesn't match what we computed"
     * without access to ClickUp's own delivery logs. The computed/received
     * digests are safe to log: an HMAC digest doesn't reveal the secret it
     * was produced with.
     */
    private function signatureFailureReason(Request $request, string $secret): ?string
    {
        if (! $secret) {
            return 'no webhook secret is configured for this workspace.';
        }

        $signature = $request->header('X-Signature');

        if (! $signature) {
            return 'ClickUp sent no X-Signature header.';
        }

        $body = $request->getContent();
        $computed = hash_hmac('sha256', $body, $secret);

        if (! hash_equals($computed, $signature)) {
            // A secret-hash fingerprint (not the secret itself) lets us
            // confirm from the logs alone whether this request was checked
            // against the same secret the last registration stored. We've
            // already confirmed the fingerprint matches on a prior mismatch,
            // which rules out secret storage — logging the full raw body
            // (base64, since it may contain characters JSON-in-a-log-line
            // would mangle) plus Content-Length/Content-Type lets us do a
            // byte-exact diff against what ClickUp says it sent, to catch a
            // proxy/WAF altering the body in transit.
            $secretFingerprint = substr(hash('sha256', $secret), 0, 12);

            Log::warning('ClickUp webhook signature mismatch — full diagnostic body dump', [
                'secretFingerprint' => $secretFingerprint,
                'contentLengthHeader' => $request->header('Content-Length'),
                'actualBodyBytes' => strlen($body),
                'contentType' => $request->header('Content-Type'),
                'transferEncoding' => $request->header('Transfer-Encoding'),
                'bodyBase64' => base64_encode($body),
            ]);

            return "signature mismatch (received {$signature}, computed {$computed}, ".
                "secret fingerprint {$secretFingerprint}, body length ".strlen($body).
                ', body starts with '.json_encode(substr($body, 0, 40)).').';
        }

        return null;
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
