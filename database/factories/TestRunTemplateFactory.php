<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestRunTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TestRunTemplate> */
class TestRunTemplateFactory extends Factory
{
    protected $model = TestRunTemplate::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(3, true),
            'description' => null,
            'environment_id' => null,
            'tags' => null,
            'tag_mode' => 'or',
            'file_pattern' => null,
            'options' => null,
        ];
    }
}
