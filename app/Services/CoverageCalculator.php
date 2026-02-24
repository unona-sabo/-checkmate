<?php

namespace App\Services;

use App\Models\Project;

class CoverageCalculator
{
    public function calculateOverallCoverage(Project $project): int
    {
        $totalFeatures = $project->features()->where('is_active', true)->count();

        if ($totalFeatures === 0) {
            return 0;
        }

        $coveredFeatures = $project->features()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereHas('testCases')->orWhereHas('checklists'))
            ->count();

        return (int) round(($coveredFeatures / $totalFeatures) * 100);
    }

    /**
     * @return list<array{module: string, total_features: int, covered_features: int, test_cases_count: int, checklists_count: int, coverage_percentage: int}>
     */
    public function getCoverageByModule(Project $project): array
    {
        $features = $project->features()
            ->where('is_active', true)
            ->withCount(['testCases', 'checklists'])
            ->with('testCases:id,module', 'checklists:id,module')
            ->get();

        /** @var array<string, array{total: int, covered: int, test_cases: int, checklists: int}> $moduleStats */
        $moduleStats = [];

        foreach ($features as $feature) {
            $featureModules = $feature->module ?? [];
            if ($featureModules === []) {
                $featureModules = ['Uncategorized'];
            }

            $isCovered = ($feature->test_cases_count > 0) || ($feature->checklists_count > 0);

            // Initialize feature-level module stats (without checklists — counted separately below)
            foreach ($featureModules as $mod) {
                if (! isset($moduleStats[$mod])) {
                    $moduleStats[$mod] = ['total' => 0, 'covered' => 0, 'test_cases' => 0, 'checklists' => 0];
                }

                $moduleStats[$mod]['total']++;
                if ($isCovered) {
                    $moduleStats[$mod]['covered']++;
                }
            }

            // Count test cases by their own module (fall back to feature modules)
            foreach ($feature->testCases as $testCase) {
                $tcModules = $testCase->module ?? [];
                if ($tcModules === []) {
                    $tcModules = $featureModules;
                }

                foreach ($tcModules as $mod) {
                    if (! isset($moduleStats[$mod])) {
                        $moduleStats[$mod] = ['total' => 0, 'covered' => 0, 'test_cases' => 0, 'checklists' => 0];
                    }
                    $moduleStats[$mod]['test_cases']++;
                }
            }

            // Count checklists by their own module (fall back to feature modules)
            foreach ($feature->checklists as $checklist) {
                $clModules = $checklist->module ?? [];
                if ($clModules === []) {
                    $clModules = $featureModules;
                }

                foreach ($clModules as $mod) {
                    if (! isset($moduleStats[$mod])) {
                        $moduleStats[$mod] = ['total' => 0, 'covered' => 0, 'test_cases' => 0, 'checklists' => 0];
                    }
                    $moduleStats[$mod]['checklists']++;
                }
            }
        }

        $coverage = [];

        foreach ($moduleStats as $module => $stats) {
            $coverage[] = [
                'module' => $module,
                'total_features' => $stats['total'],
                'covered_features' => $stats['covered'],
                'test_cases_count' => $stats['test_cases'],
                'checklists_count' => $stats['checklists'],
                'coverage_percentage' => $stats['total'] > 0
                    ? (int) round(($stats['covered'] / $stats['total']) * 100)
                    : 0,
            ];
        }

        return $coverage;
    }

    /**
     * @return list<array{id: int, feature: string, description: string|null, module: list<string>|null, category: string|null, priority: string}>
     */
    public function getGaps(Project $project): array
    {
        return $project->features()
            ->where('is_active', true)
            ->doesntHave('testCases')
            ->doesntHave('checklists')
            ->get()
            ->map(fn ($feature) => [
                'id' => $feature->id,
                'feature' => $feature->name,
                'description' => $feature->description,
                'module' => $feature->module,
                'category' => $feature->category,
                'priority' => $feature->priority,
            ])
            ->toArray();
    }

    /**
     * @return array{overall_coverage: int, total_features: int, covered_features: int, uncovered_features: int, total_test_cases: int, total_checklists: int, gaps_count: int}
     */
    public function getStatistics(Project $project): array
    {
        $totalFeatures = $project->features()->where('is_active', true)->count();
        $coveredFeatures = $project->features()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereHas('testCases')->orWhereHas('checklists'))
            ->count();

        $totalTestCases = $project->testSuites()
            ->withCount('testCases')
            ->get()
            ->sum('test_cases_count');

        $totalChecklists = $project->checklists()->count();

        return [
            'overall_coverage' => $this->calculateOverallCoverage($project),
            'total_features' => $totalFeatures,
            'covered_features' => $coveredFeatures,
            'uncovered_features' => $totalFeatures - $coveredFeatures,
            'total_test_cases' => $totalTestCases,
            'total_checklists' => $totalChecklists,
            'gaps_count' => count($this->getGaps($project)),
        ];
    }
}
