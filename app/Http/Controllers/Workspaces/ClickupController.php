<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ClickupSettingsRequest;
use App\Http\Requests\Settings\ClickupStatusMappingRequest;
use App\Models\ClickupSetting;
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
    public function show(Request $request): Response
    {
        $workspace = $request->attributes->get('workspace');
        $this->authorize('update', $workspace);

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

    public function update(ClickupSettingsRequest $request, AchievementService $achievements): RedirectResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->authorize('update', $workspace);

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

    public function updateStatusMapping(ClickupStatusMappingRequest $request): RedirectResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->authorize('update', $workspace);

        $settings = ClickupSetting::forWorkspace($workspace);

        $settings->update([
            'status_mapping' => $request->validated('status_mapping'),
        ]);

        return back()->with('success', 'Status mapping saved.');
    }

    public function fetchStatuses(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->authorize('update', $workspace);

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

    public function registerWebhook(Request $request): RedirectResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->authorize('update', $workspace);

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

            // Delete existing webhook if present
            if ($settings->webhook_id) {
                try {
                    $service->deleteWebhook($settings->webhook_id);
                } catch (\Exception) {
                    // Webhook may already be deleted
                }
            }

            $teams = $service->getTeams();
            if (empty($teams)) {
                return back()->with('error', 'No ClickUp teams found.');
            }

            $secret = Str::random(32);

            Log::info('Registering ClickUp webhook', [
                'endpoint' => $endpoint,
                'teamId' => $teams[0]['id'],
                'workspaceId' => $workspace->id,
            ]);

            $result = $service->registerWebhook($teams[0]['id'], $endpoint, $secret);

            $settings->update([
                'webhook_id' => $result['id'],
                'webhook_secret' => $secret,
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
