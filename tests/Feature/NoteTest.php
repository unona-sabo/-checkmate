<?php

use App\Models\Documentation;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

test('index page renders with notes for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Note::factory()->count(3)->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('projects.notes.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Notes/Index')
        ->has('project')
        ->has('notes', 3)
    );
});

test('create page renders for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('projects.notes.create', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Notes/Create')
        ->has('project')
    );
});

test('store creates note with valid data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('projects.notes.store', $project), [
        'title' => 'Test Note',
        'content' => 'Some content here',
        'is_draft' => true,
    ]);

    $response->assertRedirect(route('projects.notes.index', $project));
    $this->assertDatabaseHas('notes', [
        'project_id' => $project->id,
        'title' => 'Test Note',
        'content' => 'Some content here',
        'is_draft' => true,
    ]);
});

test('show page renders for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $note = Note::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('projects.notes.show', [$project, $note]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Notes/Show')
        ->has('project')
        ->has('note')
    );
});

test('update modifies existing note', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $note = Note::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put(route('projects.notes.update', [$project, $note]), [
        'title' => 'Updated Title',
        'content' => 'Updated content',
        'is_draft' => false,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'title' => 'Updated Title',
        'content' => 'Updated content',
    ]);
});

test('destroy deletes note', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $note = Note::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->delete(route('projects.notes.destroy', [$project, $note]));

    $response->assertRedirect(route('projects.notes.index', $project));
    $this->assertDatabaseMissing('notes', ['id' => $note->id]);
});

test('publish appends note content to documentation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $doc = Documentation::factory()->create([
        'project_id' => $project->id,
        'content' => 'Existing content',
    ]);
    $note = Note::factory()->create([
        'project_id' => $project->id,
        'documentation_id' => $doc->id,
        'content' => 'Note content',
        'is_draft' => true,
    ]);

    $response = $this->actingAs($user)->post(route('projects.notes.publish', [$project, $note]));

    $response->assertRedirect();
    $this->assertDatabaseHas('documentations', [
        'id' => $doc->id,
        'content' => "Existing content\n\nNote content",
    ]);
    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'is_draft' => false,
    ]);
});

test('publish fails without documentation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $note = Note::factory()->create([
        'project_id' => $project->id,
        'documentation_id' => null,
    ]);

    $response = $this->actingAs($user)->post(route('projects.notes.publish', [$project, $note]));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('viewer can view notes index', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    Note::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->get(route('projects.notes.index', $project))
        ->assertOk();
});

test('viewer cannot store note', function () {
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

    $this->actingAs($viewer)
        ->post(route('projects.notes.store', $project), [
            'title' => 'Viewer Note',
            'content' => 'Should not work',
        ])
        ->assertForbidden();
});

test('viewer cannot update note', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $note = Note::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->put(route('projects.notes.update', [$project, $note]), [
            'title' => 'Updated',
            'content' => 'Should not work',
        ])
        ->assertForbidden();
});

test('viewer cannot delete note', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $note = Note::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->delete(route('projects.notes.destroy', [$project, $note]))
        ->assertForbidden();
});
