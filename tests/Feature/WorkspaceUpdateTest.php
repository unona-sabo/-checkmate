<?php

use App\Models\Workspace;

test('owner can rename a workspace', function () {
    [$owner, $workspace] = createUserWithWorkspace();

    $response = $this->actingAs($owner)
        ->put(route('workspaces.update', $workspace), ['name' => 'Renamed Workspace']);

    $response->assertSessionHasNoErrors()->assertRedirect();

    expect($workspace->fresh()->name)->toBe('Renamed Workspace');
});

test('renaming a workspace regenerates its slug', function () {
    [$owner, $workspace] = createUserWithWorkspace();
    $workspace->update(['name' => 'Original Name', 'slug' => 'original-name']);

    $this->actingAs($owner)
        ->put(route('workspaces.update', $workspace), ['name' => 'Brand New Name'])
        ->assertRedirect();

    expect($workspace->fresh()->slug)->toBe('brand-new-name');
});

test('renaming a workspace redirects to the fresh url so the page keeps working', function () {
    [$owner, $workspace] = createUserWithWorkspace();
    $workspace->update(['name' => 'Original Name', 'slug' => 'original-name']);

    $response = $this->actingAs($owner)
        ->put(route('workspaces.update', $workspace), ['name' => 'Brand New Name']);

    $fresh = $workspace->fresh();
    $response->assertRedirect(route('workspaces.show', $fresh));

    $this->actingAs($owner)
        ->get(route('workspaces.show', $fresh))
        ->assertOk();
});

test('an old link with a stale slug still resolves because only the id is used for lookup', function () {
    [$owner, $workspace] = createUserWithWorkspace();
    $workspace->update(['name' => 'Original Name', 'slug' => 'original-name']);

    $this->actingAs($owner)
        ->put(route('workspaces.update', $workspace), ['name' => 'Brand New Name'])
        ->assertRedirect();

    // The old bookmarked URL used the pre-rename slug — it must still work.
    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->id}-original-name/settings")
        ->assertOk();
});

test('renaming to the same name does not change the slug', function () {
    [$owner, $workspace] = createUserWithWorkspace();
    $workspace->update(['name' => 'Same Name', 'slug' => 'same-name']);

    $this->actingAs($owner)
        ->put(route('workspaces.update', $workspace), ['name' => 'Same Name'])
        ->assertRedirect();

    expect($workspace->fresh()->slug)->toBe('same-name');
});

test('renaming to a name that collides with another workspace gets a unique slug', function () {
    [$owner, $workspace] = createUserWithWorkspace();
    Workspace::factory()->create(['name' => 'Taken Name', 'slug' => 'taken-name']);

    $this->actingAs($owner)
        ->put(route('workspaces.update', $workspace), ['name' => 'Taken Name'])
        ->assertRedirect();

    expect($workspace->fresh()->slug)->toBe('taken-name-1');
});

test('workspace name rejects non-latin characters', function () {
    [$owner, $workspace] = createUserWithWorkspace();

    $this->actingAs($owner)
        ->put(route('workspaces.update', $workspace), ['name' => 'Отдел тестирования'])
        ->assertSessionHasErrors('name');
});

test('workspace name accepts latin letters, numbers, and standard punctuation', function () {
    [$owner, $workspace] = createUserWithWorkspace();

    $this->actingAs($owner)
        ->put(route('workspaces.update', $workspace), ['name' => "QA Team #2 - Bob's Group"])
        ->assertSessionHasNoErrors();
});

test('member cannot rename a workspace', function () {
    [$user, $workspace] = createUserWithWorkspace('member');

    $this->actingAs($user)
        ->put(route('workspaces.update', $workspace), ['name' => 'Hacked Name'])
        ->assertForbidden();

    expect($workspace->fresh()->name)->not->toBe('Hacked Name');
});
