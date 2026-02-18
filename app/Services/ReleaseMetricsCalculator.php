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
        $release->loadMissing('checklistItems');

        // Test metrics from linked test runs
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

        // Bug metrics — scoped to release timeframe
        $project = $release->project;
        $bugreports = $project->bugreports()->where('created_at', '>=', $release->created_at)->get();

        $openBugs = $bugreports->whereNotIn('status', ['resolved', 'closed'])->count();
        $criticalBugs = $bugreports->whereNotIn('status', ['resolved', 'closed'])->where('severity', 'critical')->count();
        $highBugs = $bugreports->whereNotIn('status', ['resolved', 'closed'])->whereIn('severity', ['critical', 'major'])->count();
        $totalBugs = $bugreports->count();
        $closedBugs = $bugreports->whereIn('status', ['resolved', 'closed'])->count();
        $bugClosureRate = $totalBugs > 0 ? (int) round(($closedBugs / $totalBugs) * 100) : 0;

        // Regression pass rate from linked test runs
        $regressionPassRate = $testedCases > 0 ? (int) round(($passedCases / $testedCases) * 100) : 0;

        // Security status from security checklist items
        $securityStatus = $this->computeSecurityStatus($release);

        // Performance score from performance checklist items
        $performanceScore = $this->computePerformanceScore($release);

        return [
            'test_completion_percentage' => $testCompletion,
            'test_pass_rate' => $testPassRate,
            'total_bugs' => $openBugs,
            'critical_bugs' => $criticalBugs,
            'high_bugs' => $highBugs,
            'bug_closure_rate' => $bugClosureRate,
            'regression_pass_rate' => $regressionPassRate,
            'performance_score' => $performanceScore,
            'security_status' => $securityStatus,
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

    /**
     * Calculate a weighted readiness score for the release.
     *
     * @return array{score: int, color: string, days_to_deadline: int|null, on_track: bool, breakdown: array<string, array{weight: int, value: int, weighted: int}>}
     */
    public function calculateReadinessScore(Release $release, array $snapshot): array
    {
        $testCompletion = $snapshot['test_completion_percentage'] ?? 0;
        $passRate = $snapshot['test_pass_rate'] ?? 0;
        $criticalBugs = $snapshot['critical_bugs'] ?? 0;
        $totalBugs = $snapshot['total_bugs'] ?? 0;

        // Blockers: checklist items with is_blocker=true, not completed/na
        $release->loadMissing('checklistItems');
        $blockerCount = $release->checklistItems
            ->where('is_blocker', true)
            ->whereNotIn('status', ['completed', 'na'])
            ->count();

        // 30% test completion
        $testCompScore = (int) round($testCompletion * 0.3);

        // 30% pass rate
        $passRateScore = (int) round($passRate * 0.3);

        // 20% critical bugs penalty (100 if 0 bugs, -25 per critical bug, min 0)
        $bugBase = max(0, 100 - ($criticalBugs * 25));
        $critBugScore = (int) round($bugBase * 0.2);

        // 20% blockers penalty (100 if 0 blockers, -20 per blocker, min 0)
        $blockerBase = max(0, 100 - ($blockerCount * 20));
        $blockerScore = (int) round($blockerBase * 0.2);

        $score = $testCompScore + $passRateScore + $critBugScore + $blockerScore;
        $score = max(0, min(100, $score));

        $color = $score >= 80 ? 'green' : ($score >= 50 ? 'yellow' : 'red');

        // Days to deadline
        $daysToDeadline = null;
        $onTrack = true;
        if ($release->planned_date) {
            $daysToDeadline = (int) now()->startOfDay()->diffInDays($release->planned_date->startOfDay(), false);
            $onTrack = $daysToDeadline >= 0;
        }

        return [
            'score' => $score,
            'color' => $color,
            'days_to_deadline' => $daysToDeadline,
            'on_track' => $onTrack,
            'breakdown' => [
                'test_completion' => ['weight' => 30, 'value' => $testCompletion, 'weighted' => $testCompScore],
                'pass_rate' => ['weight' => 30, 'value' => $passRate, 'weighted' => $passRateScore],
                'critical_bugs' => ['weight' => 20, 'value' => $criticalBugs, 'weighted' => $critBugScore],
                'blockers' => ['weight' => 20, 'value' => $blockerCount, 'weighted' => $blockerScore],
            ],
        ];
    }

    /**
     * Get blockers and risks for the release.
     *
     * @return array{blocker_count: int, critical_bugs: int, security_status: string, risks: list<string>}
     */
    public function getBlockersAndRisks(Release $release, array $snapshot): array
    {
        $release->loadMissing('checklistItems');

        $blockerCount = $release->checklistItems
            ->where('is_blocker', true)
            ->whereNotIn('status', ['completed', 'na'])
            ->count();

        $criticalBugs = $snapshot['critical_bugs'] ?? 0;
        $securityStatus = $snapshot['security_status'] ?? 'pending';
        $totalBugs = $snapshot['total_bugs'] ?? 0;
        $passRate = $snapshot['test_pass_rate'] ?? 0;
        $testCompletion = $snapshot['test_completion_percentage'] ?? 0;

        $risks = [];

        if ($totalBugs > 10) {
            $risks[] = "High open bug count ({$totalBugs} bugs)";
        }

        if ($passRate > 0 && $passRate < 80) {
            $risks[] = "Low test pass rate ({$passRate}%)";
        }

        if ($testCompletion > 0 && $testCompletion < 50) {
            $risks[] = "Low test completion ({$testCompletion}%)";
        }

        if ($securityStatus === 'pending' || $securityStatus === 'in_progress') {
            $risks[] = 'Security checks not yet passed';
        }

        return [
            'blocker_count' => $blockerCount,
            'critical_bugs' => $criticalBugs,
            'security_status' => $securityStatus,
            'risks' => $risks,
        ];
    }

    /**
     * Compare metrics with the previous release.
     *
     * @return array{previous_version: string, pass_rate_diff: int, bugs_diff: int, test_completion_diff: int, trend: string}|null
     */
    public function compareWithPreviousRelease(Release $release, array $snapshot): ?array
    {
        $previousRelease = Release::query()
            ->where('project_id', $release->project_id)
            ->where('status', 'released')
            ->where('id', '<', $release->id)
            ->orderByDesc('id')
            ->first();

        if (! $previousRelease) {
            return null;
        }

        $previousSnapshot = $previousRelease->latestMetrics;

        if (! $previousSnapshot) {
            return null;
        }

        $passRateDiff = ($snapshot['test_pass_rate'] ?? 0) - $previousSnapshot->test_pass_rate;
        $bugsDiff = ($snapshot['total_bugs'] ?? 0) - $previousSnapshot->total_bugs;
        $testCompletionDiff = ($snapshot['test_completion_percentage'] ?? 0) - $previousSnapshot->test_completion_percentage;

        // Determine trend
        if ($passRateDiff > 0 && $bugsDiff <= 0) {
            $trend = 'better';
        } elseif ($passRateDiff < -10 || $bugsDiff > 5) {
            $trend = 'worse';
        } else {
            $trend = 'same';
        }

        return [
            'previous_version' => $previousRelease->version,
            'pass_rate_diff' => $passRateDiff,
            'bugs_diff' => $bugsDiff,
            'test_completion_diff' => $testCompletionDiff,
            'trend' => $trend,
        ];
    }

    /**
     * Compute security status from security checklist items.
     */
    private function computeSecurityStatus(Release $release): string
    {
        $securityItems = $release->checklistItems->where('category', 'security');

        if ($securityItems->isEmpty()) {
            return 'not_applicable';
        }

        $statuses = $securityItems->pluck('status');

        $allDone = $statuses->every(fn (string $s) => in_array($s, ['completed', 'na']));
        if ($allDone) {
            return 'passed';
        }

        if ($statuses->contains('in_progress')) {
            return 'in_progress';
        }

        return 'pending';
    }

    /**
     * Compute performance score from performance checklist items.
     */
    private function computePerformanceScore(Release $release): int
    {
        $perfItems = $release->checklistItems->where('category', 'performance');

        if ($perfItems->isEmpty()) {
            return 0;
        }

        $actionable = $perfItems->where('status', '!=', 'na');

        if ($actionable->isEmpty()) {
            return 100;
        }

        $completed = $actionable->where('status', 'completed')->count();

        return (int) round(($completed / $actionable->count()) * 100);
    }
}
