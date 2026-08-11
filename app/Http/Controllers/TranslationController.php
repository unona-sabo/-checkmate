<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\Project;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslationController extends Controller
{
    private string $provider;

    private string $model;

    private ?string $apiKey = null;

    public function translate(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'text' => 'required|string|max:10000',
            'target_language' => 'required|in:en,uk',
            'provider' => 'sometimes|in:gemini,claude,openai',
        ]);

        $settings = $project->workspace ? AiSetting::forWorkspace($project->workspace) : null;

        $this->provider = $validated['provider'] ?? $settings?->default_provider ?? config('services.ai.default_provider', 'gemini');
        $this->apiKey = $settings?->apiKeyFor($this->provider);
        $this->model = $settings?->modelFor($this->provider) ?? match ($this->provider) {
            'gemini' => config('services.gemini.model', 'gemini-2.0-flash'),
            'openai' => config('services.openai.model', 'gpt-4o-mini'),
            default => 'claude-sonnet-4-20250514',
        };

        $languageName = $validated['target_language'] === 'en' ? 'English' : 'Ukrainian';

        $prompt = "Translate the following text to {$languageName}. Return ONLY the translated text, nothing else. Do not add any explanations, notes, or formatting. Preserve the original formatting (line breaks, bullet points, numbering, etc.).\n\nText to translate:\n{$validated['text']}";

        try {
            $translatedText = match ($this->provider) {
                'claude' => $this->claudeTranslate($prompt),
                'openai' => $this->openaiTranslate($prompt),
                default => $this->geminiTranslate($prompt),
            };

            return response()->json(['translated_text' => $translatedText]);
        } catch (\Exception $e) {
            Log::error('Translation error: '.$e->getMessage());

            return response()->json(['error' => 'Translation failed. Please try again.'], 500);
        }
    }

    /**
     * @throws ConnectionException
     */
    private function geminiTranslate(string $prompt): string
    {
        $apiKey = $this->apiKey;

        if (empty($apiKey)) {
            throw new \RuntimeException('Gemini API key is not configured for this workspace. Go to Workspace Settings → AI Providers to set it up.');
        }

        $response = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$apiKey}",
            [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 4096,
                ],
            ]
        );

        if (! $response->successful()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \RuntimeException('Gemini API error: '.$error);
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    /**
     * @throws ConnectionException
     */
    private function claudeTranslate(string $prompt): string
    {
        $apiKey = $this->apiKey;

        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API key is not configured for this workspace. Go to Workspace Settings → AI Providers to set it up.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return $response->json('content.0.text', '');
    }

    /**
     * @throws ConnectionException
     */
    private function openaiTranslate(string $prompt): string
    {
        $apiKey = $this->apiKey;

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured for this workspace. Go to Workspace Settings → AI Providers to set it up.');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.3,
        ]);

        return $response->json('choices.0.message.content', '');
    }
}
