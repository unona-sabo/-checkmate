<?php

use App\Models\User;
use App\Models\Workspace;

test('registration creates a personal workspace for the user', function () {
    $response = $this->post('/register', [
        'name' => 'Test Person',
        'email' => 'test-ws@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test-ws@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->current_workspace_id)->not->toBeNull();

    $workspace = $user->currentWorkspace;
    expect($workspace->name)->toBe("Test Person's Workspace");
    expect($workspace->owner_id)->toBe($user->id);

    $member = $workspace->members()->where('users.id', $user->id)->first();
    expect($member)->not->toBeNull();
    expect($member->pivot->role)->toBe('owner');
});

test('each user gets a unique workspace slug', function () {
    $this->post('/register', [
        'name' => 'Same Name',
        'email' => 'same1@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->post('/logout');

    $this->post('/register', [
        'name' => 'Same Name',
        'email' => 'same2@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $slugs = Workspace::pluck('slug');
    expect($slugs->unique()->count())->toBe($slugs->count());
});
