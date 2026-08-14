<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

test('unknown routes render the illustrated 404 page', function () {
    $response = $this->get('/this-route-does-not-exist');

    $response->assertNotFound();
    $response->assertInertia(fn ($page) => $page
        ->component('Error')
        ->where('status', 404)
    );
});

test('a forbidden action renders the illustrated 403 page instead of the framework default', function () {
    [$user, $workspace] = createUserWithWorkspace('member');

    $response = $this->actingAs($user)
        ->put(route('workspaces.update', $workspace), ['name' => 'Hacked Name']);

    $response->assertForbidden();
    $response->assertInertia(fn ($page) => $page
        ->component('Error')
        ->where('status', 403)
    );
});

test('a CSRF token mismatch redirects back with a friendly flash message instead of a raw error page', function () {
    Route::post('/test-419-route', function () {
        throw new TokenMismatchException('CSRF token mismatch.');
    })->middleware('web');

    $response = $this->from('/dashboard')->post('/test-419-route');

    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('error', 'Your session took a little too long. Please try again.');
});
