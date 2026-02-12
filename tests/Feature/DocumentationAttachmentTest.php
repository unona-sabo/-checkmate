<?php

use App\Models\Attachment;
use App\Models\Documentation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('documentation can be created with attachments', function () {
    $response = $this->post(route('documentations.store', $this->project), [
        'title' => 'Test Doc',
        'content' => '<p>Some content</p>',
        'attachments' => [
            UploadedFile::fake()->image('screenshot.png', 800, 600),
            UploadedFile::fake()->create('spec.pdf', 500, 'application/pdf'),
        ],
    ]);

    $documentation = Documentation::first();
    $response->assertRedirect(route('documentations.show', [$this->project, $documentation]));
    expect($documentation->attachments)->toHaveCount(2);
    Storage::disk('public')->assertExists($documentation->attachments[0]->stored_path);
    Storage::disk('public')->assertExists($documentation->attachments[1]->stored_path);
});

test('documentation can be updated with new attachments', function () {
    $documentation = Documentation::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->put(route('documentations.update', [$this->project, $documentation]), [
        'title' => 'Updated Doc',
        'content' => '<p>Updated content</p>',
        'attachments' => [
            UploadedFile::fake()->image('new-screenshot.jpg'),
        ],
    ]);

    $response->assertRedirect(route('documentations.show', [$this->project, $documentation]));
    expect($documentation->fresh()->attachments)->toHaveCount(1);
});

test('documentation show page loads attachments', function () {
    $documentation = Documentation::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $documentation->attachments()->create([
        'original_filename' => 'test.png',
        'stored_path' => 'attachments/documentations/test.png',
        'mime_type' => 'image/png',
        'size' => 1024,
    ]);

    $response = $this->get(route('documentations.show', [$this->project, $documentation]));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documentations/Show')
        ->has('documentation.attachments', 1)
    );
});

test('documentation edit page loads attachments', function () {
    $documentation = Documentation::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $documentation->attachments()->create([
        'original_filename' => 'doc.pdf',
        'stored_path' => 'attachments/documentations/doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 2048,
    ]);

    $response = $this->get(route('documentations.edit', [$this->project, $documentation]));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documentations/Edit')
        ->has('documentation.attachments', 1)
    );
});

test('documentation attachment can be deleted', function () {
    $documentation = Documentation::factory()->create([
        'project_id' => $this->project->id,
    ]);

    Storage::disk('public')->put('attachments/documentations/test.png', 'content');

    $attachment = $documentation->attachments()->create([
        'original_filename' => 'test.png',
        'stored_path' => 'attachments/documentations/test.png',
        'mime_type' => 'image/png',
        'size' => 1024,
    ]);

    $response = $this->delete(route('documentations.destroy-attachment', [$this->project, $documentation, $attachment]));
    $response->assertRedirect();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing('attachments/documentations/test.png');
});

test('deleting documentation cleans up attachment files', function () {
    $documentation = Documentation::factory()->create([
        'project_id' => $this->project->id,
    ]);

    Storage::disk('public')->put('attachments/documentations/test.png', 'content');

    $documentation->attachments()->create([
        'original_filename' => 'test.png',
        'stored_path' => 'attachments/documentations/test.png',
        'mime_type' => 'image/png',
        'size' => 1024,
    ]);

    $this->delete(route('documentations.destroy', [$this->project, $documentation]));
    Storage::disk('public')->assertMissing('attachments/documentations/test.png');
});

test('documentation image can be uploaded via upload-image endpoint', function () {
    $documentation = Documentation::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->post(
        route('documentations.upload-image', [$this->project, $documentation]),
        ['image' => UploadedFile::fake()->image('screenshot.png', 800, 600)]
    );

    $response->assertOk();
    $response->assertJsonStructure(['url']);
    expect($documentation->fresh()->attachments)->toHaveCount(1);
});

test('documentation image can be uploaded via upload-new-image endpoint', function () {
    $response = $this->post(
        route('documentations.upload-new-image', $this->project),
        ['image' => UploadedFile::fake()->image('pasted.png', 400, 300)]
    );

    $response->assertOk();
    $response->assertJsonStructure(['url']);
});
