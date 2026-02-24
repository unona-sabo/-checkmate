<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('translate requires authentication', function () {
    $project = Project::factory()->create();

    $this->postJson(route('translate', $project), [
        'text' => 'Hello',
        'target_language' => 'uk',
    ])->assertUnauthorized();
});

test('translate validates required fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson(route('translate', $project), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['text', 'target_language']);
});

test('translate validates target language must be en or uk', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson(route('translate', $project), [
        'text' => 'Hello',
        'target_language' => 'fr',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['target_language']);
});

test('translate validates text max length', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson(route('translate', $project), [
        'text' => str_repeat('a', 10001),
        'target_language' => 'en',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['text']);
});

test('translate calls gemini api by default and returns translated text', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'Привіт']],
                ],
            ]],
        ]),
    ]);

    config(['services.ai.default_provider' => 'gemini']);
    config(['services.gemini.api_key' => 'test-key']);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson(route('translate', $project), [
        'text' => 'Hello',
        'target_language' => 'uk',
    ])->assertOk()
        ->assertJson(['translated_text' => 'Привіт']);
});

test('translate uses provider from request when specified', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'Hello']],
        ]),
    ]);

    config(['services.anthropic.api_key' => 'test-key']);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson(route('translate', $project), [
        'text' => 'Привіт',
        'target_language' => 'en',
        'provider' => 'claude',
    ])->assertOk()
        ->assertJson(['translated_text' => 'Hello']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic.com'));
});

test('translate rejects invalid provider', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson(route('translate', $project), [
        'text' => 'Hello',
        'target_language' => 'uk',
        'provider' => 'invalid',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['provider']);
});

test('translate returns 500 when api fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Bad request']], 400),
    ]);

    config(['services.ai.default_provider' => 'gemini']);
    config(['services.gemini.api_key' => 'test-key']);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson(route('translate', $project), [
        'text' => 'Hello',
        'target_language' => 'uk',
    ])->assertStatus(500)
        ->assertJson(['error' => 'Translation failed. Please try again.']);
});
