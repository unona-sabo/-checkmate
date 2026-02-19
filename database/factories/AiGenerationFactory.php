<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['gemini', 'claude']),
            'model' => 'gemini-2.0-flash',
            'input_type' => fake()->randomElement(['text', 'file', 'image']),
            'test_cases_generated' => fake()->numberBetween(1, 10),
            'test_cases_approved' => fake()->numberBetween(0, 5),
            'test_cases_imported' => fake()->numberBetween(0, 5),
            'test_suite_id' => null,
        ];
    }
}
