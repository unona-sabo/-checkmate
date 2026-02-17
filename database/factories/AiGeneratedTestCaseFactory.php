<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiGeneratedTestCase>
 */
class AiGeneratedTestCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'feature_id' => ProjectFeature::factory(),
            'title' => fake()->sentence(4),
            'preconditions' => fake()->optional()->sentence(),
            'test_steps' => [
                'Step 1: '.fake()->sentence(),
                'Step 2: '.fake()->sentence(),
                'Step 3: '.fake()->sentence(),
            ],
            'expected_result' => fake()->sentence(),
            'priority' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'type' => fake()->randomElement(['positive', 'negative', 'edge_case', 'boundary']),
            'is_approved' => false,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
