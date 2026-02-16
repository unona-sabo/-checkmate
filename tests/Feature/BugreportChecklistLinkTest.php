<?php

use App\Models\Checklist;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('store bugreport links back to checklist row when all params provided', function () {
    $checklist = Checklist::factory()->create([
        'project_id' => $this->project->id,
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'bugreport_link', 'label' => 'Bugreport link', 'type' => 'text'],
        ],
    ]);

    $row = $checklist->rows()->create([
        'data' => ['item' => 'Step 1', 'bugreport_link' => ''],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $response = $this->post(route('bugreports.store', $this->project), [
        'title' => 'Test Bug',
        'severity' => 'minor',
        'priority' => 'medium',
        'status' => 'new',
        'checklist_id' => $checklist->id,
        'checklist_row_ids' => (string) $row->id,
        'checklist_link_column' => 'bugreport_link',
    ]);

    $response->assertRedirect();

    $bugreport = $this->project->bugreports()->first();
    expect($bugreport)->not->toBeNull();

    $row->refresh();
    expect($row->data['bugreport_link'])->toBe(url("/projects/{$this->project->id}/bugreports/{$bugreport->id}"));
});

test('store bugreport links to first row when multiple row ids provided', function () {
    $checklist = Checklist::factory()->create([
        'project_id' => $this->project->id,
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'bugreport_link', 'label' => 'Bugreport link', 'type' => 'text'],
        ],
    ]);

    $row1 = $checklist->rows()->create([
        'data' => ['item' => 'Step 1', 'bugreport_link' => ''],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $row2 = $checklist->rows()->create([
        'data' => ['item' => 'Step 2', 'bugreport_link' => ''],
        'order' => 1,
        'row_type' => 'normal',
    ]);

    $this->post(route('bugreports.store', $this->project), [
        'title' => 'Test Bug',
        'severity' => 'minor',
        'priority' => 'medium',
        'status' => 'new',
        'checklist_id' => $checklist->id,
        'checklist_row_ids' => "{$row1->id},{$row2->id}",
        'checklist_link_column' => 'bugreport_link',
    ]);

    $bugreport = $this->project->bugreports()->first();

    $row1->refresh();
    $row2->refresh();
    expect($row1->data['bugreport_link'])->toBe(url("/projects/{$this->project->id}/bugreports/{$bugreport->id}"));
    expect($row2->data['bugreport_link'])->toBe('');
});

test('store bugreport skips link when checklist params are missing', function () {
    $this->post(route('bugreports.store', $this->project), [
        'title' => 'Test Bug',
        'severity' => 'minor',
        'priority' => 'medium',
        'status' => 'new',
    ]);

    $bugreport = $this->project->bugreports()->first();
    expect($bugreport)->not->toBeNull();
});

test('store bugreport skips link when checklist belongs to different project', function () {
    $otherProject = Project::factory()->create(['user_id' => $this->user->id]);
    $checklist = Checklist::factory()->create([
        'project_id' => $otherProject->id,
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'bugreport_link', 'label' => 'Bugreport link', 'type' => 'text'],
        ],
    ]);

    $row = $checklist->rows()->create([
        'data' => ['item' => 'Step 1', 'bugreport_link' => ''],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $this->post(route('bugreports.store', $this->project), [
        'title' => 'Test Bug',
        'severity' => 'minor',
        'priority' => 'medium',
        'status' => 'new',
        'checklist_id' => $checklist->id,
        'checklist_row_ids' => (string) $row->id,
        'checklist_link_column' => 'bugreport_link',
    ]);

    $row->refresh();
    expect($row->data['bugreport_link'])->toBe('');
});

test('store bugreport skips link when column key not in config', function () {
    $checklist = Checklist::factory()->create([
        'project_id' => $this->project->id,
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
        ],
    ]);

    $row = $checklist->rows()->create([
        'data' => ['item' => 'Step 1'],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $this->post(route('bugreports.store', $this->project), [
        'title' => 'Test Bug',
        'severity' => 'minor',
        'priority' => 'medium',
        'status' => 'new',
        'checklist_id' => $checklist->id,
        'checklist_row_ids' => (string) $row->id,
        'checklist_link_column' => 'nonexistent_column',
    ]);

    $bugreport = $this->project->bugreports()->first();
    expect($bugreport)->not->toBeNull();

    $row->refresh();
    expect($row->data)->not->toHaveKey('nonexistent_column');
});

test('store bugreport skips link when row does not exist', function () {
    $checklist = Checklist::factory()->create([
        'project_id' => $this->project->id,
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'bugreport_link', 'label' => 'Bugreport link', 'type' => 'text'],
        ],
    ]);

    $this->post(route('bugreports.store', $this->project), [
        'title' => 'Test Bug',
        'severity' => 'minor',
        'priority' => 'medium',
        'status' => 'new',
        'checklist_id' => $checklist->id,
        'checklist_row_ids' => '99999',
        'checklist_link_column' => 'bugreport_link',
    ]);

    $bugreport = $this->project->bugreports()->first();
    expect($bugreport)->not->toBeNull();
});
