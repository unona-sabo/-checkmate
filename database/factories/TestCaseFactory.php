<?php

namespace Database\Factories;

use App\Models\TestSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TestCase>
 */
class TestCaseFactory extends Factory
{
    public function definition(): array
    {
        $priorities = ['low', 'medium', 'high', 'critical'];
        $severities = ['trivial', 'minor', 'major', 'critical', 'blocker'];
        $types = ['functional', 'smoke', 'regression', 'integration', 'acceptance', 'performance', 'security', 'usability', 'other'];
        $automationStatuses = ['not_automated', 'to_be_automated', 'automated'];

        return [
            'test_suite_id' => TestSuite::factory(),
            'title' => fake()->randomElement([
                'Verify user can login with valid credentials',
                'Verify error message for invalid password',
                'Verify password reset flow',
                'Verify user registration',
                'Verify logout functionality',
                'Verify session timeout',
                'Verify remember me feature',
                'Verify two-factor authentication',
                'Verify profile update',
                'Verify email change',
                'Verify password change',
                'Verify account deletion',
                'Verify data export',
                'Verify notification preferences',
                'Verify payment processing',
            ]),
            'description' => fake()->optional()->paragraph(),
            'preconditions' => fake()->optional()->sentence(),
            'steps' => [
                ['action' => 'Navigate to the login page', 'expected' => 'Login page is displayed'],
                ['action' => 'Enter valid username', 'expected' => 'Username is accepted'],
                ['action' => 'Enter valid password', 'expected' => 'Password is masked'],
                ['action' => 'Click login button', 'expected' => 'User is redirected to dashboard'],
            ],
            'expected_result' => fake()->sentence(),
            'priority' => fake()->randomElement($priorities),
            'severity' => fake()->randomElement($severities),
            'type' => fake()->randomElement($types),
            'module' => null,
            'automation_status' => fake()->randomElement($automationStatuses),
            'tags' => fake()->optional()->randomElements(['smoke', 'regression', 'critical', 'P1', 'P2', 'login', 'security'], 2),
            'order' => fake()->numberBetween(0, 20),
            'created_by' => null,
        ];
    }
}
