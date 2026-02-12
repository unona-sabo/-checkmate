<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bugreport>
 */
class BugreportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'steps_to_reproduce' => fake()->optional()->paragraph(),
            'expected_result' => fake()->optional()->sentence(),
            'actual_result' => fake()->optional()->sentence(),
            'severity' => fake()->randomElement(['critical', 'major', 'minor', 'trivial']),
            'priority' => fake()->randomElement(['high', 'medium', 'low']),
            'status' => fake()->randomElement(['new', 'open', 'in_progress', 'resolved', 'closed', 'reopened']),
            'environment' => fake()->optional()->word(),
            'assigned_to' => null,
            'reported_by' => User::factory(),
        ];
    }
}
