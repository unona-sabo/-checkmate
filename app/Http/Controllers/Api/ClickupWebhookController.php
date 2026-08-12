<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncBugreportFromClickUp;
use App\Models\Bugreport;
use App\Models\ClickupSetting;
use App\Models\Workspace;
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

        $incomingWebhookId = $request->input('webhook_id');
        if ($incomingWebhookId && $incomingWebhookId !== $settings->webhook_id) {
            Log::warning("ClickUp webhook: event delivered by webhook {$incomingWebhookId}, but this workspace has {$settings->webhook_id} on file — this delivery is from a stale/orphaned webhook subscription that was never actually removed on ClickUp's side.");
        }

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
     * Dispatch the status sync to the queue rather than calling ClickUp's
     * API inline — the webhook response shouldn't wait on an external HTTP
     * round trip to ClickUp, especially since ClickUp itself will count a
     * slow response toward the delivery's health. Reuses the same job the
     * manual "Sync from ClickUp" action already dispatches, which fetches
     * the task's current status directly from the API rather than trusting
     * `history_items` off the webhook payload (ClickUp batches multiple
     * field changes into one call, so the status change isn't guaranteed
     * to be at a fixed index in that array).
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

        SyncBugreportFromClickUp::dispatch($bugreport);
    }
}
