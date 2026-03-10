<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GrafanaSettingsRequest;
use App\Models\GrafanaSetting;
use Illuminate\Http\RedirectResponse;
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
}
