<?php

use App\Models\Attachment;
use App\Models\Bugreport;
use App\Models\Project;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('bugreport can be created with attachments', function () {
    $response = $this->post(route('bugreports.store', $this->project), [
        'title' => 'Test Bug',
        'severity' => 'major',
        'priority' => 'high',
        'status' => 'new',
        'attachments' => [
            UploadedFile::fake()->image('screenshot.png', 800, 600),
            UploadedFile::fake()->create('log.txt', 100, 'text/plain'),
        ],
    ]);

    $bugreport = Bugreport::first();
    $response->assertRedirect(route('bugreports.show', [$this->project, $bugreport]));
    expect($bugreport->attachments)->toHaveCount(2);
    Storage::disk('public')->assertExists($bugreport->attachments[0]->stored_path);
    Storage::disk('public')->assertExists($bugreport->attachments[1]->stored_path);
});

test('bugreport can be updated with new attachments', function () {
    $bugreport = Bugreport::factory()->create([
        'project_id' => $this->project->id,
        'reported_by' => $this->user->id,
    ]);

    $response = $this->put(route('bugreports.update', [$this->project, $bugreport]), [
        'title' => 'Updated Bug',
        'severity' => 'critical',
        'priority' => 'high',
        'status' => 'open',
        'attachments' => [
            UploadedFile::fake()->image('new-screenshot.jpg'),
        ],
    ]);

    $response->assertRedirect(route('bugreports.show', [$this->project, $bugreport]));
    expect($bugreport->fresh()->attachments)->toHaveCount(1);
});

test('bugreport show page loads attachments', function () {
    $bugreport = Bugreport::factory()->create([
        'project_id' => $this->project->id,
        'reported_by' => $this->user->id,
    ]);

    $bugreport->attachments()->create([
        'original_filename' => 'test.png',
        'stored_path' => 'attachments/bugreports/test.png',
        'mime_type' => 'image/png',
        'size' => 1024,
    ]);

    $response = $this->get(route('bugreports.show', [$this->project, $bugreport]));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Bugreports/Show')
        ->has('bugreport.attachments', 1)
    );
});

test('bugreport attachment can be deleted', function () {
    $bugreport = Bugreport::factory()->create([
        'project_id' => $this->project->id,
        'reported_by' => $this->user->id,
    ]);

    Storage::disk('public')->put('attachments/bugreports/test.png', 'content');

    $attachment = $bugreport->attachments()->create([
        'original_filename' => 'test.png',
        'stored_path' => 'attachments/bugreports/test.png',
        'mime_type' => 'image/png',
        'size' => 1024,
    ]);

    $response = $this->delete(route('bugreports.destroy-attachment', [$this->project, $bugreport, $attachment]));
    $response->assertRedirect();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing('attachments/bugreports/test.png');
});

test('deleting bugreport cleans up attachment files', function () {
    $bugreport = Bugreport::factory()->create([
        'project_id' => $this->project->id,
        'reported_by' => $this->user->id,
    ]);

    Storage::disk('public')->put('attachments/bugreports/test.png', 'content');

    $bugreport->attachments()->create([
        'original_filename' => 'test.png',
        'stored_path' => 'attachments/bugreports/test.png',
        'mime_type' => 'image/png',
        'size' => 1024,
    ]);

    $this->delete(route('bugreports.destroy', [$this->project, $bugreport]));
    Storage::disk('public')->assertMissing('attachments/bugreports/test.png');
});

test('test case can be created with attachments', function () {
    $testSuite = TestSuite::factory()->create(['project_id' => $this->project->id]);

    $response = $this->post(route('test-cases.store', [$this->project, $testSuite]), [
        'title' => 'Test Case with Files',
        'priority' => 'medium',
        'severity' => 'major',
        'type' => 'functional',
        'automation_status' => 'not_automated',
        'attachments' => [
            UploadedFile::fake()->image('mockup.png'),
        ],
    ]);

    $testCase = TestCaseModel::first();
    $response->assertRedirect(route('test-cases.show', [$this->project, $testSuite, $testCase]));
    expect($testCase->attachments)->toHaveCount(1);
    Storage::disk('public')->assertExists($testCase->attachments[0]->stored_path);
});

test('test case can be updated with new attachments', function () {
    $testSuite = TestSuite::factory()->create(['project_id' => $this->project->id]);
    $testCase = TestCaseModel::factory()->create(['test_suite_id' => $testSuite->id]);

    $response = $this->put(route('test-cases.update', [$this->project, $testSuite, $testCase]), [
        'title' => 'Updated Test Case',
        'priority' => 'high',
        'severity' => 'critical',
        'type' => 'regression',
        'automation_status' => 'automated',
        'attachments' => [
            UploadedFile::fake()->create('spec.pdf', 500, 'application/pdf'),
        ],
    ]);

    $response->assertRedirect(route('test-cases.show', [$this->project, $testSuite, $testCase]));
    expect($testCase->fresh()->attachments)->toHaveCount(1);
});

test('test case attachment can be deleted', function () {
    $testSuite = TestSuite::factory()->create(['project_id' => $this->project->id]);
    $testCase = TestCaseModel::factory()->create(['test_suite_id' => $testSuite->id]);

    Storage::disk('public')->put('attachments/test-cases/doc.pdf', 'content');

    $attachment = $testCase->attachments()->create([
        'original_filename' => 'doc.pdf',
        'stored_path' => 'attachments/test-cases/doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 2048,
    ]);

    $response = $this->delete(route('test-cases.destroy-attachment', [$this->project, $testSuite, $testCase, $attachment]));
    $response->assertRedirect();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing('attachments/test-cases/doc.pdf');
});

test('deleting test case cleans up attachment files', function () {
    $testSuite = TestSuite::factory()->create(['project_id' => $this->project->id]);
    $testCase = TestCaseModel::factory()->create(['test_suite_id' => $testSuite->id]);

    Storage::disk('public')->put('attachments/test-cases/doc.pdf', 'content');

    $testCase->attachments()->create([
        'original_filename' => 'doc.pdf',
        'stored_path' => 'attachments/test-cases/doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 2048,
    ]);

    $this->delete(route('test-cases.destroy', [$this->project, $testSuite, $testCase]));
    Storage::disk('public')->assertMissing('attachments/test-cases/doc.pdf');
});

test('attachment url attribute is generated correctly', function () {
    $attachment = new Attachment([
        'original_filename' => 'test.png',
        'stored_path' => 'attachments/bugreports/test.png',
        'mime_type' => 'image/png',
        'size' => 1024,
    ]);

    expect($attachment->url)->toContain('attachments/bugreports/test.png');
});
