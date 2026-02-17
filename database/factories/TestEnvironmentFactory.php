<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestEnvironment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TestEnvironment> */
class TestEnvironmentFactory extends Factory
{
    protected $model = TestEnvironment::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->randomElement(['Staging', 'Production', 'Development', 'QA']),
            'base_url' => fake()->url(),
            'variables' => null,
            'workers' => 1,
            'retries' => 0,
            'browser' => 'chromium',
            'headed' => false,
            'timeout' => 30000,
            'description' => null,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
