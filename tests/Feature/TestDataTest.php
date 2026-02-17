<?php

use App\Models\Project;
use App\Models\TestPaymentMethod;
use App\Models\TestUser;
use App\Models\User;
use App\Models\Workspace;

// ===== Index =====

test('index page renders with test users and payment methods', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestUser::factory()->count(3)->create(['project_id' => $project->id]);
    TestPaymentMethod::factory()->count(2)->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get(route('test-data.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('TestData/Index')
        ->has('project')
        ->has('testUsers', 3)
        ->has('testPaymentMethods', 2)
    );
});

// ===== Test Users CRUD =====

test('store creates test user with valid data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('test-data.users.store', $project), [
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => 'secret123',
        'role' => 'admin',
        'environment' => 'staging',
        'description' => 'Admin test account',
        'is_valid' => true,
        'tags' => ['staging', 'admin'],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_users', [
        'project_id' => $project->id,
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'role' => 'admin',
        'environment' => 'staging',
        'created_by' => $user->id,
    ]);
});

test('store validates required user fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('test-data.users.store', $project), [
        'name' => '',
        'email' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'email']);
});

test('store validates email format', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('test-data.users.store', $project), [
        'name' => 'Test',
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('email');
});

test('update modifies existing test user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $testUser = TestUser::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put(route('test-data.users.update', [$project, $testUser]), [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'role' => 'tester',
        'is_valid' => false,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_users', [
        'id' => $testUser->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'role' => 'tester',
        'is_valid' => false,
    ]);
});

test('destroy deletes test user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $testUser = TestUser::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->delete(route('test-data.users.destroy', [$project, $testUser]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('test_users', ['id' => $testUser->id]);
});

test('bulk destroy deletes multiple test users scoped to project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create(['user_id' => $user->id]);

    $users = TestUser::factory()->count(3)->create(['project_id' => $project->id]);
    $otherUser = TestUser::factory()->create(['project_id' => $otherProject->id]);

    $response = $this->actingAs($user)->delete(route('test-data.users.bulk-destroy', $project), [
        'ids' => [$users[0]->id, $users[1]->id, $otherUser->id],
    ]);

    $response->assertRedirect();

    // Only project-scoped users deleted
    $this->assertDatabaseMissing('test_users', ['id' => $users[0]->id]);
    $this->assertDatabaseMissing('test_users', ['id' => $users[1]->id]);
    $this->assertDatabaseHas('test_users', ['id' => $users[2]->id]);
    // Other project's user untouched
    $this->assertDatabaseHas('test_users', ['id' => $otherUser->id]);
});

// ===== Test Payment Methods CRUD =====

test('store creates payment method with valid data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('test-data.payments.store', $project), [
        'name' => 'Test Visa',
        'type' => 'card',
        'system' => 'Stripe',
        'credentials' => ['card_number' => '4242424242424242', 'expiry' => '12/28', 'cvv' => '123'],
        'environment' => 'production',
        'is_valid' => true,
        'description' => 'Test card for Stripe',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_payment_methods', [
        'project_id' => $project->id,
        'name' => 'Test Visa',
        'type' => 'card',
        'system' => 'Stripe',
        'environment' => 'production',
        'created_by' => $user->id,
    ]);
});

test('store validates required payment fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('test-data.payments.store', $project), [
        'name' => '',
        'type' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'type']);
});

test('store validates payment type enum', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('test-data.payments.store', $project), [
        'name' => 'Test',
        'type' => 'invalid_type',
    ]);

    $response->assertSessionHasErrors('type');
});

test('update modifies existing payment method', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $payment = TestPaymentMethod::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put(route('test-data.payments.update', [$project, $payment]), [
        'name' => 'Updated Card',
        'type' => 'bank',
        'system' => 'Chase',
        'is_valid' => false,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_payment_methods', [
        'id' => $payment->id,
        'name' => 'Updated Card',
        'type' => 'bank',
        'system' => 'Chase',
        'is_valid' => false,
    ]);
});

