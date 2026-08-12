<?php

use App\Models\ClickupSetting;
use Illuminate\Support\Facades\Http;
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

test('member cannot view clickup settings page', function () {
    [$user] = createUserWithWorkspace('member');

    $response = $this->actingAs($user)->get(route('workspaces.clickup.show'));

    $response->assertForbidden();
});

test('viewer cannot view clickup settings page', function () {
    [$user] = createUserWithWorkspace('viewer');

    $response = $this->actingAs($user)->get(route('workspaces.clickup.show'));

    $response->assertForbidden();
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

test('register webhook deletes every existing webhook pointing at our endpoint, not just the tracked one', function () {
    URL::forceRootUrl('https://myapp.io');

    Http::fake([
        'api.clickup.com/api/v2/team' => Http::response(['teams' => [['id' => '9003144822']]]),
        // Same URL is hit twice for different purposes — GET to list existing
        // webhooks (listWebhooks), then POST to create the new one
        // (registerWebhook) — Http::fake matches by URL only, so a sequence
        // is required to answer each call correctly in order.
        'api.clickup.com/api/v2/team/9003144822/webhook' => Http::sequence()
            ->push(['webhooks' => [
                ['id' => 'orphan-1', 'endpoint' => 'https://myapp.io/api/webhooks/clickup/1'],
                ['id' => 'other-workspace', 'endpoint' => 'https://myapp.io/api/webhooks/clickup/999'],
            ]])
            ->push(['id' => 'fresh-webhook']),
        'api.clickup.com/api/v2/webhook/orphan-1' => Http::response([], 200),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
        'webhook_id' => 'stale-tracked-id',
        'webhook_secret' => 'old-secret',
    ]);

    $response = $this->actingAs($user)->post(route('workspaces.clickup.register-webhook'));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/webhook/orphan-1'));
    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/webhook/other-workspace'));

    $settings = ClickupSetting::forWorkspace($workspace);
    expect($settings->webhook_id)->toBe('fresh-webhook');
    expect($settings->webhook_secret)->not->toBe('old-secret');

    URL::forceRootUrl(config('app.url'));
});

test('webhook health returns an error when no webhook is registered', function () {
    [$user] = createUserWithWorkspace();

    $response = $this->actingAs($user)->getJson(route('workspaces.clickup.webhook-health'));

    $response->assertStatus(422)->assertJsonPath('error', 'No webhook has been registered for this workspace yet.');
});

test('webhook health reports the delivery status ClickUp has for the registered webhook', function () {
    Http::fake([
        'api.clickup.com/api/v2/team' => Http::response(['teams' => [['id' => '9003144822']]]),
        'api.clickup.com/api/v2/team/9003144822/webhook' => Http::response([
            'webhooks' => [
                [
                    'id' => 'webhook-1',
                    'endpoint' => 'https://checkmate.test/api/webhooks/clickup/1',
                    'events' => ['taskStatusUpdated'],
                    'health' => ['status' => 'active', 'fail_count' => 0],
                ],
            ],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
        'webhook_id' => 'webhook-1',
    ]);

    $response = $this->actingAs($user)->getJson(route('workspaces.clickup.webhook-health'));

    $response->assertOk();
    $response->assertJson([
        'endpoint' => 'https://checkmate.test/api/webhooks/clickup/1',
        'events' => ['taskStatusUpdated'],
        'health' => ['status' => 'active', 'fail_count' => 0],
        'team_id' => '9003144822',
    ]);
});

test('webhook health flags when the webhook no longer exists on ClickUp', function () {
    Http::fake([
        'api.clickup.com/api/v2/team' => Http::response(['teams' => [['id' => '9003144822']]]),
        'api.clickup.com/api/v2/team/9003144822/webhook' => Http::response(['webhooks' => []]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
        'webhook_id' => 'webhook-1',
    ]);

    $response = $this->actingAs($user)->getJson(route('workspaces.clickup.webhook-health'));

    $response->assertStatus(422);
    expect($response->json('error'))->toContain('no longer has a webhook with ID webhook-1');
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
