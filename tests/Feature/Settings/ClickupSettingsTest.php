<?php

use App\Models\ClickupSetting;
use App\Models\User;

test('clickup settings page is displayed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('clickup.show'));

    $response->assertOk();
});

test('clickup settings page requires authentication', function () {
    $response = $this->get(route('clickup.show'));

    $response->assertRedirect(route('login'));
});

test('clickup settings can be saved', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('clickup.update'), [
        'api_token' => 'pk_test_token_123',
        'list_id' => '901234567890',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $settings = ClickupSetting::current();
    expect($settings->api_token)->toBe('pk_test_token_123');
    expect($settings->list_id)->toBe('901234567890');
    expect($settings->isConfigured())->toBeTrue();
});

test('clickup settings validation requires api token and list id', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('clickup.update'), [
        'api_token' => '',
        'list_id' => '',
    ]);

    $response->assertSessionHasErrors(['api_token', 'list_id']);
});

test('clickup status mapping can be saved', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('clickup.status-mapping'), [
        'status_mapping' => [
            'to_do' => 'to do',
            'in_progress' => 'in progress',
            'in_review' => 'in review',
            'needs_changes' => 'needs changes',
            'cancelled' => 'cancelled',
            'done' => 'done',
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $settings = ClickupSetting::current();
    expect($settings->status_mapping)->toHaveKey('to_do', 'to do');
    expect($settings->status_mapping)->toHaveKey('done', 'done');
});

test('fetch statuses returns error when not configured', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('clickup.fetch-statuses'));

    $response->assertStatus(422)->assertJsonPath('error', 'ClickUp is not configured. Save your API token and List ID first.');
});
