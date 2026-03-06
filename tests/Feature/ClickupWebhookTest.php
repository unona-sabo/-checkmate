<?php

use App\Models\Bugreport;
use App\Models\ClickupSetting;
use App\Models\Project;

test('webhook rejects requests without valid signature', function () {
    $response = $this->postJson('/api/webhooks/clickup', [
        'event' => 'taskStatusUpdated',
    ]);

    $response->assertStatus(401);
});

test('webhook updates bugreport status on taskStatusUpdated', function () {
    $settings = ClickupSetting::current();
    $settings->update([
        'webhook_secret' => 'test-secret',
        'status_mapping' => [
            'to_do' => 'to do',
            'in_progress' => 'in progress',
            'in_review' => 'in review',
            'done' => 'done',
        ],
    ]);

    $project = Project::factory()->create();
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    $payload = json_encode([
        'event' => 'taskStatusUpdated',
        'task_id' => 'abc123',
        'history_items' => [
            ['after' => ['status' => 'done']],
        ],
    ]);

    $signature = hash_hmac('sha256', $payload, 'test-secret');

    $response = $this->postJson('/api/webhooks/clickup', json_decode($payload, true), [
        'X-Signature' => $signature,
    ]);

    $response->assertOk();

    $bugreport->refresh();
    expect($bugreport->status)->toBe('done');
});

test('webhook ignores unknown task ids', function () {
    $settings = ClickupSetting::current();
    $settings->update([
        'webhook_secret' => 'test-secret',
        'status_mapping' => ['to_do' => 'to do'],
    ]);

    $payload = json_encode([
        'event' => 'taskStatusUpdated',
        'task_id' => 'nonexistent',
        'history_items' => [
            ['after' => ['status' => 'to do']],
        ],
    ]);

    $signature = hash_hmac('sha256', $payload, 'test-secret');

    $response = $this->postJson('/api/webhooks/clickup', json_decode($payload, true), [
        'X-Signature' => $signature,
    ]);

    $response->assertOk();
});
