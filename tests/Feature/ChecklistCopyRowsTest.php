<?php

use App\Models\Checklist;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->sourceChecklist = Checklist::factory()->create(['project_id' => $this->project->id]);
    $this->targetChecklist = Checklist::factory()->create(['project_id' => $this->project->id]);
    $this->actingAs($this->user);
});

test('copies rows to target checklist', function () {
    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            ['data' => ['item' => 'Row 1', 'status' => false]],
            ['data' => ['item' => 'Row 2', 'status' => true]],
        ],
    ]);

    $response->assertRedirect(route('checklists.show', [$this->project, $this->targetChecklist]));
    $response->assertSessionHas('success', '2 rows copied successfully.');

    $rows = $this->targetChecklist->rows()->orderBy('order')->get();
    expect($rows)->toHaveCount(2);
    expect($rows[0]->data)->toBe(['item' => 'Row 1', 'status' => false]);
    expect($rows[1]->data)->toBe(['item' => 'Row 2', 'status' => true]);
    expect($rows[0]->order)->toBe(0);
    expect($rows[1]->order)->toBe(1);
});

test('appends rows after existing rows in target checklist', function () {
    $this->targetChecklist->rows()->create([
        'data' => ['item' => 'Existing'],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            ['data' => ['item' => 'Copied row']],
        ],
    ]);

    $response->assertRedirect();

    $rows = $this->targetChecklist->rows()->orderBy('order')->get();
    expect($rows)->toHaveCount(2);
    expect($rows[0]->data)->toBe(['item' => 'Existing']);
    expect($rows[1]->data)->toBe(['item' => 'Copied row']);
    expect($rows[1]->order)->toBe(1);
});

test('preserves styling when copying rows', function () {
    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            [
                'data' => ['item' => 'Styled row'],
                'row_type' => 'section_header',
                'background_color' => '#dbeafe',
                'font_color' => '#2563eb',
                'font_weight' => 'bold',
            ],
        ],
    ]);

    $response->assertRedirect();

    $row = $this->targetChecklist->rows()->first();
    expect($row->row_type)->toBe('section_header');
    expect($row->background_color)->toBe('#dbeafe');
    expect($row->font_color)->toBe('#2563eb');
    expect($row->font_weight)->toBe('bold');
});

test('returns validation error when rows is empty', function () {
    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [],
    ]);

    $response->assertSessionHasErrors('rows');
});

test('returns validation error when rows is missing', function () {
    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), []);

    $response->assertSessionHasErrors('rows');
});

test('returns validation error when row data is missing', function () {
    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            ['row_type' => 'normal'],
        ],
    ]);

    $response->assertSessionHasErrors('rows.0.data');
});

test('requires authentication', function () {
    auth()->logout();

    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            ['data' => ['item' => 'Test']],
        ],
    ]);

    $response->assertRedirect(route('login'));
});

test('requires authorization on project', function () {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            ['data' => ['item' => 'Test']],
        ],
    ]);

    $response->assertForbidden();
});

test('copies rows into a specific section after last filled row', function () {
    // Set up target checklist with sections; section A has filled rows then empty rows
    $this->targetChecklist->rows()->createMany([
        ['data' => ['item' => 'Section A'], 'order' => 0, 'row_type' => 'section_header'],
        ['data' => ['item' => 'A row 1'], 'order' => 1, 'row_type' => 'normal'],
        ['data' => ['item' => 'A row 2'], 'order' => 2, 'row_type' => 'normal'],
        ['data' => ['item' => ''], 'order' => 3, 'row_type' => 'normal'],
        ['data' => ['item' => ''], 'order' => 4, 'row_type' => 'normal'],
        ['data' => ['item' => 'Section B'], 'order' => 5, 'row_type' => 'section_header'],
        ['data' => ['item' => 'B row 1'], 'order' => 6, 'row_type' => 'normal'],
    ]);

    $sectionA = $this->targetChecklist->rows()->where('order', 0)->first();

    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            ['data' => ['item' => 'Copied 1']],
            ['data' => ['item' => 'Copied 2']],
        ],
        'section_row_id' => $sectionA->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', '2 rows copied successfully.');

    $rows = $this->targetChecklist->rows()->orderBy('order')->get();
    expect($rows)->toHaveCount(9);

    // Copied rows insert after last filled row (A row 2 at order 2), not after empty rows
    expect($rows[0]->data['item'])->toBe('Section A');
    expect($rows[1]->data['item'])->toBe('A row 1');
    expect($rows[2]->data['item'])->toBe('A row 2');
    expect($rows[3]->data['item'])->toBe('Copied 1');
    expect($rows[4]->data['item'])->toBe('Copied 2');
    expect($rows[5]->data['item'])->toBe('');  // empty row shifted
    expect($rows[6]->data['item'])->toBe('');  // empty row shifted
    expect($rows[7]->data['item'])->toBe('Section B');
    expect($rows[8]->data['item'])->toBe('B row 1');
});

