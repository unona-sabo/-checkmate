<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GrafanaSettingsRequest;
use App\Models\GrafanaSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class GrafanaController extends Controller
{
    public function show(): Response
    {
        $settings = GrafanaSetting::current();

        return Inertia::render('settings/Grafana', [
            'settings' => [
                'has_token' => ! empty($settings->api_token),
                'base_url' => $settings->base_url,
                'datasource_id' => $settings->datasource_id,
                'log_path' => $settings->log_path,
            ],
        ]);
    }

    public function update(GrafanaSettingsRequest $request): RedirectResponse
    {
        $settings = GrafanaSetting::current();

        $data = collect($request->validated())
            ->reject(fn ($value, $key) => $key === 'api_token' && empty($value))
            ->toArray();

        $settings->update($data);

        return back()->with('success', 'Grafana settings saved.');
    }

    /**
     * Diagnose connectivity from this server to the configured Grafana
     * instance — DNS resolution and a raw network round trip — so network
     * problems (e.g. the server not being on the same VPN/private network
     * as Grafana) are visible in the browser without needing server access.
     */
    public function testConnection(): JsonResponse
    {
        $settings = GrafanaSetting::current();

        if (empty($settings->base_url)) {
            return response()->json(['error' => 'Grafana Base URL is not configured.'], 422);
        }

        $baseUrl = rtrim($settings->base_url, '/');
        $host = parse_url($baseUrl, PHP_URL_HOST);

        $dns = [
            'host' => $host,
            'resolved' => false,
            'ip' => null,
        ];

        if ($host) {
            $ip = gethostbyname($host);
            // gethostbyname() returns the input unchanged if resolution fails.
            if ($ip !== $host) {
                $dns['resolved'] = true;
                $dns['ip'] = $ip;
            }
        }

        $connection = [
            'reachable' => false,
            'status' => null,
            'message' => null,
        ];

        try {
            $response = Http::timeout(10)->connectTimeout(5)->get("{$baseUrl}/api/health");
            $connection['reachable'] = true;
            $connection['status'] = $response->status();
            $connection['message'] = 'Server responded (HTTP '.$response->status().'). Network connectivity is fine — check the API token/datasource ID next.';
        } catch (ConnectionException $e) {
            $connection['message'] = $e->getMessage();
        }

        return response()->json([
            'base_url' => $baseUrl,
            'dns' => $dns,
            'connection' => $connection,
        ]);
    }
}
