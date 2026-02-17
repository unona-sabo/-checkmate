<?php

namespace App\Services;

use App\Models\AutomationTestResult;
use App\Models\Project;
use App\Models\TestEnvironment;
use App\Models\TestRunTemplate;
use Symfony\Component\Process\Process;

class PlaywrightRunner
{
    /**
     * Run Playwright tests for a project.
     *
     * @param  array{file?: string, grep?: string, environment_id?: int, template_id?: int, tags?: list<string>, tag_mode?: string}  $options
     * @return array<string, mixed>
     */
    public function runTests(Project $project, array $options = []): array
    {
        $testsPath = $project->automation_tests_path;

        if (! $testsPath || ! is_dir($testsPath)) {
            throw new \RuntimeException('Automation tests path not configured or does not exist');
        }

        $environment = null;
        if (! empty($options['environment_id'])) {
            $environment = TestEnvironment::find($options['environment_id']);
        }

        $template = null;
        if (! empty($options['template_id'])) {
            $template = TestRunTemplate::find($options['template_id']);
            if ($template && ! $environment && $template->environment_id) {
                $environment = $template->environment;
            }
        }

        $command = ['npx', 'playwright', 'test'];

        // File pattern from options or template
        $file = $options['file'] ?? $template?->file_pattern ?? null;
        if (! empty($file)) {
            $command[] = $file;
        }

        // Build grep from tags or direct grep
        $grep = $this->buildGrepPattern($options, $template);
        if ($grep) {
            $command[] = '--grep';
            $command[] = $grep;
        }

        // Environment-specific options
        if ($environment) {
            if ($environment->workers > 1) {
                $command[] = '--workers';
                $command[] = (string) $environment->workers;
            }
            if ($environment->retries > 0) {
                $command[] = '--retries';
                $command[] = (string) $environment->retries;
            }
            if ($environment->browser !== 'chromium') {
                $command[] = '--project';
                $command[] = $environment->browser;
            }
            if ($environment->headed) {
                $command[] = '--headed';
            }
            if ($environment->timeout !== 30000) {
                $command[] = '--timeout';
                $command[] = (string) $environment->timeout;
            }
        }

        $command[] = '--reporter=json';

        // Build environment variables
        $envVars = $this->buildEnvVars($environment);

        $process = new Process($command, $testsPath, $envVars);
        $process->setTimeout(600);

        try {
            $process->run();
            $output = $process->getOutput();

            $results = json_decode($output, true);

            if (! $results) {
                throw new \RuntimeException('Failed to parse Playwright output: '.$process->getErrorOutput());
            }

            return $results;
        } catch (\RuntimeException $e) {
            $output = $process->getOutput();

            if ($output) {
                $results = json_decode($output, true);
                if ($results) {
                    return $results;
                }
            }

            throw $e;
        }
    }

    /**
     * Import Playwright JSON results into the database.
     *
     * @param  array{environment_id?: int, template_id?: int, tags?: list<string>}  $meta
     */
    public function importResults(Project $project, array $results, array $meta = []): int
    {
        $executedAt = now();
        $imported = 0;

        foreach ($results['suites'] ?? [] as $suite) {
            $imported += $this->processSuite($project, $suite, $executedAt, $meta);
        }

        return $imported;
    }

    /**
     * Build grep pattern from tags.
     *
     * @param  array{grep?: string, tags?: list<string>, tag_mode?: string}  $options
     */
    private function buildGrepPattern(array $options, ?TestRunTemplate $template): ?string
    {
        if (! empty($options['grep'])) {
            return $options['grep'];
        }

        $tags = $options['tags'] ?? $template?->tags ?? null;
        $tagMode = $options['tag_mode'] ?? $template?->tag_mode ?? 'or';

        if (empty($tags)) {
            return null;
        }

        if ($tagMode === 'and') {
            // AND: (?=.*@smoke)(?=.*@critical)
            return implode('', array_map(fn (string $tag) => '(?=.*'.preg_quote($tag, '/').')', $tags));
        }

        // OR: @smoke|@critical
        return implode('|', array_map(fn (string $tag) => preg_quote($tag, '/'), $tags));
    }

    /**
     * Build environment variables for the process.
     *
     * @return array<string, string>|null
     */
    private function buildEnvVars(?TestEnvironment $environment): ?array
    {
        if (! $environment) {
            return null;
        }

        $envVars = [];

        if ($environment->base_url) {
            $envVars['BASE_URL'] = $environment->base_url;
            $envVars['PLAYWRIGHT_BASE_URL'] = $environment->base_url;
        }

        foreach ($environment->variables ?? [] as $key => $value) {
            $envVars[$key] = (string) $value;
        }

        if (empty($envVars)) {
            return null;
        }

        return $envVars;
    }

    /**
     * @param  array{environment_id?: int, template_id?: int, tags?: list<string>}  $meta
     */
    private function processSuite(Project $project, array $suite, $executedAt, array $meta = []): int
    {
        $imported = 0;

        foreach ($suite['suites'] ?? [] as $nestedSuite) {
            $imported += $this->processSuite($project, $nestedSuite, $executedAt, $meta);
        }

        foreach ($suite['specs'] ?? [] as $spec) {
            $file = $spec['file'] ?? $suite['file'] ?? '';

            if ($project->automation_tests_path) {
                $file = str_replace($project->automation_tests_path.DIRECTORY_SEPARATOR, '', $file);
                $file = str_replace('\\', '/', $file);
            }

            foreach ($spec['tests'] ?? [] as $test) {
                $testName = $spec['title'] ?? $test['title'] ?? '';
                $results = $test['results'] ?? [];
                $lastResult = end($results) ?: [];

                $status = match ($test['status'] ?? $lastResult['status'] ?? 'unknown') {
                    'expected' => 'passed',
                    'unexpected' => 'failed',
                    'flaky' => 'passed',
                    'skipped' => 'skipped',
                    'timedOut' => 'timedout',
                    default => $test['status'] ?? 'skipped',
                };

                $duration = $lastResult['duration'] ?? 0;
                $error = $lastResult['error'] ?? null;

                // Extract tags from test name
                preg_match_all('/@(\w+)/', $testName, $tagMatches);
                $testTags = array_map(fn (string $t) => '@'.$t, $tagMatches[1] ?? []);

                $testCase = $project->testSuites()
                    ->join('test_cases', 'test_suites.id', '=', 'test_cases.test_suite_id')
                    ->where('test_cases.playwright_file', $file)
                    ->where('test_cases.playwright_test_name', $testName)
                    ->select('test_cases.*')
                    ->first();

                AutomationTestResult::create([
                    'project_id' => $project->id,
                    'test_case_id' => $testCase?->id,
                    'environment_id' => $meta['environment_id'] ?? null,
                    'template_id' => $meta['template_id'] ?? null,
                    'test_file' => $file,
                    'test_name' => $testName,
                    'status' => $status,
                    'duration_ms' => $duration,
                    'error_message' => is_array($error) ? ($error['message'] ?? null) : $error,
                    'error_stack' => is_array($error) ? ($error['stack'] ?? null) : null,
                    'tags' => ! empty($testTags) ? $testTags : ($meta['tags'] ?? null),
                    'executed_at' => $executedAt,
                ]);

                if ($testCase) {
                    $testCase->update(['last_automated_run' => $executedAt]);
                }

                $imported++;
            }
        }

        return $imported;
    }
}
