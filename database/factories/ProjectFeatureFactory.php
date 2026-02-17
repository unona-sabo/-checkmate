<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectFeature>
 */
class ProjectFeatureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'module' => fake()->randomElement(['UI', 'API', 'Backend', 'Database']),
            'category' => fake()->randomElement(['Authentication', 'Payment', 'Admin', 'Dashboard', 'Settings']),
            'priority' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'is_active' => true,
        ];
    }
}
