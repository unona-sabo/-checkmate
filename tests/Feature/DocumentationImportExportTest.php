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

test('import into new top-level documentation creates JSON tree without existing parent', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $json = json_encode([
        'title' => 'Imported Root',
        'content' => '<p>root content</p>',
        'children' => [
            ['title' => 'Child A'],
        ],
    ]);
    $file = UploadedFile::fake()->createWithContent('doc.json', $json);

    $response = $this->actingAs($user)->post(
        route('documentations.import-new', $project),
        ['file' => $file]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success', '2 document(s) imported successfully.');

    $root = Documentation::where('project_id', $project->id)->where('title', 'Imported Root')->first();
    expect($root)->not->toBeNull();
    expect($root->parent_id)->toBeNull();

    $child = Documentation::where('parent_id', $root->id)->first();
    expect($child)->not->toBeNull();
    expect($child->title)->toBe('Child A');
});

test('import into new top-level documentation parses non-JSON file', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $file = UploadedFile::fake()->createWithContent('notes.txt', "First paragraph.\n\nSecond paragraph.");

    $response = $this->actingAs($user)->post(
        route('documentations.import-new', $project),
        ['file' => $file]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $imported = Documentation::where('project_id', $project->id)->where('title', 'notes')->first();
    expect($imported)->not->toBeNull();
    expect($imported->parent_id)->toBeNull();
});

test('import into new top-level documentation accepts a custom title override for JSON', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $json = json_encode([
        'title' => 'Original Title',
        'content' => '<p>root content</p>',
    ]);
    $file = UploadedFile::fake()->createWithContent('doc.json', $json);

    $response = $this->actingAs($user)->post(
        route('documentations.import-new', $project),
        ['file' => $file, 'title' => 'Custom Title']
    );

    $response->assertRedirect();

    $doc = Documentation::where('project_id', $project->id)->first();
    expect($doc->title)->toBe('Custom Title');
});

test('import into new top-level documentation accepts a custom title override for parsed files', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $file = UploadedFile::fake()->createWithContent('notes.txt', 'Some content.');

    $response = $this->actingAs($user)->post(
        route('documentations.import-new', $project),
        ['file' => $file, 'title' => 'Custom Title']
    );

    $response->assertRedirect();

    $doc = Documentation::where('project_id', $project->id)->first();
    expect($doc->title)->toBe('Custom Title');
});

test('viewer cannot import into new top-level documentation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $file = UploadedFile::fake()->createWithContent('doc.json', json_encode(['title' => 'Test']));

    $response = $this->actingAs($viewer)->post(
        route('documentations.import-new', $project),
        ['file' => $file]
    );

    $response->assertForbidden();
});

test('store note creates a new top-level documentation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(
        route('documentations.store-note', $project),
        [
            'title' => 'Quick Note',
            'content' => '<p>Line one</p><p>Line two</p>',
        ]
    );

    $documentation = Documentation::where('project_id', $project->id)->where('title', 'Quick Note')->first();
    expect($documentation)->not->toBeNull();
    expect($documentation->parent_id)->toBeNull();
    // The exact whitespace HTMLPurifier inserts between paragraphs varies by
    // platform (CRLF vs LF), so normalize before comparing.
    expect(str_replace("\r\n", "\n", $documentation->content))->toBe("<p>Line one</p>\n\n<p>Line two</p>");

    $response->assertRedirect(route('documentations.show', [$project, $documentation]));
    $response->assertSessionHas('success');
});

test('store note requires title and content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(
        route('documentations.store-note', $project),
        []
    );

    $response->assertSessionHasErrors(['title', 'content']);
});

test('viewer cannot store a note', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($viewer)->post(
        route('documentations.store-note', $project),
        ['title' => 'Test', 'content' => 'content']
    );

    $response->assertForbidden();
});
