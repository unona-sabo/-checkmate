<?php

namespace Database\Factories;

use App\Models\TestRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TestRunCase>
 */
class TestRunCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'test_run_id' => TestRun::factory(),
            'test_case_id' => null,
            'title' => fake()->sentence(4),
            'status' => 'untested',
        ];
    }
}
