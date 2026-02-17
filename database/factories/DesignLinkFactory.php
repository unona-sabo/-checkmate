<?php

namespace Database\Factories;

use App\Models\DesignLink;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignLink>
 */
class DesignLinkFactory extends Factory
{
    protected $model = DesignLink::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'url' => fake()->url(),
            'icon' => fake()->randomElement(['figma', 'zeplin', 'invision', 'pdf', 'link']),
            'color' => fake()->hexColor(),
            'description' => fake()->optional()->sentence(),
            'category' => fake()->randomElement(['Figma', 'Mockups', 'Assets', 'Guidelines']),
            'created_by' => User::factory(),
        ];
    }
}
