<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\AiSettingsRequest;
use App\Models\AiSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiController extends Controller
{
    public function show(Request $request): Response
    {
        $workspace = $request->attributes->get('workspace');
        $this->authorize('update', $workspace);

        $settings = AiSetting::forWorkspace($workspace);

        return Inertia::render('Workspaces/Ai', [
            'workspace' => $workspace,
            'settings' => [
                'has_gemini_key' => $settings->apiKeyFor('gemini') !== null,
                'gemini_model' => $settings->gemini_model,
                'has_claude_key' => $settings->apiKeyFor('claude') !== null,
                'has_openai_key' => $settings->apiKeyFor('openai') !== null,
                'openai_model' => $settings->openai_model,
                'default_provider' => $settings->default_provider ?? config('services.ai.default_provider', 'gemini'),
            ],
        ]);
    }

    public function update(AiSettingsRequest $request): RedirectResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->authorize('update', $workspace);

        $settings = AiSetting::forWorkspace($workspace);

        // A blank key input means "keep the existing one" — a masked
        // password field can't round-trip the real secret back to us.
        $data = collect($request->validated())
            ->reject(fn ($value, $key) => str_ends_with($key, '_api_key') && empty($value))
            ->toArray();

        $settings->update($data);

        return back()->with('success', 'AI provider settings saved.');
    }
}
