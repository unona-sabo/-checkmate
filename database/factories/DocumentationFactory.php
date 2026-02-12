<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Documentation>
 */
class DocumentationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(),
            'content' => fake()->optional()->paragraphs(3, true),
            'category' => fake()->optional()->randomElement(['API', 'Frontend', 'Database', 'Architecture']),
            'order' => 0,
            'parent_id' => null,
        ];
    }
}
