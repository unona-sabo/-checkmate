<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Checklist>
 */
class ChecklistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->randomElement([
                'Deployment Checklist',
                'Code Review Checklist',
                'Release Checklist',
                'Security Checklist',
                'Onboarding Checklist',
                'Sprint Planning Checklist',
                'Bug Triage Checklist',
                'Testing Checklist',
            ]),
            'order' => 0,
            'category' => null,
            'columns_config' => [
                ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
                ['key' => 'status', 'label' => 'Done', 'type' => 'checkbox'],
                ['key' => 'assignee', 'label' => 'Assignee', 'type' => 'text'],
            ],
        ];
    }
}
