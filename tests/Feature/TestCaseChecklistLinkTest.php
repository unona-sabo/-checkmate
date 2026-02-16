<?php

use App\Models\Checklist;
use App\Models\Project;
use App\Models\TestSuite;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->testSuite = TestSuite::factory()->create(['project_id' => $this->project->id]);
    $this->actingAs($this->user);
});

test('store test case links back to all checklist rows when params provided', function () {
    $checklist = Checklist::factory()->create([
        'project_id' => $this->project->id,
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'test_case_link', 'label' => 'Test case link', 'type' => 'text'],
        ],
    ]);

    $row1 = $checklist->rows()->create([
        'data' => ['item' => 'Step 1', 'test_case_link' => ''],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $row2 = $checklist->rows()->create([
        'data' => ['item' => 'Step 2', 'test_case_link' => ''],
        'order' => 1,
        'row_type' => 'normal',
    ]);

    $response = $this->post(route('test-cases.store', [$this->project, $this->testSuite]), [
        'title' => 'Test Case',
        'steps' => [['action' => 'Step 1', 'expected' => ''], ['action' => 'Step 2', 'expected' => '']],
        'priority' => 'medium',
        'severity' => 'major',
        'type' => 'functional',
        'automation_status' => 'not_automated',
        'checklist_id' => $checklist->id,
        'checklist_row_ids' => "{$row1->id},{$row2->id}",
        'checklist_link_column' => 'test_case_link',
    ]);

    $response->assertRedirect();

    $testCase = $this->testSuite->testCases()->first();
    expect($testCase)->not->toBeNull();

    $expectedUrl = url("/projects/{$this->project->id}/test-suites/{$this->testSuite->id}/test-cases/{$testCase->id}");

    $row1->refresh();
    $row2->refresh();
    expect($row1->data['test_case_link'])->toBe($expectedUrl);
    expect($row2->data['test_case_link'])->toBe($expectedUrl);
});

test('store test case skips link when checklist params are missing', function () {
    $response = $this->post(route('test-cases.store', [$this->project, $this->testSuite]), [
        'title' => 'Test Case',
        'priority' => 'medium',
        'severity' => 'major',
        'type' => 'functional',
        'automation_status' => 'not_automated',
    ]);

    $response->assertRedirect();

    $testCase = $this->testSuite->testCases()->first();
    expect($testCase)->not->toBeNull();
});

test('store test case skips link when checklist belongs to different project', function () {
    $otherProject = Project::factory()->create(['user_id' => $this->user->id]);
    $checklist = Checklist::factory()->create([
        'project_id' => $otherProject->id,
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'test_case_link', 'label' => 'Test case link', 'type' => 'text'],
        ],
    ]);

    $row = $checklist->rows()->create([
        'data' => ['item' => 'Step 1', 'test_case_link' => ''],
        'order' => 0,
        'row_type' => 'normal',
    ]);

    $this->post(route('test-cases.store', [$this->project, $this->testSuite]), [
        'title' => 'Test Case',
        'priority' => 'medium',
        'severity' => 'major',
        'type' => 'functional',
        'automation_status' => 'not_automated',
        'checklist_id' => $checklist->id,
        'checklist_row_ids' => (string) $row->id,
        'checklist_link_column' => 'test_case_link',
    ]);

    $row->refresh();
    expect($row->data['test_case_link'])->toBe('');
});

test('store test case skips link when column key not in config', function () {
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

    $this->post(route('test-cases.store', [$this->project, $this->testSuite]), [
        'title' => 'Test Case',
        'priority' => 'medium',
        'severity' => 'major',
        'type' => 'functional',
        'automation_status' => 'not_automated',
        'checklist_id' => $checklist->id,
        'checklist_row_ids' => (string) $row->id,
        'checklist_link_column' => 'nonexistent_column',
    ]);

    $testCase = $this->testSuite->testCases()->first();
    expect($testCase)->not->toBeNull();

    $row->refresh();
    expect($row->data)->not->toHaveKey('nonexistent_column');
});

test('store test case skips link when row does not exist', function () {
    $checklist = Checklist::factory()->create([
        'project_id' => $this->project->id,
        'columns_config' => [
            ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
            ['key' => 'test_case_link', 'label' => 'Test case link', 'type' => 'text'],
        ],
    ]);

    $this->post(route('test-cases.store', [$this->project, $this->testSuite]), [
        'title' => 'Test Case',
        'priority' => 'medium',
        'severity' => 'major',
        'type' => 'functional',
        'automation_status' => 'not_automated',
        'checklist_id' => $checklist->id,
        'checklist_row_ids' => '99999',
        'checklist_link_column' => 'test_case_link',
    ]);

    $testCase = $this->testSuite->testCases()->first();
    expect($testCase)->not->toBeNull();
});
