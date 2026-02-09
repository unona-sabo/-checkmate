<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TestSuite>
 */
class TestSuiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'name' => fake()->randomElement([
                'Authentication',
                'User Management',
                'Dashboard',
                'Settings',
                'API Endpoints',
                'Payment Processing',
                'Notifications',
                'File Upload',
                'Search',
                'Reports',
            ]),
            'description' => fake()->optional()->sentence(),
            'order' => fake()->numberBetween(0, 10),
        ];
    }

    public function withParent(TestSuite $parent): static
    {
        return $this->state(fn () => [
            'project_id' => $parent->project_id,
            'parent_id' => $parent->id,
        ]);
    }
}
