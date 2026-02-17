<?php

namespace App\Services;

use Symfony\Component\Finder\Finder;

class PlaywrightScanner
{
    /**
     * Scan a directory for Playwright test files and parse their contents.
     *
     * @return array{tests_path: string, total_files: int, total_tests: int, all_tags: list<string>, files: array<int, array{file: string, full_path: string, suite: string, tests: list<array{name: string, full_name: string, tags: list<string>}>, skipped_tests: list<string>}>}
     */
    public function scanDirectory(string $testsPath): array
    {
        if (! is_dir($testsPath)) {
            throw new \RuntimeException("Directory does not exist: {$testsPath}");
        }

        $results = [
            'tests_path' => $testsPath,
            'total_files' => 0,
            'total_tests' => 0,
            'all_tags' => [],
            'files' => [],
        ];

        $searchDirs = [];
        if (is_dir($testsPath.'/tests')) {
            $searchDirs[] = $testsPath.'/tests';
        }
        if (is_dir($testsPath.'/e2e')) {
            $searchDirs[] = $testsPath.'/e2e';
        }

        if (empty($searchDirs)) {
            $searchDirs[] = $testsPath;
        }

        $finder = new Finder;
        $finder->files()
            ->in($searchDirs)
            ->name(['*.spec.js', '*.spec.ts', '*.test.js', '*.test.ts']);

        $allTags = [];

        foreach ($finder as $file) {
            $fileData = $this->parseTestFile($file->getRealPath(), $testsPath);

            if (! empty($fileData['tests'])) {
                $results['files'][] = $fileData;
                $results['total_files']++;
                $results['total_tests'] += count($fileData['tests']);

                foreach ($fileData['tests'] as $test) {
                    foreach ($test['tags'] as $tag) {
                        $allTags[$tag] = true;
                    }
                }
            }
        }

        $results['all_tags'] = array_keys($allTags);
        sort($results['all_tags']);

        return $results;
    }

    /**
     * Parse a single test file to extract test names, tags, and suite information.
     *
     * @return array{file: string, full_path: string, suite: string, tests: list<array{name: string, full_name: string, tags: list<string>}>, skipped_tests: list<string>}
     */
    private function parseTestFile(string $filePath, string $basePath): array
    {
        $content = file_get_contents($filePath);

        $relativePath = str_replace($basePath.DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace('\\', '/', $relativePath);

        // Parse test.describe()
        preg_match_all("/test\.describe\(['\"]([^'\"]+)['\"]/", $content, $describes);
        $suiteName = $describes[1][0] ?? null;

        if (! $suiteName) {
            $fileName = basename($filePath, '.spec.js');
            $fileName = basename($fileName, '.spec.ts');
            $fileName = basename($fileName, '.test.js');
            $fileName = basename($fileName, '.test.ts');
            $suiteName = ucwords(str_replace(['-', '_'], ' ', $fileName));
        }

        // Parse test() and test.only()
        preg_match_all("/test(?:\.only)?\(['\"]([^'\"]+)['\"]/", $content, $tests);

        // Parse test.skip()
        preg_match_all("/test\.skip\(['\"]([^'\"]+)['\"]/", $content, $skippedTests);

        // Build test objects with tags extracted from names
        $parsedTests = [];
        foreach ($tests[1] ?? [] as $fullName) {
            $parsed = $this->extractTags($fullName);
            $parsedTests[] = $parsed;
        }

        return [
            'file' => $relativePath,
            'full_path' => $filePath,
            'suite' => $suiteName,
            'tests' => $parsedTests,
            'skipped_tests' => $skippedTests[1] ?? [],
        ];
    }

    /**
     * Extract @tags from a test name.
     *
     * @return array{name: string, full_name: string, tags: list<string>}
     */
    private function extractTags(string $fullName): array
    {
        preg_match_all('/@(\w+)/', $fullName, $matches);
        $tags = array_map(fn (string $tag) => '@'.$tag, $matches[1] ?? []);

        // Clean name by removing tags
        $cleanName = trim(preg_replace('/@\w+/', '', $fullName));

        return [
            'name' => $cleanName,
            'full_name' => $fullName,
            'tags' => $tags,
        ];
    }
}
