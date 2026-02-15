<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FeatureDescription>
 */
class FeatureDescriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_key' => fake()->randomElement(['checklists', 'test-suites', 'test-runs', 'bugreports', 'documentations', 'notes']),
            'feature_index' => fake()->numberBetween(0, 14),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'is_custom' => false,
            'created_by' => null,
        ];
    }

    public function custom(): static
    {
        return $this->state(fn () => [
            'is_custom' => true,
            'created_by' => User::factory(),
        ]);
    }
}
