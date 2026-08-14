<?php

test('sidebar categories are all visible by default', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $response = $this->actingAs($user)->get(route('workspaces.show', $workspace));

    $response->assertOk();
    expect($workspace->fresh()->hidden_sidebar_categories)->toBeNull();
});

test('sidebar categories can be hidden', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $response = $this->actingAs($user)->put(route('workspaces.sidebar.update', $workspace), [
        'hidden_categories' => ['bugreports', 'automation'],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    expect($workspace->fresh()->hidden_sidebar_categories)->toBe(['bugreports', 'automation']);
});

test('sidebar categories validation rejects an unknown category', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $response = $this->actingAs($user)->put(route('workspaces.sidebar.update', $workspace), [
        'hidden_categories' => ['not-a-real-category'],
    ]);

    $response->assertSessionHasErrors(['hidden_categories.0']);
});

test('member cannot update sidebar categories', function () {
    [$user, $workspace] = createUserWithWorkspace('member');

    $response = $this->actingAs($user)->put(route('workspaces.sidebar.update', $workspace), [
        'hidden_categories' => ['bugreports'],
    ]);

    $response->assertForbidden();
});

test('viewer cannot update sidebar categories', function () {
    [$user, $workspace] = createUserWithWorkspace('viewer');

    $response = $this->actingAs($user)->put(route('workspaces.sidebar.update', $workspace), [
        'hidden_categories' => ['bugreports'],
    ]);

    $response->assertForbidden();
});

test('two workspaces have independent sidebar category settings', function () {
    [$userA, $workspaceA] = createUserWithWorkspace();
    [$userB, $workspaceB] = createUserWithWorkspace();

    $this->actingAs($userA)->put(route('workspaces.sidebar.update', $workspaceA), [
        'hidden_categories' => ['bugreports'],
    ]);

    $this->actingAs($userB)->put(route('workspaces.sidebar.update', $workspaceB), [
        'hidden_categories' => ['notes', 'design'],
    ]);

    expect($workspaceA->fresh()->hidden_sidebar_categories)->toBe(['bugreports']);
    expect($workspaceB->fresh()->hidden_sidebar_categories)->toBe(['notes', 'design']);
});

test('hidden sidebar categories are shared with the frontend via currentWorkspace', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $workspace->update(['hidden_sidebar_categories' => ['releases']]);

    $response = $this->actingAs($user)->get(route('projects.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('currentWorkspace.hidden_sidebar_categories', ['releases'])
    );
});
