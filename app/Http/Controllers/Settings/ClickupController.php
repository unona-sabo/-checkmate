<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ClickupSettingsRequest;
use App\Http\Requests\Settings\ClickupStatusMappingRequest;
use App\Models\ClickupSetting;
use App\Services\ClickupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClickupController extends Controller
{
    public function show(): Response
    {
        $settings = ClickupSetting::current();

        return Inertia::render('settings/Clickup', [
            'settings' => [
                'has_token' => ! empty($settings->api_token),
                'list_id' => $settings->list_id,
                'status_mapping' => $settings->status_mapping ?? [],
                'has_webhook' => ! empty($settings->webhook_id),
            ],
            'appStatuses' => ['to_do', 'in_progress', 'in_review', 'needs_changes', 'cancelled', 'done'],
        ]);
    }

    public function update(ClickupSettingsRequest $request): RedirectResponse
    {
        $settings = ClickupSetting::current();

        $settings->update([
            'api_token' => $request->validated('api_token'),
            'list_id' => $request->validated('list_id'),
        ]);

        return back()->with('success', 'ClickUp settings saved.');
    }

    public function updateStatusMapping(ClickupStatusMappingRequest $request): RedirectResponse
    {
        $settings = ClickupSetting::current();

        $settings->update([
            'status_mapping' => $request->validated('status_mapping'),
        ]);

        return back()->with('success', 'Status mapping saved.');
    }

    public function fetchStatuses(): JsonResponse
    {
        $settings = ClickupSetting::current();

        if (! $settings->isConfigured()) {
            return response()->json(['error' => 'ClickUp is not configured. Save your API token and List ID first.'], 422);
        }

        try {
            $service = ClickupService::fromSettings();
            $statuses = $service->getListStatuses($settings->list_id);

            return response()->json(['statuses' => $statuses]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch statuses: '.$e->getMessage()], 422);
        }
    }

    public function registerWebhook(): RedirectResponse
    {
        $settings = ClickupSetting::current();

        if (! $settings->isConfigured()) {
            return back()->with('error', 'Configure your API token and List ID first.');
        }

        try {
            $service = ClickupService::fromSettings();

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
            $endpoint = url('/api/webhooks/clickup');
            $result = $service->registerWebhook($teams[0]['id'], $endpoint, $secret);

            $settings->update([
                'webhook_id' => $result['id'],
                'webhook_secret' => $secret,
            ]);

            return back()->with('success', 'Webhook registered successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to register webhook: '.$e->getMessage());
        }
    }
}
