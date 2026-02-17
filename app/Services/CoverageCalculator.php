<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

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
            ->whereHas('testCases')
            ->count();

        return (int) round(($coveredFeatures / $totalFeatures) * 100);
    }

    /**
     * @return list<array{module: string, total_features: int, covered_features: int, test_cases_count: int, coverage_percentage: int}>
     */
    public function getCoverageByModule(Project $project): array
    {
        $modules = $project->features()
            ->where('is_active', true)
            ->select('module', DB::raw('count(*) as total'))
            ->groupBy('module')
            ->get();

        $coverage = [];

        foreach ($modules as $module) {
            $covered = $project->features()
                ->where('module', $module->module)
                ->where('is_active', true)
                ->whereHas('testCases')
                ->count();

            $testCasesCount = $project->features()
                ->where('module', $module->module)
                ->withCount('testCases')
                ->get()
                ->sum('test_cases_count');

            $coverage[] = [
                'module' => $module->module ?? 'Uncategorized',
                'total_features' => $module->total,
                'covered_features' => $covered,
                'test_cases_count' => $testCasesCount,
                'coverage_percentage' => $module->total > 0
                    ? (int) round(($covered / $module->total) * 100)
                    : 0,
            ];
        }

        return $coverage;
    }

    /**
     * @return list<array{id: int, feature: string, description: string|null, module: string|null, category: string|null, priority: string}>
     */
    public function getGaps(Project $project): array
    {
        return $project->features()
            ->where('is_active', true)
            ->doesntHave('testCases')
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
     * @return array{overall_coverage: int, total_features: int, covered_features: int, uncovered_features: int, total_test_cases: int, gaps_count: int}
     */
    public function getStatistics(Project $project): array
    {
        $totalFeatures = $project->features()->where('is_active', true)->count();
        $coveredFeatures = $project->features()
            ->where('is_active', true)
            ->whereHas('testCases')
            ->count();

        $totalTestCases = $project->testSuites()
            ->withCount('testCases')
            ->get()
            ->sum('test_cases_count');

        return [
            'overall_coverage' => $this->calculateOverallCoverage($project),
            'total_features' => $totalFeatures,
            'covered_features' => $coveredFeatures,
            'uncovered_features' => $totalFeatures - $coveredFeatures,
            'total_test_cases' => $totalTestCases,
            'gaps_count' => count($this->getGaps($project)),
        ];
    }
}
