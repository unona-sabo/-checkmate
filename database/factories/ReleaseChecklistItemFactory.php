<?php

namespace Database\Factories;

use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReleaseChecklistItem>
 */
class ReleaseChecklistItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'release_id' => Release::factory(),
            'category' => fake()->randomElement(['testing', 'security', 'performance', 'deployment', 'documentation']),
            'title' => fake()->sentence(),
            'description' => fake()->optional()->sentence(),
            'status' => 'pending',
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'is_blocker' => false,
            'order' => 0,
        ];
    }
}
