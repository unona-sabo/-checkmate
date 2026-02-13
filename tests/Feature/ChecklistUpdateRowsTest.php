<?php

use App\Models\Checklist;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->checklist = Checklist::factory()->create(['project_id' => $this->project->id]);
    $this->actingAs($this->user);
});

test('update rows saves data successfully', function () {
    $row = $this->checklist->rows()->create([
        'data' => ['item' => 'Test item'],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $response = $this->put(route('checklists.update-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'id' => $row->id,
                'data' => ['item' => 'Updated item'],
                'order' => 0,
                'row_type' => 'normal',
            ],
        ],
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text', 'width' => 200],
        ],
    ]);

    $response->assertRedirect();
    expect($row->fresh()->data)->toBe(['item' => 'Updated item']);
});

test('update rows returns validation errors for invalid data', function () {
    $response = $this->put(route('checklists.update-rows', [$this->project, $this->checklist]), [
        'rows' => 'not-an-array',
    ]);

    $response->assertSessionHasErrors('rows');
});

test('update rows returns validation errors for missing row data', function () {
    $response = $this->put(route('checklists.update-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'id' => null,
                'order' => 0,
                // missing 'data' field
            ],
        ],
    ]);

    $response->assertSessionHasErrors('rows.0.data');
});

test('update rows returns validation errors for invalid row type', function () {
    $response = $this->put(route('checklists.update-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'id' => null,
                'data' => ['item' => 'Test'],
                'order' => 0,
                'row_type' => 'invalid_type',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('rows.0.row_type');
});

test('update rows returns validation errors for invalid column config type', function () {
    $response = $this->put(route('checklists.update-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'id' => null,
                'data' => ['item' => 'Test'],
                'order' => 0,
                'row_type' => 'normal',
            ],
        ],
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'invalid_type'],
        ],
    ]);

    $response->assertSessionHasErrors('columns_config.0.type');
});

test('update rows does not modify database on validation failure', function () {
    $row = $this->checklist->rows()->create([
        'data' => ['item' => 'Original value'],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $this->put(route('checklists.update-rows', [$this->project, $this->checklist]), [
        'rows' => 'not-an-array',
    ]);

    expect($row->fresh()->data)->toBe(['item' => 'Original value']);
});
