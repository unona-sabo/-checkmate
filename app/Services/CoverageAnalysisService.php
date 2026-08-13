<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoverageAnalysisService
{
    private string $model;

    public function __construct(
        private string $provider,
        private ?string $apiKey = null,
        ?string $model = null,
    ) {
        $this->model = $model ?? match ($this->provider) {
            'gemini' => config('services.gemini.model', 'gemini-flash-latest'),
            'openai' => config('services.openai.model', 'gpt-4o-mini'),
            default => 'claude-sonnet-4-20250514',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $testCases
     * @param  list<array<string, mixed>>  $features
     * @param  list<array<string, mixed>>  $documentation
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function analyzeCoverage(array $testCases, array $features, array $documentation = [], ?string $customInstructions = null): array
    {
        $prompt = $this->buildCoverageAnalysisPrompt($testCases, $features, $documentation, $customInstructions);

        return match ($this->provider) {
            'gemini' => $this->geminiComplete($prompt),
            'openai' => $this->openaiComplete($prompt),
            default => $this->claudeComplete($prompt),
        };
    }

    /**
     * @param  array<string, mixed>  $gap
     * @param  list<array<string, mixed>>  $existingTestCases
     * @param  list<array<string, mixed>>  $documentation
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function generateTestCases(array $gap, array $existingTestCases = [], array $documentation = []): array
    {
        $prompt = $this->buildTestCaseGenerationPrompt($gap, $existingTestCases, $documentation);

        return match ($this->provider) {
            'gemini' => $this->geminiComplete($prompt),
            'openai' => $this->openaiComplete($prompt),
            default => $this->claudeComplete($prompt),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $testCases
     * @param  list<array<string, mixed>>  $features
     * @param  list<array<string, mixed>>  $documentation
     */
    private function buildCoverageAnalysisPrompt(array $testCases, array $features, array $documentation, ?string $customInstructions = null): string
    {
        $customInstructionsBlock = ! empty($customInstructions)
            ? "\n\nADDITIONAL INSTRUCTIONS FROM THE REQUESTER — pay close attention to these:\n{$customInstructions}"
            : '';

        return 'You are a QA expert analyzing test coverage for a software project.

PROJECT FEATURES (what needs to be tested):
'.json_encode($features).'

EXISTING TEST CASES (what\'s already covered):
'.json_encode($testCases).'

DOCUMENTATION:
'.json_encode($documentation).'

Analyze the test coverage and provide a comprehensive assessment.'.$customInstructionsBlock.'

IMPORTANT: Base your analysis strictly on the features, test cases, and documentation listed above — do not invent features, modules, or test cases that are not present in that data. If a section above is empty, say so plainly (e.g. "no test cases exist yet") rather than inventing example content to fill out the response.

IMPORTANT: Return ONLY valid JSON, no additional text before or after.

Return JSON with this exact structure:
{
  "summary": "Brief overview of coverage status (2-3 sentences)",
  "overall_coverage": 75,
  "gaps": [
    {
      "id": "gap_1",
      "feature": "Feature name",
      "description": "What testing is missing",
      "priority": "critical|high|medium|low",
      "category": "functional|ui|api|security|performance",
      "module": "Module name",
      "suggested_test_count": 5,
      "reasoning": "Why this is important"
    }
  ],
  "well_covered": [
    {
      "feature": "Feature name",
      "module": "Module name",
      "test_count": 12,
      "coverage": 95,
      "strength": "What makes this well-covered"
    }
  ],
  "risks": [
    {
      "id": "risk_1",
      "area": "Feature/Module name",
      "level": "critical|high|medium|low",
      "reason": "Why this is risky",
      "impact": "Potential consequences",
      "recommendation": "What to do about it"
    }
  ],
  "recommendations": [
    {
      "priority": 1,
      "action": "Specific recommendation",
      "benefit": "Expected improvement",
      "effort": "low|medium|high"
    }
  ],
  "coverage_by_category": {
    "functional": 80,
    "ui": 65,
    "api": 90,
    "security": 45,
    "performance": 30
  }
}';
    }

    /**
     * @param  array<string, mixed>  $gap
     * @param  list<array<string, mixed>>  $existingTestCases
     * @param  list<array<string, mixed>>  $documentation
     */
    private function buildTestCaseGenerationPrompt(array $gap, array $existingTestCases = [], array $documentation = []): string
    {
        $feature = $gap['feature'] ?? '';
        $description = $gap['description'] ?? '';
        $module = $gap['module'] ?? '';
        $category = $gap['category'] ?? '';
        $priority = $gap['priority'] ?? '';

        $existingTestCasesBlock = $existingTestCases !== []
            ? "\n\nEXISTING TEST CASES ALREADY LINKED TO THIS FEATURE (do not duplicate these scenarios):\n".json_encode($existingTestCases, JSON_PRETTY_PRINT)
            : '';

        $documentationBlock = $documentation !== []
            ? "\n\nPROJECT DOCUMENTATION (use for context on expected behavior):\n".json_encode($documentation, JSON_PRETTY_PRINT)
            : '';

        return "You are a QA expert. Generate detailed test cases for the following coverage gap.

COVERAGE GAP:
Feature: {$feature}
Description: {$description}
Module: {$module}
Category: {$category}
Priority: {$priority}{$existingTestCasesBlock}{$documentationBlock}

Generate 3-7 comprehensive test cases that cover different scenarios (positive, negative, edge cases) not already covered by the existing test cases above.

IMPORTANT: Return ONLY valid JSON, no additional text.

Return JSON array with this structure:
[
  {
    \"title\": \"Test case title\",
    \"preconditions\": \"What must be true before testing\",
    \"test_steps\": [
      \"Step 1: Action to perform\",
      \"Step 2: Next action\",
      \"Step 3: Final action\"
    ],
    \"expected_result\": \"What should happen\",
    \"priority\": \"critical|high|medium|low\",
    \"type\": \"positive|negative|edge_case|boundary\"
  }
]";
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    private function claudeComplete(string $prompt): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Anthropic API key is not configured for this workspace. Go to Workspace Settings → AI Providers to set it up.');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 4096,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';

            return $this->extractJson($content);
        } catch (ConnectionException $e) {
            Log::error('Claude API connection error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    private function geminiComplete(string $prompt): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API key is not configured for this workspace. Go to Workspace Settings → AI Providers to set it up.');
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->timeout(120)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent",
                [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 4096,
                    ],
                ]
            );

            if (! $response->successful()) {
                $error = $response->json('error.message', 'Unknown error');
                throw new \RuntimeException('Gemini API error: '.$error);
            }

            $data = $response->json();
            $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return $this->extractJson($content);
        } catch (ConnectionException $e) {
            Log::error('Gemini API connection error', ['exception' => $e->getMessage()]);
            throw new ConnectionException('Unable to reach the Gemini API. Please try again.', previous: $e);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    private function openaiComplete(string $prompt): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured for this workspace. Go to Workspace Settings → AI Providers to set it up.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.7,
                'max_tokens' => 4096,
            ]);

            if (! $response->successful()) {
                $error = $response->json('error.message', 'Unknown error');
                throw new \RuntimeException('OpenAI API error: '.$error);
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            return $this->extractJson($content);
        } catch (ConnectionException $e) {
            Log::error('OpenAI API connection error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractJson(string $content): array
    {
        $objectPos = strpos($content, '{');
        $arrayPos = strpos($content, '[');

        // Whichever bracket opens first tells us whether the payload is a
        // top-level object or array — checking object before array
        // unconditionally would truncate a JSON array of objects down to
        // just its first element, since `{.*}` greedily matches from the
        // first `{` to the last `}` regardless of the outer `[`/`]`.
        $matches = [];
        if ($arrayPos !== false && ($objectPos === false || $arrayPos < $objectPos)) {
            preg_match('/\[.*\]/s', $content, $matches);
        } else {
            preg_match('/\{.*\}/s', $content, $matches);
        }

        $jsonContent = $matches[0] ?? $content;

        return json_decode($jsonContent, true) ?? [];
    }
}
