<?php

use App\Models\Checklist;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('reorder endpoint persists order and category', function () {
    $a = Checklist::factory()->create(['project_id' => $this->project->id, 'order' => 0, 'category' => null]);
    $b = Checklist::factory()->create(['project_id' => $this->project->id, 'order' => 1, 'category' => null]);

    $response = $this->put(route('checklists.reorder', $this->project), [
        'items' => [
            ['id' => $b->id, 'order' => 0, 'category' => 'Important'],
            ['id' => $a->id, 'order' => 1, 'category' => null],
        ],
    ]);

    $response->assertRedirect();

    expect($b->fresh())
        ->order->toBe(0)
        ->category->toBe('Important');

    expect($a->fresh())
        ->order->toBe(1)
        ->category->toBeNull();
});

test('store sets order to max plus one', function () {
    Checklist::factory()->create(['project_id' => $this->project->id, 'order' => 0]);
    Checklist::factory()->create(['project_id' => $this->project->id, 'order' => 1]);

    $response = $this->post(route('checklists.store', $this->project), [
        'name' => 'Third Checklist',
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
        ],
    ]);

    $response->assertRedirect();

    $newest = Checklist::where('project_id', $this->project->id)->orderByDesc('order')->first();
    expect($newest->name)->toBe('Third Checklist');
    expect($newest->order)->toBe(2);
});

test('index returns checklists ordered by order field', function () {
    Checklist::factory()->create(['project_id' => $this->project->id, 'order' => 2, 'category' => 'Beta', 'name' => 'Beta First']);
    Checklist::factory()->create(['project_id' => $this->project->id, 'order' => 0, 'category' => null, 'name' => 'Uncategorized First']);
    Checklist::factory()->create(['project_id' => $this->project->id, 'order' => 3, 'category' => 'Alpha', 'name' => 'Alpha First']);
    Checklist::factory()->create(['project_id' => $this->project->id, 'order' => 1, 'category' => null, 'name' => 'Uncategorized Second']);

    $response = $this->get(route('checklists.index', $this->project));

    $response->assertSuccessful();

    $checklists = $response->original->getData()['page']['props']['checklists'];
    $names = collect($checklists)->pluck('name')->all();

    // Sorted purely by order field — frontend handles category grouping
    expect($names)->toBe([
        'Uncategorized First',
        'Uncategorized Second',
        'Beta First',
        'Alpha First',
    ]);
});

test('reorder ignores checklists from other projects', function () {
    $otherProject = Project::factory()->create(['user_id' => $this->user->id]);
    $otherChecklist = Checklist::factory()->create(['project_id' => $otherProject->id, 'order' => 0]);
    $myChecklist = Checklist::factory()->create(['project_id' => $this->project->id, 'order' => 0]);

    $this->put(route('checklists.reorder', $this->project), [
        'items' => [
            ['id' => $otherChecklist->id, 'order' => 99, 'category' => 'Hacked'],
            ['id' => $myChecklist->id, 'order' => 1, 'category' => null],
        ],
    ]);

    // Other project's checklist should not be modified
    expect($otherChecklist->fresh())
        ->order->toBe(0)
        ->category->toBeNull();

    expect($myChecklist->fresh())
        ->order->toBe(1);
});

test('store accepts optional category', function () {
    $response = $this->post(route('checklists.store', $this->project), [
        'name' => 'Categorized Checklist',
        'category' => 'Important',
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
        ],
    ]);

    $response->assertRedirect();

    $checklist = Checklist::where('name', 'Categorized Checklist')->first();
    expect($checklist->category)->toBe('Important');
});
