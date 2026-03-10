<?php

use App\Models\Project;
use App\Models\User;

test('payout monitor index page renders for authenticated user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}/payout-monitor");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PayoutMonitor/Index')
        ->has('project')
        ->has('isConfigured')
    );
});

test('payout monitor index page requires authentication', function () {
    $project = Project::factory()->create();

    $this->get("/projects/{$project->id}/payout-monitor")
        ->assertRedirect('/login');
});

test('parse-log endpoint validates required input', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/projects/{$project->id}/payout-monitor/parse-log", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['raw_log']);
});

test('parse-log endpoint returns parsed payout data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $log = '[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.request {"payout_id":12345,"requestAmount":"100","requestCurrency":"USD"}';

    $response = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/payout-monitor/parse-log", ['raw_log' => $log]);

    $response->assertOk()
        ->assertJsonStructure([
            'payouts' => [['payout_id', 'status', 'events', 'categories', 'anomalies']],
            'summary' => ['total_payouts', 'total_events', 'errors', 'anomalies'],
        ]);

    expect($response->json('summary.total_payouts'))->toBe(1);
    expect($response->json('payouts.0.payout_id'))->toBe(12345);
});

test('fetch-latest returns error when grafana not configured', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/projects/{$project->id}/payout-monitor/fetch-latest")
        ->assertUnprocessable()
        ->assertJsonPath('error', 'Grafana is not configured. Go to Settings > Grafana to set up your API token.');
});
