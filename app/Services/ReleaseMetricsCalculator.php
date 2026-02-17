<?php

namespace App\Services;

use App\Models\Release;
use App\Models\ReleaseMetricsSnapshot;

class ReleaseMetricsCalculator
{
    /**
     * Calculate metrics from release data.
     *
     * @return array{test_completion_percentage: int, test_pass_rate: int, total_bugs: int, critical_bugs: int, high_bugs: int, bug_closure_rate: int, regression_pass_rate: int, performance_score: int, security_status: string}
     */
    public function calculate(Release $release): array
    {
        // Test metrics from linked test runs (real data)
        $linkedTestRuns = $release->testRuns;
        $totalCases = 0;
        $untestedCases = 0;
        $passedCases = 0;

        foreach ($linkedTestRuns as $testRun) {
            $stats = $testRun->stats ?? [];
            $totalCases += array_sum($stats);
            $untestedCases += ($stats['untested'] ?? 0) + ($stats['retest'] ?? 0);
            $passedCases += ($stats['passed'] ?? 0);
        }

        $testedCases = $totalCases - $untestedCases;
        $testCompletion = $totalCases > 0 ? (int) round(($testedCases / $totalCases) * 100) : 0;
        $testPassRate = $testedCases > 0 ? (int) round(($passedCases / $testedCases) * 100) : 0;

        // Bug metrics — only open bugs count
        $project = $release->project;
        $bugreports = $project->bugreports;

        $openBugs = $bugreports->whereNotIn('status', ['resolved', 'closed'])->count();
        $criticalBugs = $bugreports->whereNotIn('status', ['resolved', 'closed'])->where('severity', 'critical')->count();
        $highBugs = $bugreports->whereNotIn('status', ['resolved', 'closed'])->whereIn('severity', ['critical', 'major'])->count();
        $totalBugs = $bugreports->count();
        $closedBugs = $bugreports->whereIn('status', ['resolved', 'closed'])->count();
        $bugClosureRate = $totalBugs > 0 ? (int) round(($closedBugs / $totalBugs) * 100) : 0;

        // Regression pass rate from linked test runs
        $regressionPassRate = $testedCases > 0 ? (int) round(($passedCases / $testedCases) * 100) : 0;

        return [
            'test_completion_percentage' => $testCompletion,
            'test_pass_rate' => $testPassRate,
            'total_bugs' => $openBugs,
            'critical_bugs' => $criticalBugs,
            'high_bugs' => $highBugs,
            'bug_closure_rate' => $bugClosureRate,
            'regression_pass_rate' => $regressionPassRate,
            'performance_score' => 0,
            'security_status' => 'pending',
        ];
    }

    /**
     * Create a metrics snapshot for the release.
     */
    public function createSnapshot(Release $release): ReleaseMetricsSnapshot
    {
        $metrics = $this->calculate($release);

        return $release->metricsSnapshots()->create([
            ...$metrics,
            'snapshot_at' => now(),
        ]);
    }

    /**
     * Determine health based on metrics.
     */
    public function determineHealth(array $metrics): string
    {
        if (($metrics['critical_bugs'] ?? 0) > 0 || ($metrics['test_pass_rate'] ?? 0) < 70) {
            return 'red';
        }

        if (($metrics['test_pass_rate'] ?? 0) > 95 && ($metrics['critical_bugs'] ?? 0) === 0) {
            return 'green';
        }

        return 'yellow';
    }
}
