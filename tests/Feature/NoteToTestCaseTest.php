<?php

use App\Models\Project;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('test case can be created with steps from note', function () {
    $testSuite = TestSuite::factory()->create(['project_id' => $this->project->id]);

    $response = $this->withSession(['_token' => 'test-token'])->post(
        route('test-cases.store', [$this->project, $testSuite]),
        [
            '_token' => 'test-token',
            'title' => 'Verify user login flow',
            'steps' => [
                ['action' => 'Navigate to the login page', 'expected' => null],
                ['action' => 'Enter valid credentials', 'expected' => null],
                ['action' => 'Click the login button', 'expected' => null],
                ['action' => 'Verify dashboard is displayed', 'expected' => null],
            ],
            'priority' => 'medium',
            'severity' => 'major',
            'type' => 'functional',
            'automation_status' => 'not_automated',
            'tags' => [],
        ],
    );

    $testCase = TestCaseModel::first();
    expect($testCase)->not->toBeNull();

    expect($testCase)
        ->title->toBe('Verify user login flow')
        ->priority->toBe('medium')
        ->severity->toBe('major')
        ->type->toBe('functional')
        ->automation_status->toBe('not_automated')
        ->test_suite_id->toBe($testSuite->id);

    expect($testCase->steps)->toHaveCount(4);
    expect($testCase->steps[0]['action'])->toBe('Navigate to the login page');
    expect($testCase->steps[0]['expected'])->toBeNull();
    expect($testCase->steps[3]['action'])->toBe('Verify dashboard is displayed');
});

test('test case from note requires title', function () {
    $testSuite = TestSuite::factory()->create(['project_id' => $this->project->id]);

    $response = $this->withSession(['_token' => 'test-token'])->post(
        route('test-cases.store', [$this->project, $testSuite]),
        [
            '_token' => 'test-token',
            'steps' => [
                ['action' => 'Some step', 'expected' => null],
            ],
            'priority' => 'medium',
            'severity' => 'major',
            'type' => 'functional',
            'automation_status' => 'not_automated',
        ],
    );

    $response->assertInvalid('title');
    expect(TestCaseModel::count())->toBe(0);
});

test('test case from note can be created on child suite', function () {
    $parentSuite = TestSuite::factory()->create(['project_id' => $this->project->id]);
    $childSuite = TestSuite::factory()->withParent($parentSuite)->create();

    $response = $this->withSession(['_token' => 'test-token'])->post(
        route('test-cases.store', [$this->project, $childSuite]),
        [
            '_token' => 'test-token',
            'title' => 'Child suite test case',
            'steps' => [
                ['action' => 'Step one', 'expected' => null],
                ['action' => 'Step two', 'expected' => null],
            ],
            'priority' => 'medium',
            'severity' => 'major',
            'type' => 'functional',
            'automation_status' => 'not_automated',
            'tags' => [],
        ],
    );

    $testCase = TestCaseModel::first();
    expect($testCase)->not->toBeNull();
    expect($testCase->test_suite_id)->toBe($childSuite->id);
    expect($testCase->steps)->toHaveCount(2);
});
