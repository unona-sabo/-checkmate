<?php

namespace Database\Factories;

use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReleaseFeature>
 */
class ReleaseFeatureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'release_id' => Release::factory(),
            'feature_name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'status' => 'planned',
            'test_coverage_percentage' => fake()->numberBetween(0, 100),
            'tests_planned' => fake()->numberBetween(0, 20),
            'tests_executed' => fake()->numberBetween(0, 15),
            'tests_passed' => fake()->numberBetween(0, 15),
        ];
    }
}
