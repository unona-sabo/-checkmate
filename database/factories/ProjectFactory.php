<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'E-Commerce Platform',
                'Mobile App',
                'Admin Dashboard',
                'API Gateway',
                'User Portal',
                'Payment System',
                'Notification Service',
                'Analytics Dashboard',
                'CRM System',
                'Inventory Management',
            ]),
        ];
    }
}
