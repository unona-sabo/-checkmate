<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestUser>
 */
class TestUserFactory extends Factory
{
    protected $model = TestUser::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => fake()->password(8, 16),
            'role' => fake()->randomElement(['admin', 'user', 'moderator', 'tester', null]),
            'environment' => fake()->randomElement(['develop', 'staging', 'production', null]),
            'description' => fake()->optional()->sentence(),
            'is_valid' => true,
            'additional_info' => null,
            'tags' => null,
            'created_by' => User::factory(),
            'order' => 0,
        ];
    }

    /**
     * Mark the test user as invalid.
     */
    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_valid' => false,
        ]);
    }
}
