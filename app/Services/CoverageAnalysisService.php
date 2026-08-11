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
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function generateTestCases(array $gap): array
    {
        $prompt = $this->buildTestCaseGenerationPrompt($gap);

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
'.json_encode($features, JSON_PRETTY_PRINT).'

EXISTING TEST CASES (what\'s already covered):
'.json_encode($testCases, JSON_PRETTY_PRINT).'

DOCUMENTATION:
'.json_encode($documentation, JSON_PRETTY_PRINT).'

Analyze the test coverage and provide a comprehensive assessment.'.$customInstructionsBlock.'

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
     */
    private function buildTestCaseGenerationPrompt(array $gap): string
    {
        $feature = $gap['feature'] ?? '';
        $description = $gap['description'] ?? '';
        $module = $gap['module'] ?? '';
        $category = $gap['category'] ?? '';
        $priority = $gap['priority'] ?? '';

        return "You are a QA expert. Generate detailed test cases for the following coverage gap.

COVERAGE GAP:
Feature: {$feature}
Description: {$description}
Module: {$module}
Category: {$category}
Priority: {$priority}

Generate 3-7 comprehensive test cases that cover different scenarios (positive, negative, edge cases).

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
            $response = Http::timeout(120)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
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
            Log::error('Gemini API connection error: '.$e->getMessage());
            throw $e;
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
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $jsonContent = $matches[0];
        } elseif (preg_match('/\[.*\]/s', $content, $matches)) {
            $jsonContent = $matches[0];
        } else {
            $jsonContent = $content;
        }

        return json_decode($jsonContent, true) ?? [];
    }
}
