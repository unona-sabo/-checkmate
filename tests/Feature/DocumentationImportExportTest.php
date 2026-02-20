<?php

use App\Models\Documentation;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;

test('export documentation as JSON', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $doc = Documentation::factory()->create([
        'project_id' => $project->id,
        'title' => 'Test Doc',
        'content' => '<p>Some content</p>',
        'category' => 'Guide',
    ]);

    $response = $this->actingAs($user)->get(
        route('documentations.export', [$project, $doc])
    );

    $response->assertOk();
    $response->assertHeader('content-type', 'application/json; charset=UTF-8');

    $data = json_decode($response->streamedContent(), true);
    expect($data['title'])->toBe('Test Doc');
    expect($data['content'])->toBe('<p>Some content</p>');
    expect($data['category'])->toBe('Guide');
});

test('export includes children recursively', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $parent = Documentation::factory()->create([
        'project_id' => $project->id,
        'title' => 'Parent',
    ]);
    $child = Documentation::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parent->id,
        'title' => 'Child',
    ]);
    Documentation::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $child->id,
        'title' => 'Grandchild',
    ]);

    $response = $this->actingAs($user)->get(
        route('documentations.export', [$project, $parent])
    );

    $data = json_decode($response->streamedContent(), true);
    expect($data['title'])->toBe('Parent');
    expect($data['children'])->toHaveCount(1);
    expect($data['children'][0]['title'])->toBe('Child');
    expect($data['children'][0]['children'])->toHaveCount(1);
    expect($data['children'][0]['children'][0]['title'])->toBe('Grandchild');
});

test('import documentation from JSON file', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $parent = Documentation::factory()->create([
        'project_id' => $project->id,
        'title' => 'Parent',
    ]);

    $json = json_encode([
        'title' => 'Imported Doc',
        'content' => '<p>Imported content</p>',
        'category' => 'Imported',
        'children' => [
            [
                'title' => 'Imported Child',
                'content' => '<p>Child content</p>',
                'category' => null,
            ],
        ],
    ]);

    $file = UploadedFile::fake()->createWithContent('doc.json', $json);

    $response = $this->actingAs($user)->post(
        route('documentations.import', [$project, $parent]),
        ['file' => $file]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $imported = Documentation::where('parent_id', $parent->id)
        ->where('title', 'Imported Doc')
        ->first();

    expect($imported)->not->toBeNull();
    expect($imported->content)->toBe('<p>Imported content</p>');
    expect($imported->category)->toBe('Imported');

    $importedChild = Documentation::where('parent_id', $imported->id)->first();
    expect($importedChild)->not->toBeNull();
    expect($importedChild->title)->toBe('Imported Child');
});

test('import TXT file creates documentation page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $parent = Documentation::factory()->create(['project_id' => $project->id]);

    $file = UploadedFile::fake()->createWithContent('notes.txt', "First paragraph.\n\nSecond paragraph.");

    $response = $this->actingAs($user)->post(
        route('documentations.import', [$project, $parent]),
        ['file' => $file]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $imported = Documentation::where('parent_id', $parent->id)
        ->where('title', 'notes')
        ->first();

    expect($imported)->not->toBeNull();
    expect($imported->content)->toContain('First paragraph.');
    expect($imported->content)->toContain('Second paragraph.');
});

test('import CSV file creates documentation with table', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $parent = Documentation::factory()->create(['project_id' => $project->id]);

    $csv = "Name,Status,Priority\nLogin Bug,Open,High\nUI Glitch,Closed,Low";
    $file = UploadedFile::fake()->createWithContent('data.csv', $csv);

    $response = $this->actingAs($user)->post(
        route('documentations.import', [$project, $parent]),
        ['file' => $file]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $imported = Documentation::where('parent_id', $parent->id)
        ->where('title', 'data')
        ->first();

    expect($imported)->not->toBeNull();
    expect($imported->content)->toContain('<table>');
    expect($imported->content)->toContain('Login Bug');
});

test('import rejects invalid JSON', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $doc = Documentation::factory()->create(['project_id' => $project->id]);

    $file = UploadedFile::fake()->createWithContent('bad.json', 'not json at all');

    $response = $this->actingAs($user)->post(
        route('documentations.import', [$project, $doc]),
        ['file' => $file]
    );

    $response->assertSessionHasErrors('file');
});

test('import rejects JSON without title field', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $doc = Documentation::factory()->create(['project_id' => $project->id]);

    $file = UploadedFile::fake()->createWithContent('bad.json', json_encode(['content' => 'no title']));

    $response = $this->actingAs($user)->post(
        route('documentations.import', [$project, $doc]),
        ['file' => $file]
    );

    $response->assertSessionHasErrors('file');
});

test('import requires file', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $doc = Documentation::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('documentations.import', [$project, $doc]),
        []
    );

    $response->assertSessionHasErrors('file');
});

test('viewer cannot import documentation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);
    $doc = Documentation::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $file = UploadedFile::fake()->createWithContent('doc.json', json_encode(['title' => 'Test']));

    $response = $this->actingAs($viewer)->post(
        route('documentations.import', [$project, $doc]),
        ['file' => $file]
    );

    $response->assertForbidden();
});
