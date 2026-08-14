<?php

use App\Models\GrafanaSetting;
use App\Models\Workspace;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('grafana settings page renders for authenticated user', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $this->actingAs($user)
        ->get(route('workspaces.grafana.show', $workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Workspaces/Grafana')
            ->has('settings')
            ->where('settings.has_token', false)
        );
});

test('grafana settings page requires authentication', function () {
    $workspace = Workspace::factory()->create();

    $this->get(route('workspaces.grafana.show', $workspace))
        ->assertRedirect('/login');
});

test('member cannot view grafana settings page', function () {
    [$user, $workspace] = createUserWithWorkspace('member');

    $this->actingAs($user)
        ->get(route('workspaces.grafana.show', $workspace))
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('error');
});

test('viewer cannot view grafana settings page', function () {
    [$user, $workspace] = createUserWithWorkspace('viewer');

    $this->actingAs($user)
        ->get(route('workspaces.grafana.show', $workspace))
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('error');
});

test('grafana settings can be saved', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $this->actingAs($user)
        ->put(route('workspaces.grafana.update', $workspace), [
            'api_token' => 'glsa_test_token_123',
            'base_url' => 'https://logging.example.io',
            'datasource_id' => '1',
            'log_path' => '/home/app/storage/logs/payouts-{YYYY-MM-DD}.log',
        ])
        ->assertRedirect();

    $settings = GrafanaSetting::forWorkspace($workspace);
    expect($settings->base_url)->toBe('https://logging.example.io');
    expect($settings->datasource_id)->toBe('1');
    expect($settings->log_path)->toBe('/home/app/storage/logs/payouts-{YYYY-MM-DD}.log');
    expect($settings->api_token)->toBe('glsa_test_token_123');
});

test('grafana settings validates required fields', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $this->actingAs($user)
        ->putJson(route('workspaces.grafana.update', $workspace), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['base_url', 'datasource_id']);
});

test('grafana settings preserves token when not provided', function () {
    [$user, $workspace] = createUserWithWorkspace();

    GrafanaSetting::forWorkspace($workspace)->update([
        'api_token' => 'glsa_original_token',
        'base_url' => 'https://old.example.io',
        'datasource_id' => '1',
    ]);

    $this->actingAs($user)
        ->put(route('workspaces.grafana.update', $workspace), [
            'api_token' => '',
            'base_url' => 'https://new.example.io',
            'datasource_id' => '2',
        ])
        ->assertRedirect();

    $settings = GrafanaSetting::forWorkspace($workspace)->fresh();
    expect($settings->api_token)->toBe('glsa_original_token');
    expect($settings->base_url)->toBe('https://new.example.io');
    expect($settings->datasource_id)->toBe('2');
});

test('grafana settings validates base_url is a valid url', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $this->actingAs($user)
        ->putJson(route('workspaces.grafana.update', $workspace), [
            'api_token' => 'glsa_test',
            'base_url' => 'not-a-url',
            'datasource_id' => '1',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['base_url']);
});

test('grafana settings shows has_token true after saving', function () {
    [$user, $workspace] = createUserWithWorkspace();

    GrafanaSetting::forWorkspace($workspace)->update([
        'api_token' => 'glsa_existing_token',
        'base_url' => 'https://logging.example.io',
        'datasource_id' => '1',
    ]);

    $this->actingAs($user)
        ->get(route('workspaces.grafana.show', $workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('settings.has_token', true)
            ->where('settings.base_url', 'https://logging.example.io')
            ->where('settings.datasource_id', '1')
        );
});

test('member cannot update grafana settings', function () {
    [$user, $workspace] = createUserWithWorkspace('member');

    $this->actingAs($user)
        ->put(route('workspaces.grafana.update', $workspace), [
            'api_token' => 'glsa_test',
            'base_url' => 'https://logging.example.io',
            'datasource_id' => '1',
        ])
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('error');
});

test('two workspaces have independent grafana settings', function () {
    [$userA, $workspaceA] = createUserWithWorkspace();
    [$userB, $workspaceB] = createUserWithWorkspace();

    $this->actingAs($userA)->put(route('workspaces.grafana.update', $workspaceA), [
        'base_url' => 'https://workspace-a.example.io',
        'datasource_id' => 'a',
    ]);

    $this->actingAs($userB)->put(route('workspaces.grafana.update', $workspaceB), [
        'base_url' => 'https://workspace-b.example.io',
        'datasource_id' => 'b',
    ]);

    expect(GrafanaSetting::forWorkspace($workspaceA)->datasource_id)->toBe('a');
    expect(GrafanaSetting::forWorkspace($workspaceB)->datasource_id)->toBe('b');
});

test('grafana test connection requires base url', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $this->actingAs($user)
        ->postJson(route('workspaces.grafana.test-connection', $workspace))
        ->assertStatus(422)
        ->assertJson(['error' => 'Grafana Base URL is not configured.']);
});

test('grafana test connection reports dns failure for unresolvable host', function () {
    [$user, $workspace] = createUserWithWorkspace();

    GrafanaSetting::forWorkspace($workspace)->update([
        'base_url' => 'https://this-domain-does-not-exist.invalid',
    ]);

    $this->actingAs($user)
        ->postJson(route('workspaces.grafana.test-connection', $workspace))
        ->assertOk()
        ->assertJson([
            'dns' => [
                'host' => 'this-domain-does-not-exist.invalid',
                'resolved' => false,
                'ip' => null,
            ],
        ]);
});

test('grafana test connection reports reachable when server responds', function () {
    Http::fake([
        'logging.example.io/*' => Http::response(['status' => 'ok'], 200),
    ]);

    [$user, $workspace] = createUserWithWorkspace();

    GrafanaSetting::forWorkspace($workspace)->update([
        'base_url' => 'https://logging.example.io',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('workspaces.grafana.test-connection', $workspace))
        ->assertOk();

    $response->assertJsonPath('connection.reachable', true);
    $response->assertJsonPath('connection.status', 200);
});

test('grafana test connection reports connection failure message', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 6: Could not resolve host');
    });

    [$user, $workspace] = createUserWithWorkspace();

    GrafanaSetting::forWorkspace($workspace)->update([
        'base_url' => 'https://logging.example.io',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('workspaces.grafana.test-connection', $workspace))
        ->assertOk();

    $response->assertJsonPath('connection.reachable', false);
    expect($response->json('connection.message'))->toContain('Could not resolve host');
});

test('grafana test connection with manual ip bypasses dns via curl resolve', function () {
    Http::fake([
        'logging.example.io/*' => Http::response(['status' => 'ok'], 200),
    ]);

    [$user, $workspace] = createUserWithWorkspace();

    GrafanaSetting::forWorkspace($workspace)->update([
        'base_url' => 'https://logging.example.io',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('workspaces.grafana.test-connection', $workspace), ['ip' => '10.1.1.105'])
        ->assertOk();

    $response->assertJsonPath('ip_connection.ip', '10.1.1.105');
    $response->assertJsonPath('ip_connection.reachable', true);
});

test('grafana test connection omits ip_connection when no ip provided', function () {
    Http::fake([
        'logging.example.io/*' => Http::response(['status' => 'ok'], 200),
    ]);

    [$user, $workspace] = createUserWithWorkspace();

    GrafanaSetting::forWorkspace($workspace)->update([
        'base_url' => 'https://logging.example.io',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('workspaces.grafana.test-connection', $workspace))
        ->assertOk();

    $response->assertJsonPath('ip_connection', null);
});
