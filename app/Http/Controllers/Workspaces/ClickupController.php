<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Concerns\RequiresWorkspaceManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ClickupSettingsRequest;
use App\Http\Requests\Settings\ClickupStatusMappingRequest;
use App\Models\ClickupSetting;
use App\Models\Workspace;
use App\Services\AchievementService;
use App\Services\ClickupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClickupController extends Controller
{
    use RequiresWorkspaceManager;

    public function show(Request $request, Workspace $workspace): Response|RedirectResponse
    {
        if ($redirect = $this->ensureCanManageWorkspace($request, $workspace)) {
            return $redirect;
        }

        $settings = ClickupSetting::forWorkspace($workspace);

        return Inertia::render('Workspaces/Clickup', [
            'workspace' => $workspace,
            'settings' => [
                'has_token' => ! empty($settings->api_token),
                'list_id' => $settings->list_id,
                'status_mapping' => $settings->status_mapping ?? [],
                'has_webhook' => ! empty($settings->webhook_id),
            ],
            'appStatuses' => ['triage', 'to_do', 'in_progress', 'blocked', 'in_review', 'needs_changes', 'cancelled', 'done'],
            'queueDiagnostics' => $this->queueDiagnostics(),
        ]);
    }

    /**
     * Surface the export job queue's health directly in the browser, since
     * this environment has no SSH/log access to inspect the jobs tables.
     *
     * @return array{pending: int, recentFailures: array<int, array{failed_at: string, message: string}>}
     */
    private function queueDiagnostics(): array
    {
        $pending = DB::table('jobs')
            ->where('payload', 'like', '%ExportBugreportToClickUp%')
            ->count();

        $recentFailures = DB::table('failed_jobs')
            ->where('payload', 'like', '%ExportBugreportToClickUp%')
            ->orderByDesc('failed_at')
            ->limit(3)
            ->get(['exception', 'failed_at'])
            ->map(fn ($row) => [
                'failed_at' => $row->failed_at,
                'message' => Str::limit(strtok($row->exception, "\n") ?: $row->exception, 300),
            ])
            ->values()
            ->all();

        return [
            'pending' => $pending,
            'recentFailures' => $recentFailures,
        ];
    }

    public function update(ClickupSettingsRequest $request, Workspace $workspace, AchievementService $achievements): RedirectResponse
    {
        if ($redirect = $this->ensureCanManageWorkspace($request, $workspace)) {
            return $redirect;
        }

        $settings = ClickupSetting::forWorkspace($workspace);

        $settings->update([
            'api_token' => $request->validated('api_token'),
            'list_id' => $request->validated('list_id'),
        ]);

        if ($settings->isConfigured()) {
            $achievements->checkClickupConnector($request->user());
        }

        return back()->with('success', 'ClickUp settings saved.');
    }

    public function updateStatusMapping(ClickupStatusMappingRequest $request, Workspace $workspace): RedirectResponse
    {
        if ($redirect = $this->ensureCanManageWorkspace($request, $workspace)) {
            return $redirect;
        }

        $settings = ClickupSetting::forWorkspace($workspace);

        $settings->update([
            'status_mapping' => $request->validated('status_mapping'),
        ]);

        return back()->with('success', 'Status mapping saved.');
    }

    public function fetchStatuses(Request $request, Workspace $workspace): JsonResponse
    {
        if ($response = $this->ensureCanManageWorkspaceJson($request, $workspace)) {
            return $response;
        }

        $settings = ClickupSetting::forWorkspace($workspace);

        if (! $settings->isConfigured()) {
            return response()->json(['error' => 'ClickUp is not configured. Save your API token and List ID first.'], 422);
        }

        try {
            $service = ClickupService::fromSettings($settings);
            $statuses = $service->getListStatuses($settings->list_id);

            return response()->json(['statuses' => $statuses]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch statuses: '.$e->getMessage()], 422);
        }
    }

    public function registerWebhook(Request $request, Workspace $workspace): RedirectResponse
    {
        if ($redirect = $this->ensureCanManageWorkspace($request, $workspace)) {
            return $redirect;
        }

        $settings = ClickupSetting::forWorkspace($workspace);

        if (! $settings->isConfigured()) {
            return back()->with('error', 'Configure your API token and List ID first.');
        }

        $endpoint = preg_replace('#^http://#', 'https://', url("/api/webhooks/clickup/{$workspace->id}"));

        if (! $this->isPubliclyReachableHost($endpoint)) {
            return back()->with('error', "ClickUp can't call back a local development URL ({$endpoint}). Webhooks need a real, publicly reachable domain — use a tunnel (e.g. ngrok) while developing locally, or register the webhook once this app is deployed.");
        }

        try {
            $service = ClickupService::fromSettings($settings);

            $teams = $service->getTeams();
            if (empty($teams)) {
                return back()->with('error', 'No ClickUp teams found.');
            }
            // Picking teams[0] would silently register the webhook against
            // the wrong team for a token with access to more than one —
            // fail loudly instead rather than guessing.
            if (count($teams) > 1) {
                return back()->with('error', 'This API token has access to more than one ClickUp team, which this integration doesn\'t support yet — use a token scoped to a single team.');
            }
            $teamId = $teams[0]['id'];

            // Delete every webhook already pointing at our endpoint for this
            // team — not just the one we happen to have tracked in
            // $settings->webhook_id. A registration that never made it to
            // the settings update (or two people re-registering at once)
            // can leave an orphaned duplicate that ClickUp keeps delivering
            // to, signed with a secret we no longer know — which looks like
            // a permanent, unexplainable signature mismatch on our end.
            $existingWebhooks = $service->listWebhooks($teamId);
            Log::info('ClickUp webhook cleanup: found existing webhooks for team', [
                'teamId' => $teamId,
                'ourEndpoint' => $endpoint,
                'existing' => array_map(fn ($w) => ['id' => $w['id'] ?? null, 'endpoint' => $w['endpoint'] ?? null], $existingWebhooks),
            ]);
            foreach ($existingWebhooks as $webhook) {
                if (rtrim((string) ($webhook['endpoint'] ?? ''), '/') === rtrim($endpoint, '/')) {
                    try {
                        $service->deleteWebhook($webhook['id']);
                        Log::info("ClickUp webhook cleanup: deleted matching webhook {$webhook['id']}.");
                    } catch (\Exception $e) {
                        Log::warning("ClickUp webhook cleanup: failed to delete webhook {$webhook['id']}: {$e->getMessage()}");
                    }
                }
            }

            Log::info('Registering ClickUp webhook', [
                'endpoint' => $endpoint,
                'teamId' => $teamId,
                'workspaceId' => $workspace->id,
            ]);

            // ClickUp's create-webhook endpoint does not accept a
            // client-supplied secret — it always generates its own and
            // returns it nested under `webhook.secret` in the response
            // (the top-level `id` is a duplicate of `webhook.id`). Storing
            // anything other than that value means every signature we
            // compute is checked against a secret ClickUp never actually
            // signed with, so every delivery looks like a permanent,
            // unexplainable signature mismatch.
            $result = $service->registerWebhook($teamId, $endpoint);
            $webhookId = $result['webhook']['id'] ?? $result['id'];
            $webhookSecret = $result['webhook']['secret'];

            $settings->update([
                'webhook_id' => $webhookId,
                'webhook_secret' => $webhookSecret,
            ]);

            Log::info('ClickUp webhook registered', [
                'webhookId' => $webhookId,
                'secretFingerprint' => substr(hash('sha256', $webhookSecret), 0, 12),
            ]);

            return back()->with('success', 'Webhook registered successfully.');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'OAUTH_194') || str_contains($e->getMessage(), 'Specified URL not allowed')) {
                return back()->with('error', "ClickUp rejected the webhook URL ({$endpoint}) as not publicly reachable. Use a tunnel (e.g. ngrok) while developing locally, or register the webhook once this app is deployed to a real domain.");
            }

            return back()->with('error', "Failed to register webhook (endpoint: {$endpoint}): ".$e->getMessage());
        }
    }

    /**
     * Ask ClickUp directly whether the registered webhook is healthy — this
     * environment has no way to inspect ClickUp's own delivery logs
     * otherwise, and "the webhook object still exists" isn't the same as
     * "ClickUp is successfully delivering to it."
     */
    public function webhookHealth(Request $request, Workspace $workspace): JsonResponse
    {
        if ($response = $this->ensureCanManageWorkspaceJson($request, $workspace)) {
            return $response;
        }

        $settings = ClickupSetting::forWorkspace($workspace);

        if (! $settings->webhook_id) {
            return response()->json(['error' => 'No webhook has been registered for this workspace yet.'], 422);
        }

        try {
            $service = ClickupService::fromSettings($settings);
            $teams = $service->getTeams();

            if (empty($teams)) {
                return response()->json(['error' => 'No ClickUp teams found for this API token.'], 422);
            }

            // Don't assume teams[0] is the team the webhook was registered
            // under — a token with access to multiple teams could have the
            // matching webhook on any of them. Search all of them.
            $webhook = null;
            foreach ($teams as $team) {
                $webhooks = $service->listWebhooks($team['id']);
                $webhook = collect($webhooks)->firstWhere('id', $settings->webhook_id);
                if ($webhook) {
                    break;
                }
            }

            if (! $webhook) {
                return response()->json(['error' => "ClickUp no longer has a webhook with ID {$settings->webhook_id} — it may have been deleted or disabled on ClickUp's side. Try registering it again."], 422);
            }

            return response()->json([
                'endpoint' => $webhook['endpoint'] ?? null,
                'events' => $webhook['events'] ?? [],
                'health' => $webhook['health'] ?? null,
                'team_id' => $teams[0]['id'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to check webhook health: '.$e->getMessage()], 422);
        }
    }

    /**
     * ClickUp needs to call this URL back over the public internet — reject
     * obviously local/dev hosts up front instead of round-tripping to
     * ClickUp's API just to get back a cryptic "OAUTH_194" error.
     */
    private function isPubliclyReachableHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        $localTlds = ['.test', '.local', '.localhost', '.internal', '.invalid', '.example'];

        foreach ($localTlds as $tld) {
            if (str_ends_with($host, $tld)) {
                return false;
            }
        }

        return true;
    }
}
