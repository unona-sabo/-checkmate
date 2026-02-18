<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Note>
 */
class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(),
            'content' => fake()->optional()->paragraphs(2, true),
            'documentation_id' => null,
            'is_draft' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_draft' => false,
        ]);
    }
}
