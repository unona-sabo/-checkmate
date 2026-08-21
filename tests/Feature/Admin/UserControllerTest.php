<?php

use App\Models\User;
use App\Models\Workspace;

test('non-admins cannot view the users list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('admins can view the users list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Users')
            ->has('users', 3)
        );
});

test('admins can block a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.block', $target))
        ->assertRedirect();

    expect($target->refresh()->isBlocked())->toBeTrue();
});

test('admins can unblock a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['blocked_at' => now()]);

    $this->actingAs($admin)
        ->post(route('admin.users.unblock', $target))
        ->assertRedirect();

    expect($target->refresh()->isBlocked())->toBeFalse();
});

test('admins cannot block themselves', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.users.block', $admin))
        ->assertForbidden();

    expect($admin->refresh()->isBlocked())->toBeFalse();
});

test('admins can delete a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target))
        ->assertRedirect();

    expect(User::find($target->id))->toBeNull();
});

test('admins cannot delete a user who owns a workspace', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    Workspace::factory()->create(['owner_id' => $target->id, 'name' => 'Acme Corp']);

    $response = $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('Acme Corp');
    expect(User::find($target->id))->not->toBeNull();
});

test('admins cannot delete themselves', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});

test('blocked users cannot log in', function () {
    $user = User::factory()->create(['blocked_at' => now()]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('blocking a logged-in user signs them out on their next request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $user->update(['blocked_at' => now()]);

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
