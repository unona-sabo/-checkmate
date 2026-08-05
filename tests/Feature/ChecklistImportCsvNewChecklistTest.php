<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('import CSV into a new checklist creates columns from headers and rows from data', function () {
    $csv = "Item,Status,Priority\nWrite tests,Open,High\nReview PR,Closed,Low";
    $file = UploadedFile::fake()->createWithContent('checklist.csv', $csv);

    $response = $this->post(route('checklists.import-csv-new-checklist', [$this->project]), [
        'name' => 'Imported Checklist',
        'file' => $file,
    ]);

    $checklist = $this->project->checklists()->where('name', 'Imported Checklist')->first();

    expect($checklist)->not->toBeNull();

    $response->assertRedirect(route('checklists.show', [$this->project, $checklist]));

    expect($checklist->columns_config)->toBe([
        ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
        ['key' => 'priority', 'label' => 'Priority', 'type' => 'text'],
    ]);

    $rows = $checklist->rows()->orderBy('order')->get();
    expect($rows)->toHaveCount(2);
    expect($rows[0]->data)->toBe(['item' => 'Write tests', 'status' => 'Open', 'priority' => 'High']);
    expect($rows[1]->data)->toBe(['item' => 'Review PR', 'status' => 'Closed', 'priority' => 'Low']);
});

test('import CSV into a new checklist requires a name', function () {
    $file = UploadedFile::fake()->createWithContent('checklist.csv', "Item\nOne");

    $response = $this->post(route('checklists.import-csv-new-checklist', [$this->project]), [
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('name');
});

test('import CSV into a new checklist requires a file', function () {
    $response = $this->post(route('checklists.import-csv-new-checklist', [$this->project]), [
        'name' => 'No File Checklist',
    ]);

    $response->assertSessionHasErrors('file');
});

test('import CSV into a new checklist rejects an empty file', function () {
    $file = UploadedFile::fake()->createWithContent('empty.csv', '');

    $response = $this->post(route('checklists.import-csv-new-checklist', [$this->project]), [
        'name' => 'Empty Checklist',
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
    expect($this->project->checklists()->where('name', 'Empty Checklist')->exists())->toBeFalse();
});

test('import CSV into a new checklist deduplicates duplicate header names', function () {
    $csv = "Item,Item\nA,B";
    $file = UploadedFile::fake()->createWithContent('dupes.csv', $csv);

    $response = $this->post(route('checklists.import-csv-new-checklist', [$this->project]), [
        'name' => 'Dupe Headers',
        'file' => $file,
    ]);

    $checklist = $this->project->checklists()->where('name', 'Dupe Headers')->first();

    $response->assertRedirect(route('checklists.show', [$this->project, $checklist]));

    expect($checklist->columns_config)->toBe([
        ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
        ['key' => 'item_1', 'label' => 'Item', 'type' => 'text'],
    ]);

    $row = $checklist->rows()->first();
    expect($row->data)->toBe(['item' => 'A', 'item_1' => 'B']);
});
