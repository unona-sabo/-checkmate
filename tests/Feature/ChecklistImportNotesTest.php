<?php

use App\Models\Checklist;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->checklist = Checklist::factory()->create([
        'project_id' => $this->project->id,
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'checkbox'],
        ],
    ]);
    $this->actingAs($this->user);
});

test('import notes without sections appends after last filled row', function () {
    $this->checklist->rows()->create(['data' => ['item' => 'Filled'], 'order' => 0, 'row_type' => 'normal']);
    $this->checklist->rows()->create(['data' => ['item' => ''], 'order' => 1, 'row_type' => 'normal']);

    $response = $this->post(route('checklists.import-notes', [$this->project, $this->checklist]), [
        'notes' => ['Note A', 'Note B'],
        'column_key' => 'item',
    ]);

    $response->assertRedirect();

    $items = $this->checklist->rows()->orderBy('order')->get()->pluck('data.item')->all();

    // After "Filled" (last filled), before empty row
    expect($items)->toBe(['Filled', 'Note A', 'Note B', '']);
});

test('import notes into specific section inserts after last filled row in that section', function () {
    $section1 = $this->checklist->rows()->create([
        'data' => ['item' => 'Section One'],
        'order' => 0,
        'row_type' => 'section_header',
    ]);
    $this->checklist->rows()->create(['data' => ['item' => 'Row 1'], 'order' => 1, 'row_type' => 'normal']);
    $this->checklist->rows()->create(['data' => ['item' => ''], 'order' => 2, 'row_type' => 'normal']);

    $section2 = $this->checklist->rows()->create([
        'data' => ['item' => 'Section Two'],
        'order' => 3,
        'row_type' => 'section_header',
    ]);
    $this->checklist->rows()->create(['data' => ['item' => 'Row 2'], 'order' => 4, 'row_type' => 'normal']);

    $response = $this->post(route('checklists.import-notes', [$this->project, $this->checklist]), [
        'notes' => ['Imported A', 'Imported B'],
        'column_key' => 'item',
        'section_row_id' => $section1->id,
    ]);

    $response->assertRedirect();

    $items = $this->checklist->rows()->orderBy('order')->get()->pluck('data.item')->all();

    // After "Row 1" (last filled in section 1), empty row stays after imports
    expect($items)->toBe(['Section One', 'Row 1', 'Imported A', 'Imported B', '', 'Section Two', 'Row 2']);
});

test('import into empty section inserts directly after header', function () {
    $section = $this->checklist->rows()->create([
        'data' => ['item' => 'Empty Section'],
        'order' => 0,
        'row_type' => 'section_header',
    ]);
    $this->checklist->rows()->create([
        'data' => ['item' => 'Next Section'],
        'order' => 1,
        'row_type' => 'section_header',
    ]);

    $response = $this->post(route('checklists.import-notes', [$this->project, $this->checklist]), [
        'notes' => ['New row'],
        'column_key' => 'item',
        'section_row_id' => $section->id,
    ]);

    $response->assertRedirect();

    $items = $this->checklist->rows()->orderBy('order')->get()->pluck('data.item')->all();

    expect($items)->toBe(['Empty Section', 'New row', 'Next Section']);
});

test('import without section_row_id inserts after last filled row in last section', function () {
    $this->checklist->rows()->create([
        'data' => ['item' => 'Section A'],
        'order' => 0,
        'row_type' => 'section_header',
    ]);
    $this->checklist->rows()->create(['data' => ['item' => 'Row A'], 'order' => 1, 'row_type' => 'normal']);

    $this->checklist->rows()->create([
        'data' => ['item' => 'Section B'],
        'order' => 2,
        'row_type' => 'section_header',
    ]);
    $this->checklist->rows()->create(['data' => ['item' => 'Row B'], 'order' => 3, 'row_type' => 'normal']);
    $this->checklist->rows()->create(['data' => ['item' => ''], 'order' => 4, 'row_type' => 'normal']);

    $response = $this->post(route('checklists.import-notes', [$this->project, $this->checklist]), [
        'notes' => ['Appended'],
        'column_key' => 'item',
    ]);

    $response->assertRedirect();

    $items = $this->checklist->rows()->orderBy('order')->get()->pluck('data.item')->all();

    // After "Row B" (last filled in last section B), empty row stays after
    expect($items)->toBe(['Section A', 'Row A', 'Section B', 'Row B', 'Appended', '']);
});

test('import without section_row_id and no sections inserts after last filled row', function () {
    $this->checklist->rows()->create(['data' => ['item' => 'First'], 'order' => 0, 'row_type' => 'normal']);
    $this->checklist->rows()->create(['data' => ['item' => 'Second'], 'order' => 1, 'row_type' => 'normal']);

    $response = $this->post(route('checklists.import-notes', [$this->project, $this->checklist]), [
        'notes' => ['Third'],
        'column_key' => 'item',
    ]);

    $response->assertRedirect();

    $items = $this->checklist->rows()->orderBy('order')->get()->pluck('data.item')->all();

    expect($items)->toBe(['First', 'Second', 'Third']);
});
