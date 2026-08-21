<?php

use App\Jobs\SyncBugreportFromClickUp;
use App\Models\Bugreport;
use App\Models\ClickupSetting;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

test('webhook rejects requests without valid signature', function () {
    $workspace = Workspace::factory()->create();

    $response = $this->postJson("/api/webhooks/clickup/{$workspace->id}", [
        'event' => 'taskStatusUpdated',
    ]);

    $response->assertStatus(401);
});

test('webhook updates bugreport status on taskStatusUpdated', function () {
    Http::fake([
        'api.clickup.com/api/v2/task/abc123' => Http::response([
            'id' => 'abc123',
            'status' => ['status' => 'done'],
        ]),
    ]);

    $workspace = Workspace::factory()->create();
    $settings = ClickupSetting::forWorkspace($workspace);
    $settings->update([
        'api_token' => 'test-token',
        'list_id' => 'list-1',
        'webhook_secret' => 'test-secret',
        'status_mapping' => [
            'to_do' => 'to do',
            'in_progress' => 'in progress',
            'in_review' => 'in review',
            'done' => 'done',
        ],
    ]);

    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    // The webhook payload's `history_items` is intentionally NOT trusted for
    // the new status — ClickUp can batch unrelated field changes into the
    // same call, so the handler fetches the task's authoritative status
    // directly from the API instead of parsing the diff.
    $payload = json_encode([
        'event' => 'taskStatusUpdated',
        'task_id' => 'abc123',
        'history_items' => [
            ['after' => ['status' => 'in review']],
        ],
    ]);

    $signature = hash_hmac('sha256', $payload, 'test-secret');

    $response = $this->postJson("/api/webhooks/clickup/{$workspace->id}", json_decode($payload, true), [
        'X-Signature' => $signature,
    ]);

    $response->assertOk();

    $bugreport->refresh();
    expect($bugreport->status)->toBe('done');
});

test('webhook resolves the correct status even when it is not the first history item', function () {
    Http::fake([
        'api.clickup.com/api/v2/task/abc123' => Http::response([
            'id' => 'abc123',
            'status' => ['status' => 'in review'],
        ]),
    ]);

    $workspace = Workspace::factory()->create();
    $settings = ClickupSetting::forWorkspace($workspace);
    $settings->update([
        'api_token' => 'test-token',
        'list_id' => 'list-1',
        'webhook_secret' => 'test-secret',
        'status_mapping' => [
            'to_do' => 'to do',
            'in_review' => 'in review',
        ],
    ]);

    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    // ClickUp batched an assignee change ahead of the status change — a
    // naive `history_items.0` read would have missed the status entirely.
    $payload = json_encode([
        'event' => 'taskStatusUpdated',
        'task_id' => 'abc123',
        'history_items' => [
            ['field' => 'assignee_add', 'after' => ['id' => 42]],
            ['field' => 'status', 'after' => ['status' => 'in review']],
        ],
    ]);

    $signature = hash_hmac('sha256', $payload, 'test-secret');

    $this->postJson("/api/webhooks/clickup/{$workspace->id}", json_decode($payload, true), [
        'X-Signature' => $signature,
    ])->assertOk();

    expect($bugreport->refresh()->status)->toBe('in_review');
});

test('webhook does nothing when the integration is not configured', function () {
    $workspace = Workspace::factory()->create();
    $settings = ClickupSetting::forWorkspace($workspace);
    $settings->update([
        'webhook_secret' => 'test-secret',
        'status_mapping' => ['done' => 'done'],
    ]);

    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    $payload = json_encode([
        'event' => 'taskStatusUpdated',
        'task_id' => 'abc123',
        'history_items' => [['after' => ['status' => 'done']]],
    ]);

    $signature = hash_hmac('sha256', $payload, 'test-secret');

    $this->postJson("/api/webhooks/clickup/{$workspace->id}", json_decode($payload, true), [
        'X-Signature' => $signature,
    ])->assertOk();

    expect($bugreport->refresh()->status)->toBe('to_do');
});

