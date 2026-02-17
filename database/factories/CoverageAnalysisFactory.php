<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CoverageAnalysis>
 */
class CoverageAnalysisFactory extends Factory
{
    public function definition(): array
    {
        $totalFeatures = fake()->numberBetween(5, 30);
        $coveredFeatures = fake()->numberBetween(0, $totalFeatures);

        return [
            'project_id' => Project::factory(),
            'analysis_data' => [
                'summary' => 'Test coverage analysis summary.',
                'overall_coverage' => $totalFeatures > 0 ? round(($coveredFeatures / $totalFeatures) * 100) : 0,
                'gaps' => [],
                'well_covered' => [],
                'risks' => [],
                'recommendations' => [],
            ],
            'overall_coverage' => $totalFeatures > 0 ? round(($coveredFeatures / $totalFeatures) * 100) : 0,
            'total_features' => $totalFeatures,
            'covered_features' => $coveredFeatures,
            'total_test_cases' => fake()->numberBetween(10, 100),
            'gaps_count' => $totalFeatures - $coveredFeatures,
            'analyzed_at' => now(),
        ];
    }
}
