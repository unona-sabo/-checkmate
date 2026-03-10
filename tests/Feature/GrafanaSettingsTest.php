<?php

use App\Models\GrafanaSetting;
use App\Models\User;

test('grafana settings page renders for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/grafana')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Grafana')
            ->has('settings')
            ->where('settings.has_token', false)
        );
});

test('grafana settings page requires authentication', function () {
    $this->get('/settings/grafana')
        ->assertRedirect('/login');
});

test('grafana settings can be saved', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/settings/grafana', [
            'api_token' => 'glsa_test_token_123',
            'base_url' => 'https://logging.example.io',
            'datasource_id' => '1',
            'log_path' => '/home/app/storage/logs/payouts-{YYYY-MM-DD}.log',
        ])
        ->assertRedirect();

    $settings = GrafanaSetting::current();
    expect($settings->base_url)->toBe('https://logging.example.io');
    expect($settings->datasource_id)->toBe('1');
    expect($settings->log_path)->toBe('/home/app/storage/logs/payouts-{YYYY-MM-DD}.log');
    expect($settings->api_token)->toBe('glsa_test_token_123');
});

test('grafana settings validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/settings/grafana', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['base_url', 'datasource_id']);
});

test('grafana settings preserves token when not provided', function () {
    $user = User::factory()->create();

    GrafanaSetting::current()->update([
        'api_token' => 'glsa_original_token',
        'base_url' => 'https://old.example.io',
        'datasource_id' => '1',
    ]);

    $this->actingAs($user)
        ->put('/settings/grafana', [
            'api_token' => '',
            'base_url' => 'https://new.example.io',
            'datasource_id' => '2',
        ])
        ->assertRedirect();

    $settings = GrafanaSetting::current()->fresh();
    expect($settings->api_token)->toBe('glsa_original_token');
    expect($settings->base_url)->toBe('https://new.example.io');
    expect($settings->datasource_id)->toBe('2');
});

test('grafana settings validates base_url is a valid url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/settings/grafana', [
            'api_token' => 'glsa_test',
            'base_url' => 'not-a-url',
            'datasource_id' => '1',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['base_url']);
});

test('grafana settings shows has_token true after saving', function () {
    $user = User::factory()->create();

    GrafanaSetting::current()->update([
        'api_token' => 'glsa_existing_token',
        'base_url' => 'https://logging.example.io',
        'datasource_id' => '1',
    ]);

    $this->actingAs($user)
        ->get('/settings/grafana')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('settings.has_token', true)
            ->where('settings.base_url', 'https://logging.example.io')
            ->where('settings.datasource_id', '1')
        );
});
