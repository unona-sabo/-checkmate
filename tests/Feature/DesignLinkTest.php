<?php

use App\Models\DesignLink;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

test('index page renders with design links for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    DesignLink::factory()->count(3)->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('design-links.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Design/Index')
        ->has('project')
        ->has('designLinks', 3)
    );
});

test('store creates design link with valid data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('design-links.store', $project), [
        'title' => 'Main Figma File',
        'url' => 'https://figma.com/file/abc123',
        'icon' => 'figma',
        'color' => '#F24E1E',
        'description' => 'Primary design file',
        'category' => 'Figma',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('design_links', [
        'project_id' => $project->id,
        'title' => 'Main Figma File',
        'url' => 'https://figma.com/file/abc123',
        'icon' => 'figma',
        'color' => '#F24E1E',
        'description' => 'Primary design file',
        'category' => 'Figma',
        'created_by' => $user->id,
    ]);
});

test('store validates required fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('design-links.store', $project), [
        'title' => '',
        'url' => '',
    ]);

    $response->assertSessionHasErrors(['title', 'url']);
});

test('store rejects invalid URL format', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('design-links.store', $project), [
        'title' => 'Bad Link',
        'url' => 'not-a-url',
    ]);

    $response->assertSessionHasErrors('url');
});

test('update modifies existing design link', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $link = DesignLink::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put(route('design-links.update', [$project, $link]), [
        'title' => 'Updated Title',
        'url' => 'https://example.com/updated',
        'icon' => 'zeplin',
        'color' => '#FDBD39',
        'description' => 'Updated description',
        'category' => 'Mockups',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('design_links', [
        'id' => $link->id,
        'title' => 'Updated Title',
        'url' => 'https://example.com/updated',
        'icon' => 'zeplin',
    ]);
});

test('destroy deletes design link', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $link = DesignLink::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->delete(route('design-links.destroy', [$project, $link]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('design_links', ['id' => $link->id]);
});

test('viewer cannot store design link', function () {
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
        ->post(route('design-links.store', $project), [
            'title' => 'Viewer Link',
            'url' => 'https://example.com',
        ])
        ->assertForbidden();
});

test('viewer cannot update design link', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $link = DesignLink::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->put(route('design-links.update', [$project, $link]), [
            'title' => 'Updated',
            'url' => 'https://example.com',
        ])
        ->assertForbidden();
});

test('viewer cannot destroy design link', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $link = DesignLink::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($viewer)
        ->delete(route('design-links.destroy', [$project, $link]))
        ->assertForbidden();
});