test('section insertion shifts subsequent rows correctly', function () {
    // Two sections: Section A with one row, Section B with one row
    $this->targetChecklist->rows()->createMany([
        ['data' => ['item' => 'Section A'], 'order' => 0, 'row_type' => 'section_header'],
        ['data' => ['item' => 'A content'], 'order' => 1, 'row_type' => 'normal'],
        ['data' => ['item' => 'Section B'], 'order' => 2, 'row_type' => 'section_header'],
        ['data' => ['item' => 'B content'], 'order' => 3, 'row_type' => 'normal'],
    ]);

    $sectionA = $this->targetChecklist->rows()->where('order', 0)->first();

    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            ['data' => ['item' => 'Inserted']],
        ],
        'section_row_id' => $sectionA->id,
    ]);

    $response->assertRedirect();

    $rows = $this->targetChecklist->rows()->orderBy('order')->get();
    expect($rows)->toHaveCount(5);
    expect($rows[0]->data['item'])->toBe('Section A');
    expect($rows[0]->order)->toBe(0);
    expect($rows[1]->data['item'])->toBe('A content');
    expect($rows[1]->order)->toBe(1);
    expect($rows[2]->data['item'])->toBe('Inserted');
    expect($rows[2]->order)->toBe(2);
    expect($rows[3]->data['item'])->toBe('Section B');
    expect($rows[3]->order)->toBe(3);
    expect($rows[4]->data['item'])->toBe('B content');
    expect($rows[4]->order)->toBe(4);
});

test('maps columns by key when source_columns_config is provided', function () {
    // Target checklist has columns: item (text), status (checkbox)
    $this->targetChecklist->update([
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'checkbox'],
        ],
    ]);

    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            ['data' => ['item' => 'Hello', 'status' => true]],
        ],
        'source_columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'checkbox'],
        ],
    ]);

    $response->assertRedirect();
    $row = $this->targetChecklist->rows()->first();
    expect($row->data)->toBe(['item' => 'Hello', 'status' => true]);
});

test('maps columns by label when keys differ', function () {
    // Target has col_1 = "Name", col_2 = "Done"
    $this->targetChecklist->update([
        'columns_config' => [
            ['key' => 'col_1', 'label' => 'Name', 'type' => 'text'],
            ['key' => 'col_2', 'label' => 'Done', 'type' => 'checkbox'],
        ],
    ]);

    // Source has different keys but matching labels
    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            ['data' => ['field_a' => 'Test row', 'field_b' => true]],
        ],
        'source_columns_config' => [
            ['key' => 'field_a', 'label' => 'Name', 'type' => 'text'],
            ['key' => 'field_b', 'label' => 'Done', 'type' => 'checkbox'],
        ],
    ]);

    $response->assertRedirect();
    $row = $this->targetChecklist->rows()->first();
    expect($row->data['col_1'])->toBe('Test row');
    expect($row->data['col_2'])->toBe(true);
});

test('fills defaults for unmatched target columns', function () {
    // Target has 3 columns
    $this->targetChecklist->update([
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'priority', 'label' => 'Priority', 'type' => 'text'],
            ['key' => 'done', 'label' => 'Done', 'type' => 'checkbox'],
        ],
    ]);

    // Source only has 1 matching column
    $response = $this->post(route('checklists.copy-rows', [$this->project, $this->targetChecklist]), [
        'rows' => [
            ['data' => ['name' => 'Only name']],
        ],
        'source_columns_config' => [
            ['key' => 'name', 'label' => 'Item', 'type' => 'text'],
        ],
    ]);

    $response->assertRedirect();
    $row = $this->targetChecklist->rows()->first();
    expect($row->data['item'])->toBe('Only name');
    expect($row->data['priority'])->toBe('');
    expect($row->data['done'])->toBeFalse();
});
