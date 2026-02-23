<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AITestGeneratorService
{
    private string $provider;

    private string $model;

    public function __construct(?string $provider = null)
    {
        $this->provider = $provider ?? config('services.ai.default_provider', 'gemini');
        $this->model = match ($this->provider) {
            'gemini' => config('services.gemini.model', 'gemini-2.0-flash'),
            'openai' => config('services.openai.model', 'gpt-4o-mini'),
            default => 'claude-sonnet-4-20250514',
        };
    }

    /**
     * @param  array{count?: int, custom_prompt?: string}  $options
     * @return list<array{title: string, description: string, preconditions: string, steps: string, expected_result: string, priority: string, type: string}>
     *
     * @throws ConnectionException
     */
    public function generateFromText(string $text, array $options = []): array
    {
        $prompt = $this->buildPrompt($text, $options);

        return match ($this->provider) {
            'claude' => $this->claudeGenerate($prompt, $options),
            'openai' => $this->openaiGenerate($prompt, $options),
            default => $this->geminiGenerate([['text' => $prompt]], $options),
        };
    }

    /**
     * @param  array{count?: int, custom_prompt?: string}  $options
     * @return list<array{title: string, description: string, preconditions: string, steps: string, expected_result: string, priority: string, type: string}>
     *
     * @throws ConnectionException
     */
    public function generateFromImage(string $imagePath, array $options = []): array
    {
        $imageData = $this->optimizeImage($imagePath);
        $mimeType = mime_content_type($imagePath) ?: 'image/png';

        $prompt = $this->buildImagePrompt($options);

        return match ($this->provider) {
            'claude' => $this->claudeGenerateWithImage($prompt, $imageData, $mimeType, $options),
            'openai' => $this->openaiGenerateWithImage($prompt, $imageData, $mimeType, $options),
            default => $this->geminiGenerate([
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageData]],
            ], $options),
        };
    }

    /**
     * @param  array{count?: int, custom_prompt?: string}  $options
     * @return list<array{title: string, description: string, preconditions: string, steps: string, expected_result: string, priority: string, type: string}>
     *
     * @throws ConnectionException
     */
    public function generateFromFile(string $filePath, array $options = []): array
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException('Unable to read file: '.$filePath);
        }

        return $this->generateFromText($content, $options);
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @param  array<string, mixed>  $options
     * @return list<array{title: string, description: string, preconditions: string, steps: string, expected_result: string, priority: string, type: string}>
     *
     * @throws ConnectionException
     */
    private function geminiGenerate(array $parts, array $options): array
    {
        $apiKey = config('services.gemini.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('Gemini API key is not configured. Set GEMINI_API_KEY in your .env file.');
        }

        try {
            $response = Http::timeout(120)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$apiKey}",
                [
                    'contents' => [['parts' => $parts]],
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
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return $this->parseTestCases($text);

        } catch (ConnectionException $e) {
            Log::error('Gemini API connection error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array{title: string, description: string, preconditions: string, steps: string, expected_result: string, priority: string, type: string}>
     *
     * @throws ConnectionException
     */
    private function claudeGenerate(string $prompt, array $options): array
    {
        $apiKey = config('services.anthropic.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API key is not configured. Set ANTHROPIC_API_KEY in your .env file.');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 4096,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $data = $response->json();
            $text = $data['content'][0]['text'] ?? '';

            return $this->parseTestCases($text);

        } catch (ConnectionException $e) {
            Log::error('Claude API connection error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array{title: string, description: string, preconditions: string, steps: string, expected_result: string, priority: string, type: string}>
     *
     * @throws ConnectionException
     */
    private function claudeGenerateWithImage(string $prompt, string $imageData, string $mimeType, array $options): array
    {
        $apiKey = config('services.anthropic.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API key is not configured. Set ANTHROPIC_API_KEY in your .env file.');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 4096,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mimeType,
                                'data' => $imageData,
                            ],
                        ],
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
            ]);

            $data = $response->json();
            $text = $data['content'][0]['text'] ?? '';

            return $this->parseTestCases($text);

        } catch (ConnectionException $e) {
            Log::error('Claude API connection error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array{title: string, description: string, preconditions: string, steps: string, expected_result: string, priority: string, type: string}>
     *
     * @throws ConnectionException
     */
    private function openaiGenerate(string $prompt, array $options): array
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured. Set OPENAI_API_KEY in your .env file.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
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
            $text = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseTestCases($text);

        } catch (ConnectionException $e) {
            Log::error('OpenAI API connection error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array{title: string, description: string, preconditions: string, steps: string, expected_result: string, priority: string, type: string}>
     *
     * @throws ConnectionException
     */
    private function openaiGenerateWithImage(string $prompt, string $imageData, string $mimeType, array $options): array
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured. Set OPENAI_API_KEY in your .env file.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$imageData}",
                            ],
                        ],
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
                'temperature' => 0.7,
                'max_tokens' => 4096,
            ]);

            if (! $response->successful()) {
                $error = $response->json('error.message', 'Unknown error');
                throw new \RuntimeException('OpenAI API error: '.$error);
            }

            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseTestCases($text);

        } catch (ConnectionException $e) {
            Log::error('OpenAI API connection error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function buildPrompt(string $document, array $options): string
    {
        $count = $options['count'] ?? null;
        $countInstruction = $count
            ? "Generate exactly {$count} test cases."
            : 'Generate as many test cases as appropriate to thoroughly cover the functionality described.';

        $customPrompt = ! empty($options['custom_prompt'])
            ? "\n\nADDITIONAL REQUIREMENTS:\n{$options['custom_prompt']}"
            : '';

        $languageInstruction = ! empty($options['language'])
            ? "\n\nLANGUAGE: Generate all test case content (titles, descriptions, steps, expected results) in {$options['language']}."
            : '';

        return "You are an expert QA engineer creating comprehensive test cases from documentation.

DOCUMENTATION:
{$document}

Generate test cases that cover the key functionality described. Include positive, negative, and edge case scenarios.{$customPrompt}{$languageInstruction}

IMPORTANT: Return ONLY valid JSON, no additional text before or after.

Return a JSON array with this exact structure:
[
  {
    \"title\": \"Clear, specific test case title\",
    \"description\": \"What this test validates (1-2 sentences)\",
    \"preconditions\": \"Required setup before running test\",
    \"steps\": [
      {\"action\": \"Navigate to login page\", \"expected\": \"Login page is displayed\"},
      {\"action\": \"Enter valid credentials and click Login\", \"expected\": \"User is redirected to dashboard\"}
    ],
    \"expected_result\": \"Overall expected outcome when all steps complete\",
    \"priority\": \"critical|high|medium|low\",
    \"severity\": \"blocker|critical|major|minor|trivial\",
    \"type\": \"functional|integration|regression|security|performance|usability|smoke|exploratory\",
    \"automation_status\": \"not_automated\"
  }
]

FIELD RULES:
- title: Clear, testable statement (max 255 chars)
- steps: Array of objects with \"action\" (required) and \"expected\" (optional per step)
- priority: critical (blocks deployment), high (major releases), medium (standard), low (when time permits)
- severity: blocker (system unusable), critical (core broken), major (workaround exists), minor (low impact), trivial (cosmetic)
- type: Choose based on test purpose
- automation_status: Always \"not_automated\" for AI-generated tests

{$countInstruction}";
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function buildImagePrompt(array $options): string
    {
        $count = $options['count'] ?? null;
        $countInstruction = $count
            ? "Generate exactly {$count} test cases."
            : 'Generate as many test cases as appropriate to thoroughly cover the functionality visible in the image.';

        $customPrompt = ! empty($options['custom_prompt'])
            ? "\n\nADDITIONAL REQUIREMENTS:\n{$options['custom_prompt']}"
            : '';

        $languageInstruction = ! empty($options['language'])
            ? "\n\nLANGUAGE: Generate all test case content (titles, descriptions, steps, expected results) in {$options['language']}."
            : '';

        return "You are an expert QA engineer. Analyze this screenshot/image of a software application and generate detailed test cases based on what you see.

Identify UI elements, workflows, and functionality visible in the image. Generate test cases covering interactions, validations, and edge cases.{$customPrompt}{$languageInstruction}

IMPORTANT: Return ONLY valid JSON, no additional text before or after.

Return a JSON array with this exact structure:
[
  {
    \"title\": \"Test case focusing on specific UI element\",
    \"description\": \"What UI aspect this validates\",
    \"preconditions\": \"Required state (e.g., user logged in, page loaded)\",
    \"steps\": [
      {\"action\": \"Click on element X\", \"expected\": \"Element responds with visual feedback\"}
    ],
    \"expected_result\": \"Expected visual or functional outcome\",
    \"priority\": \"critical|high|medium|low\",
    \"severity\": \"blocker|critical|major|minor|trivial\",
    \"type\": \"functional|integration|regression|security|performance|usability|smoke|exploratory\",
    \"automation_status\": \"not_automated\"
  }
]

FOCUS AREAS FOR UI TESTING:
- Interactive elements: buttons, inputs, links, dropdowns work correctly
- Form validation: required fields, error messages, format validation
- Navigation: menus, breadcrumbs, links navigate correctly
- Responsive behavior: layout adapts to different screen sizes
- Loading states: spinners, disabled states shown appropriately
- Error states: validation messages appear in correct location

{$countInstruction}";
    }

    /**
     * @return list<array{title: string, description: string, preconditions: string, steps: string, expected_result: string, priority: string, severity: string, type: string, automation_status: string}>
     */
    private function parseTestCases(string $aiResponse): array
    {
        $aiResponse = trim($aiResponse);

        // Extract JSON from markdown code blocks if present
        if (preg_match('/```(?:json)?\s*(\[.*?\])\s*```/s', $aiResponse, $matches)) {
            $aiResponse = $matches[1];
        } elseif (preg_match('/\[.*\]/s', $aiResponse, $matches)) {
            $aiResponse = $matches[0];
        }

        $parsed = json_decode($aiResponse, true);

        if (! is_array($parsed)) {
            Log::warning('AI response could not be parsed as JSON', ['response' => Str::limit($aiResponse, 500)]);

            return [];
        }

        return array_values(array_map(function (array $case) {
            // Format steps: handle both structured [{action, expected}] and plain strings
            $steps = $case['steps'] ?? '';
            if (is_array($steps)) {
                $lines = [];
                foreach ($steps as $index => $step) {
                    $num = $index + 1;
                    if (is_array($step)) {
                        $lines[] = "{$num}. {$step['action']}";
                        if (! empty($step['expected'])) {
                            $lines[] = "   Expected: {$step['expected']}";
                        }
                    } else {
                        $line = is_string($step) ? $step : '';
                        $lines[] = preg_match('/^\d+\./', $line) ? $line : "{$num}. {$line}";
                    }
                }
                $steps = implode("\n", $lines);
            }

            return [
                'title' => Str::limit($case['title'] ?? 'Untitled Test Case', 255, ''),
                'description' => $case['description'] ?? '',
                'preconditions' => $case['preconditions'] ?? '',
                'steps' => $steps,
                'expected_result' => $case['expected_result'] ?? '',
                'priority' => $this->validateEnum($case['priority'] ?? null, ['critical', 'high', 'medium', 'low'], 'medium'),
                'severity' => $this->validateEnum($case['severity'] ?? null, ['blocker', 'critical', 'major', 'minor', 'trivial'], 'major'),
                'type' => $this->validateEnum($case['type'] ?? null, ['functional', 'smoke', 'regression', 'integration', 'acceptance', 'performance', 'security', 'usability', 'exploratory', 'other'], 'functional'),
                'automation_status' => $this->validateEnum($case['automation_status'] ?? null, ['not_automated', 'automated', 'in_progress', 'cannot_automate'], 'not_automated'),
            ];
        }, $parsed));
    }

    private function validateEnum(?string $value, array $allowed, string $default): string
    {
        if ($value && in_array(strtolower($value), $allowed)) {
            return strtolower($value);
        }

        return $default;
    }

    /**
     * Resize large images before sending to API.
     */
    private function optimizeImage(string $path): string
    {
        $imageData = file_get_contents($path);

        if ($imageData === false) {
            throw new \RuntimeException('Unable to read image file: '.$path);
        }

        // If image is under 4MB, just base64 encode it
        if (strlen($imageData) <= 4 * 1024 * 1024) {
            return base64_encode($imageData);
        }

        // For larger images, attempt to resize using GD
        if (! extension_loaded('gd')) {
            return base64_encode($imageData);
        }

        $image = @imagecreatefromstring($imageData);
        if ($image === false) {
            return base64_encode($imageData);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxDimension = 2048;

        if ($width > $maxDimension || $height > $maxDimension) {
            $ratio = min($maxDimension / $width, $maxDimension / $height);
            $newWidth = (int) ($width * $ratio);
            $newHeight = (int) ($height * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($resized !== false) {
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                ob_start();
                imagejpeg($resized, null, 85);
                $resizedData = ob_get_clean();
                imagedestroy($resized);
                imagedestroy($image);

                if ($resizedData !== false) {
                    return base64_encode($resizedData);
                }
            }
        }

        imagedestroy($image);

        return base64_encode($imageData);
    }
}
