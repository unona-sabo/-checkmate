<?php

use App\Models\ClickupSetting;
use Illuminate\Support\Facades\URL;

test('clickup settings page is displayed', function () {
    [$user] = createUserWithWorkspace();

    $response = $this->actingAs($user)->get(route('workspaces.clickup.show'));

    $response->assertOk();
});

test('clickup settings page requires authentication', function () {
    $response = $this->get(route('workspaces.clickup.show'));

    $response->assertRedirect(route('login'));
});

test('clickup settings can be saved', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $response = $this->actingAs($user)->put(route('workspaces.clickup.update'), [
        'api_token' => 'pk_test_token_123',
        'list_id' => '901234567890',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $settings = ClickupSetting::forWorkspace($workspace);
    expect($settings->api_token)->toBe('pk_test_token_123');
    expect($settings->list_id)->toBe('901234567890');
    expect($settings->isConfigured())->toBeTrue();
});

test('clickup settings validation requires api token and list id', function () {
    [$user] = createUserWithWorkspace();

    $response = $this->actingAs($user)->put(route('workspaces.clickup.update'), [
        'api_token' => '',
        'list_id' => '',
    ]);

    $response->assertSessionHasErrors(['api_token', 'list_id']);
});

test('clickup status mapping can be saved', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $response = $this->actingAs($user)->put(route('workspaces.clickup.status-mapping'), [
        'status_mapping' => [
            'triage' => 'triage',
            'to_do' => 'to do',
            'in_progress' => 'in progress',
            'blocked' => 'blocked',
            'in_review' => 'in review',
            'needs_changes' => 'needs changes',
            'cancelled' => 'cancelled',
            'done' => 'done',
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $settings = ClickupSetting::forWorkspace($workspace);
    expect($settings->status_mapping)->toHaveKey('to_do', 'to do');
    expect($settings->status_mapping)->toHaveKey('done', 'done');
});

test('fetch statuses returns error when not configured', function () {
    [$user] = createUserWithWorkspace();

    $response = $this->actingAs($user)->postJson(route('workspaces.clickup.fetch-statuses'));

    $response->assertStatus(422)->assertJsonPath('error', 'ClickUp is not configured. Save your API token and List ID first.');
});

test('member cannot update clickup settings', function () {
    [$user, $workspace] = createUserWithWorkspace('member');

    $response = $this->actingAs($user)->put(route('workspaces.clickup.update'), [
        'api_token' => 'pk_test_token_123',
        'list_id' => '901234567890',
    ]);

    $response->assertForbidden();
});

test('viewer cannot update clickup settings', function () {
    [$user, $workspace] = createUserWithWorkspace('viewer');

    $response = $this->actingAs($user)->put(route('workspaces.clickup.update'), [
        'api_token' => 'pk_test_token_123',
        'list_id' => '901234567890',
    ]);

    $response->assertForbidden();
});

test('register webhook rejects a local development domain up front', function () {
    URL::forceRootUrl('https://checkmate.test');

    [$user, $workspace] = createUserWithWorkspace();
    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
    ]);

    $response = $this->actingAs($user)->post(route('workspaces.clickup.register-webhook'));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(session('error'))->toContain("can't call back a local development URL");

    URL::forceRootUrl(config('app.url'));
});

test('two workspaces have independent clickup settings', function () {
    [$userA, $workspaceA] = createUserWithWorkspace();
    [$userB, $workspaceB] = createUserWithWorkspace();

    $this->actingAs($userA)->put(route('workspaces.clickup.update'), [
        'api_token' => 'pk_workspace_a',
        'list_id' => 'list_a',
    ]);

    $this->actingAs($userB)->put(route('workspaces.clickup.update'), [
        'api_token' => 'pk_workspace_b',
        'list_id' => 'list_b',
    ]);

    expect(ClickupSetting::forWorkspace($workspaceA)->list_id)->toBe('list_a');
    expect(ClickupSetting::forWorkspace($workspaceB)->list_id)->toBe('list_b');
});
