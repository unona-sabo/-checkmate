<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestCommand;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCommand>
 */
class TestCommandFactory extends Factory
{
    protected $model = TestCommand::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'category' => fake()->randomElement(['deploy', 'database', 'testing', 'build', null]),
            'description' => fake()->sentence(3),
            'command' => fake()->randomElement([
                'php artisan migrate',
                'npm run build',
                'docker-compose up -d',
                'git pull origin main',
                'composer install --no-dev',
            ]),
            'comment' => fake()->optional()->sentence(),
            'order' => 0,
            'created_by' => User::factory(),
        ];
    }
}