test('destroy deletes payment method', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $payment = TestPaymentMethod::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->delete(route('test-data.payments.destroy', [$project, $payment]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('test_payment_methods', ['id' => $payment->id]);
});

test('bulk destroy deletes multiple payment methods scoped to project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $otherProject = Project::factory()->create(['user_id' => $user->id]);

    $payments = TestPaymentMethod::factory()->count(3)->create(['project_id' => $project->id]);
    $otherPayment = TestPaymentMethod::factory()->create(['project_id' => $otherProject->id]);

    $response = $this->actingAs($user)->delete(route('test-data.payments.bulk-destroy', $project), [
        'ids' => [$payments[0]->id, $payments[1]->id, $otherPayment->id],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseMissing('test_payment_methods', ['id' => $payments[0]->id]);
    $this->assertDatabaseMissing('test_payment_methods', ['id' => $payments[1]->id]);
    $this->assertDatabaseHas('test_payment_methods', ['id' => $payments[2]->id]);
    $this->assertDatabaseHas('test_payment_methods', ['id' => $otherPayment->id]);
});

// ===== RBAC (Viewer Forbidden) =====

test('viewer cannot store test user', function () {
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
        ->post(route('test-data.users.store', $project), [
            'name' => 'Viewer User',
            'email' => 'viewer@example.com',
        ])
        ->assertForbidden();
});

test('viewer cannot store payment method', function () {
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
        ->post(route('test-data.payments.store', $project), [
            'name' => 'Viewer Card',
            'type' => 'card',
        ])
        ->assertForbidden();
});

test('viewer can view test data index', function () {
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
        ->get(route('test-data.index', $project))
        ->assertOk();
});

// ===== Encrypted Password =====

test('password is stored encrypted in database', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('test-data.users.store', $project), [
        'name' => 'Encrypted Test',
        'email' => 'encrypted@example.com',
        'password' => 'my-secret-password',
    ]);

    $testUser = TestUser::where('email', 'encrypted@example.com')->first();

    // The decrypted value should match
    expect($testUser->password)->toBe('my-secret-password');

    // The raw database value should NOT match (it's encrypted)
    expect($testUser->getRawOriginal('password'))->not->toBe('my-secret-password');
});

// ===== Encrypted Credentials =====

test('payment credentials are stored encrypted in database', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('test-data.payments.store', $project), [
        'name' => 'Encrypted Card',
        'type' => 'card',
        'credentials' => ['card_number' => '4242424242424242'],
    ]);

    $payment = TestPaymentMethod::where('name', 'Encrypted Card')->first();

    // The decrypted value should match
    expect($payment->credentials)->toBe(['card_number' => '4242424242424242']);

    // The raw database value should NOT match (it's encrypted)
    expect($payment->getRawOriginal('credentials'))->not->toBe('{"card_number":"4242424242424242"}');
});

// ===== Reorder =====

test('reorder users updates order values', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $users = TestUser::factory()->count(3)->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put(route('test-data.users.reorder', $project), [
        'ids' => [$users[2]->id, $users[0]->id, $users[1]->id],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_users', ['id' => $users[2]->id, 'order' => 0]);
    $this->assertDatabaseHas('test_users', ['id' => $users[0]->id, 'order' => 1]);
    $this->assertDatabaseHas('test_users', ['id' => $users[1]->id, 'order' => 2]);
});

test('reorder payments updates order values', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $payments = TestPaymentMethod::factory()->count(3)->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put(route('test-data.payments.reorder', $project), [
        'ids' => [$payments[2]->id, $payments[0]->id, $payments[1]->id],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('test_payment_methods', ['id' => $payments[2]->id, 'order' => 0]);
    $this->assertDatabaseHas('test_payment_methods', ['id' => $payments[0]->id, 'order' => 1]);
    $this->assertDatabaseHas('test_payment_methods', ['id' => $payments[1]->id, 'order' => 2]);
});

test('reorder validates ids required', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->put(route('test-data.users.reorder', $project), [
        'ids' => [],
    ]);

    $response->assertSessionHasErrors('ids');
});

test('viewer cannot reorder users', function () {
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
        ->put(route('test-data.users.reorder', $project), ['ids' => [1]])
        ->assertForbidden();
});

test('viewer cannot reorder payments', function () {
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
        ->put(route('test-data.payments.reorder', $project), ['ids' => [1]])
        ->assertForbidden();
});

test('index returns users in order column ascending', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $second = TestUser::factory()->create(['project_id' => $project->id, 'order' => 1, 'name' => 'Second']);
    $first = TestUser::factory()->create(['project_id' => $project->id, 'order' => 0, 'name' => 'First']);
    $third = TestUser::factory()->create(['project_id' => $project->id, 'order' => 2, 'name' => 'Third']);

    $response = $this->actingAs($user)->get(route('test-data.index', $project));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('TestData/Index')
        ->where('testUsers.0.name', 'First')
        ->where('testUsers.1.name', 'Second')
        ->where('testUsers.2.name', 'Third')
    );
});

test('store auto-assigns order as max plus one', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    TestUser::factory()->create(['project_id' => $project->id, 'order' => 5]);

    $this->actingAs($user)->post(route('test-data.users.store', $project), [
        'name' => 'New User',
        'email' => 'new@example.com',
    ]);

    $newUser = TestUser::where('email', 'new@example.com')->first();
    expect($newUser->order)->toBe(6);
});
