<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Release>
 */
class ReleaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'version' => fake()->numerify('#.#.#'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'planned_date' => fake()->optional()->dateTimeBetween('now', '+3 months'),
            'status' => 'planning',
            'health' => 'yellow',
            'decision' => 'pending',
            'created_by' => User::factory(),
        ];
    }
}
