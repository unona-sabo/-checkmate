<?php

namespace Database\Factories;

use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReleaseMetricsSnapshot>
 */
class ReleaseMetricsSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'release_id' => Release::factory(),
            'test_completion_percentage' => fake()->numberBetween(0, 100),
            'test_pass_rate' => fake()->numberBetween(0, 100),
            'total_bugs' => fake()->numberBetween(0, 50),
            'critical_bugs' => fake()->numberBetween(0, 5),
            'high_bugs' => fake()->numberBetween(0, 10),
            'bug_closure_rate' => fake()->numberBetween(0, 100),
            'regression_pass_rate' => fake()->numberBetween(0, 100),
            'performance_score' => fake()->numberBetween(0, 100),
            'security_status' => fake()->randomElement(['pending', 'passed', 'failed']),
            'snapshot_at' => now(),
        ];
    }
}
