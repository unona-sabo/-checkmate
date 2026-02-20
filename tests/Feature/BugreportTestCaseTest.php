<?php

use App\Models\Bugreport;
use App\Models\Project;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('show page includes test suites data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $user->id,
    ]);

    $parentSuite = TestSuite::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
    ]);
    $childSuite = TestSuite::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentSuite->id,
    ]);

    $response = $this->actingAs($user)->get(route('bugreports.show', [$project, $bugreport]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Bugreports/Show')
        ->has('bugreport')
        ->has('testSuites', 1, fn ($suite) => $suite
            ->where('id', $parentSuite->id)
            ->where('name', $parentSuite->name)
            ->has('children', 1)
            ->etc()
        )
    );
});

test('creating test case from bugreport copies attachments', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);

    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $user->id,
    ]);

    // Create a fake file and attach to bugreport
    $file = UploadedFile::fake()->image('screenshot.png', 200, 200);
    $storedPath = $file->store('attachments/bugreports', 'public');
    $bugreport->attachments()->create([
        'original_filename' => 'screenshot.png',
        'stored_path' => $storedPath,
        'mime_type' => 'image/png',
        'size' => $file->getSize(),
    ]);

    $response = $this->actingAs($user)->post(
        route('test-cases.store', [$project, $suite]),
        [
            'title' => 'Test from bug',
            'priority' => 'high',
            'severity' => 'critical',
            'type' => 'functional',
            'automation_status' => 'not_automated',
            'bugreport_id' => $bugreport->id,
        ]
    );

    $response->assertRedirect();

    $testCase = $suite->testCases()->first();
    expect($testCase)->not->toBeNull();
    expect($testCase->attachments)->toHaveCount(1);
    expect($testCase->attachments->first()->original_filename)->toBe('screenshot.png');

    // Verify the file was actually copied (different path)
    $copiedPath = $testCase->attachments->first()->stored_path;
    expect($copiedPath)->not->toBe($storedPath);
    Storage::disk('public')->assertExists($copiedPath);
});

test('test case create page includes bugreport attachments when bugreport_id provided', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $suite = TestSuite::factory()->create(['project_id' => $project->id]);

    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $user->id,
    ]);
    $bugreport->attachments()->create([
        'original_filename' => 'log.txt',
        'stored_path' => 'attachments/bugreports/log.txt',
        'mime_type' => 'text/plain',
        'size' => 1024,
    ]);

    $response = $this->actingAs($user)->get(
        route('test-cases.create', [$project, $suite]).'?bugreport_id='.$bugreport->id
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('TestCases/Create')
        ->has('bugreportAttachments', 1, fn ($att) => $att
            ->where('original_filename', 'log.txt')
            ->etc()
        )
    );
});
