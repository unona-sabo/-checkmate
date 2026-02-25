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

test('patch rows updates only provided rows', function () {
    $row1 = $this->checklist->rows()->create([
        'data' => ['item' => 'Row 1'],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $row2 = $this->checklist->rows()->create([
        'data' => ['item' => 'Row 2'],
        'order' => 1,
        'row_type' => 'normal',
    ]);

    $response = $this->patch(route('checklists.patch-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'id' => $row1->id,
                'data' => ['item' => 'Updated Row 1'],
                'order' => 0,
                'row_type' => 'normal',
            ],
        ],
    ]);

    $response->assertRedirect();
    expect($row1->fresh()->data)->toBe(['item' => 'Updated Row 1']);
    expect($row2->fresh()->data)->toBe(['item' => 'Row 2']);
});

test('patch rows does not delete other rows', function () {
    $row1 = $this->checklist->rows()->create([
        'data' => ['item' => 'Row 1'],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $row2 = $this->checklist->rows()->create([
        'data' => ['item' => 'Row 2'],
        'order' => 1,
        'row_type' => 'normal',
    ]);

    $this->patch(route('checklists.patch-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'id' => $row1->id,
                'data' => ['item' => 'Updated'],
                'order' => 0,
                'row_type' => 'normal',
            ],
        ],
    ]);

    expect($this->checklist->rows()->count())->toBe(2);
});

test('patch rows requires row id', function () {
    $response = $this->patch(route('checklists.patch-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'data' => ['item' => 'Test'],
                'order' => 0,
                'row_type' => 'normal',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('rows.0.id');
});

test('patch rows skips non-existent row ids', function () {
    $row = $this->checklist->rows()->create([
        'data' => ['item' => 'Original'],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $response = $this->patch(route('checklists.patch-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'id' => 999999,
                'data' => ['item' => 'Ghost'],
                'order' => 0,
                'row_type' => 'normal',
            ],
        ],
    ]);

    $response->assertRedirect();
    expect($row->fresh()->data)->toBe(['item' => 'Original']);
});

test('patch rows updates styling fields', function () {
    $row = $this->checklist->rows()->create([
        'data' => ['item' => 'Test'],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $this->patch(route('checklists.patch-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'id' => $row->id,
                'data' => ['item' => 'Test'],
                'order' => 0,
                'row_type' => 'normal',
                'background_color' => '#fee2e2',
                'font_color' => '#dc2626',
                'font_weight' => 'bold',
                'module' => ['UI', 'API'],
            ],
        ],
    ]);

    $row->refresh();
    expect($row->background_color)->toBe('#fee2e2');
    expect($row->font_color)->toBe('#dc2626');
    expect($row->font_weight)->toBe('bold');
    expect($row->module)->toBe(['UI', 'API']);
});

test('patch rows preserves updated_at when content unchanged', function () {
    $row = $this->checklist->rows()->create([
        'data' => ['item' => 'Test'],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $originalUpdatedAt = $row->updated_at->toDateTimeString();

    // Wait to ensure timestamp would differ
    $this->travel(5)->minutes();

    $this->patch(route('checklists.patch-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'id' => $row->id,
                'data' => ['item' => 'Test'],
                'order' => 0,
                'row_type' => 'normal',
            ],
        ],
    ]);

    expect($row->fresh()->updated_at->toDateTimeString())->toBe($originalUpdatedAt);
});

test('patch rows bumps updated_at when content changes', function () {
    $row = $this->checklist->rows()->create([
        'data' => ['item' => 'Test'],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $originalUpdatedAt = $row->updated_at->toDateTimeString();

    $this->travel(5)->minutes();

    $this->patch(route('checklists.patch-rows', [$this->project, $this->checklist]), [
        'rows' => [
            [
                'id' => $row->id,
                'data' => ['item' => 'Changed'],
                'order' => 0,
                'row_type' => 'normal',
            ],
        ],
    ]);

    expect($row->fresh()->updated_at->toDateTimeString())->not->toBe($originalUpdatedAt);
});

test('patch rows requires authentication', function () {
    auth()->logout();

    $response = $this->patch(route('checklists.patch-rows', [$this->project, $this->checklist]), [
        'rows' => [],
    ]);

    $response->assertRedirect(route('login'));
});
