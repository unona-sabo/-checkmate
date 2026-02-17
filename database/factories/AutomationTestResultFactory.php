<?php

namespace Database\Factories;

use App\Models\AutomationTestResult;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AutomationTestResult> */
class AutomationTestResultFactory extends Factory
{
    protected $model = AutomationTestResult::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'test_case_id' => null,
            'test_file' => 'tests/'.fake()->word().'.spec.ts',
            'test_name' => fake()->sentence(4),
            'status' => fake()->randomElement(['passed', 'failed', 'skipped', 'timedout']),
            'duration_ms' => fake()->numberBetween(100, 30000),
            'error_message' => null,
            'error_stack' => null,
            'screenshot_path' => null,
            'video_path' => null,
            'executed_at' => now(),
        ];
    }

    public function passed(): static
    {
        return $this->state(fn () => ['status' => 'passed']);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }
}
