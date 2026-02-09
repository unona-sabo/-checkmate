<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TestRun>
 */
class TestRunFactory extends Factory
{
    public function definition(): array
    {
        $statuses = ['active', 'completed', 'archived'];

        return [
            'project_id' => Project::factory(),
            'name' => fake()->randomElement([
                'Sprint 1 Regression',
                'Release 2.0 Smoke Test',
                'Hotfix Verification',
                'Feature X Acceptance',
                'Security Audit',
                'Performance Test Run',
                'Integration Test Cycle',
                'UAT Round 1',
                'Pre-release Testing',
                'Post-deployment Verification',
            ]),
            'description' => fake()->optional()->sentence(),
            'environment' => fake()->randomElement(['Development', 'Staging', 'Production', 'QA']),
            'milestone' => fake()->optional()->randomElement(['v1.0', 'v1.1', 'v2.0', 'Sprint 5', 'Sprint 6']),
            'status' => fake()->randomElement($statuses),
            'progress' => fake()->numberBetween(0, 100),
            'stats' => null,
            'started_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'completed_at' => fake()->optional()->dateTimeBetween('now', '+1 week'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'progress' => 100,
            'completed_at' => now(),
        ]);
    }
}
