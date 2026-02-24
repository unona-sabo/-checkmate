<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestLink>
 */
class TestLinkFactory extends Factory
{
    protected $model = TestLink::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'category' => fake()->randomElement(['documentation', 'monitoring', 'admin', 'api', null]),
            'description' => fake()->sentence(3),
            'url' => fake()->url(),
            'comment' => fake()->optional()->sentence(),
            'order' => 0,
            'created_by' => User::factory(),
        ];
    }
}