test('webhook dispatches a queued job to sync status instead of calling clickup inline', function () {
    Bus::fake();
    Http::fake();

    $workspace = Workspace::factory()->create();
    $settings = ClickupSetting::forWorkspace($workspace);
    $settings->update([
        'api_token' => 'test-token',
        'list_id' => 'list-1',
        'webhook_secret' => 'test-secret',
        'status_mapping' => ['done' => 'done'],
    ]);

    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    $payload = json_encode([
        'event' => 'taskStatusUpdated',
        'task_id' => 'abc123',
        'webhook_id' => $settings->webhook_id,
        'history_items' => [['after' => ['status' => 'done']]],
    ]);
    $signature = hash_hmac('sha256', $payload, 'test-secret');

    $this->postJson("/api/webhooks/clickup/{$workspace->id}", json_decode($payload, true), [
        'X-Signature' => $signature,
    ])->assertOk();

    Bus::assertDispatched(
        SyncBugreportFromClickUp::class,
        fn ($job) => $job->bugreport->is($bugreport),
    );

    // No inline HTTP call to ClickUp's API should happen from the webhook
    // request itself — that's the whole point of queuing it.
    Http::assertNothingSent();
});

test('webhook ignores unknown task ids', function () {
    $workspace = Workspace::factory()->create();
    $settings = ClickupSetting::forWorkspace($workspace);
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

    $response = $this->postJson("/api/webhooks/clickup/{$workspace->id}", json_decode($payload, true), [
        'X-Signature' => $signature,
    ]);

    $response->assertOk();
});

test('webhook does not update a bugreport with a matching task id in a different workspace', function () {
    Http::fake([
        'api.clickup.com/api/v2/task/shared-id' => Http::response([
            'id' => 'shared-id',
            'status' => ['status' => 'done'],
        ]),
    ]);

    $workspaceA = Workspace::factory()->create();
    ClickupSetting::forWorkspace($workspaceA)->update([
        'webhook_secret' => 'secret-a',
        'status_mapping' => ['to_do' => 'to do', 'done' => 'done'],
    ]);

    $workspaceB = Workspace::factory()->create();
    ClickupSetting::forWorkspace($workspaceB)->update([
        'api_token' => 'test-token',
        'list_id' => 'list-1',
        'webhook_secret' => 'secret-b',
        'status_mapping' => ['to_do' => 'to do', 'done' => 'done'],
    ]);

    // Same clickup_task_id, but the bug report lives in workspace B's
    // project — a webhook delivered for workspace A must not touch it.
    $projectB = Project::factory()->create(['workspace_id' => $workspaceB->id]);
    $bugreportInB = Bugreport::factory()->create([
        'project_id' => $projectB->id,
        'status' => 'to_do',
        'clickup_task_id' => 'shared-id',
    ]);

    $payload = json_encode([
        'event' => 'taskStatusUpdated',
        'task_id' => 'shared-id',
    ]);

    $signature = hash_hmac('sha256', $payload, 'secret-a');

    $response = $this->postJson("/api/webhooks/clickup/{$workspaceA->id}", json_decode($payload, true), [
        'X-Signature' => $signature,
    ]);

    $response->assertOk();

    expect($bugreportInB->refresh()->status)->toBe('to_do');
});

test('webhook signature for one workspace is rejected on another workspace URL', function () {
    $workspaceA = Workspace::factory()->create();
    ClickupSetting::forWorkspace($workspaceA)->update(['webhook_secret' => 'secret-a']);

    $workspaceB = Workspace::factory()->create();
    ClickupSetting::forWorkspace($workspaceB)->update(['webhook_secret' => 'secret-b']);

    $payload = json_encode([
        'event' => 'taskStatusUpdated',
        'task_id' => 'abc123',
        'history_items' => [
            ['after' => ['status' => 'done']],
        ],
    ]);

    // Signed with workspace A's secret, but posted to workspace B's URL.
    $signature = hash_hmac('sha256', $payload, 'secret-a');

    $response = $this->postJson("/api/webhooks/clickup/{$workspaceB->id}", json_decode($payload, true), [
        'X-Signature' => $signature,
    ]);

    $response->assertStatus(401);
});
