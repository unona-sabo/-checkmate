<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAIService
{
    /**
     * @param  list<array<string, mixed>>  $testCases
     * @param  list<array<string, mixed>>  $features
     * @param  list<array<string, mixed>>  $documentation
     * @return array<string, mixed>
     */
    public function analyzeCoverage(array $testCases, array $features, array $documentation = []): array
    {
        $prompt = $this->buildCoverageAnalysisPrompt($testCases, $features, $documentation);

        return $this->callClaude($prompt);
    }

    /**
     * @param  array<string, mixed>  $gap
     * @return list<array<string, mixed>>
     */
    public function generateTestCases(array $gap): array
    {
        $prompt = $this->buildTestCaseGenerationPrompt($gap);

        return $this->callClaude($prompt);
    }

    /**
     * @param  list<array<string, mixed>>  $testCases
     * @param  list<array<string, mixed>>  $features
     * @param  list<array<string, mixed>>  $documentation
     */
    private function buildCoverageAnalysisPrompt(array $testCases, array $features, array $documentation): string
    {
        return 'You are a QA expert analyzing test coverage for a software project.

PROJECT FEATURES (what needs to be tested):
'.json_encode($features, JSON_PRETTY_PRINT).'

EXISTING TEST CASES (what\'s already covered):
'.json_encode($testCases, JSON_PRETTY_PRINT).'

DOCUMENTATION:
'.json_encode($documentation, JSON_PRETTY_PRINT).'

Analyze the test coverage and provide a comprehensive assessment.

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
    private function callClaude(string $prompt): array
    {
        $apiKey = config('services.anthropic.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API key is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 4096,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';

            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $jsonContent = $matches[0];
            } elseif (preg_match('/\[.*\]/s', $content, $matches)) {
                $jsonContent = $matches[0];
            } else {
                $jsonContent = $content;
            }

            return json_decode($jsonContent, true) ?? [];

        } catch (\Exception $e) {
            Log::error('Claude API error: '.$e->getMessage());

            throw $e;
        }
    }
}
