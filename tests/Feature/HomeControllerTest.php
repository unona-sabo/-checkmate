<?php

use App\Models\User;

test('dashboard page renders for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('dashboard page requires authentication', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});
