<?php

use App\Jobs\ExportBugreportToClickUp;
use App\Jobs\SyncBugreportFromClickUp;
use App\Models\Bugreport;
use App\Models\ClickupSetting;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('export to clickup returns error when not configured', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('bugreports.export-clickup', [$project, $bugreport])
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('export to clickup returns error for a project without a workspace', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => null]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('bugreports.export-clickup', [$project, $bugreport])
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('export to clickup dispatches job when configured', function () {
    Queue::fake();

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
    ]);

    $response = $this->actingAs($user)->post(
        route('bugreports.export-clickup', [$project, $bugreport])
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    Queue::assertPushed(ExportBugreportToClickUp::class, function ($job) use ($bugreport) {
        return $job->bugreport->id === $bugreport->id;
    });
});

test('export to clickup prevents double export', function () {
    Queue::fake();

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'clickup_task_id' => 'existing_task_id',
    ]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
    ]);

    $response = $this->actingAs($user)->post(
        route('bugreports.export-clickup', [$project, $bugreport])
    );

    $response->assertRedirect();
    $response->assertSessionHas('info');

    Queue::assertNotPushed(ExportBugreportToClickUp::class);
});

test('link clickup extracts the task id from a pasted task url', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => null]);

    $response = $this->actingAs($user)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        ['clickup_link' => 'https://app.clickup.com/t/abc123?block=activity']
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($bugreport->refresh()->clickup_task_id)->toBe('abc123');
});

test('link clickup extracts the task id from an in-context team/task url', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => null]);

    // "/t/{teamId}/{taskId}" — the numeric team id must be skipped in favor
    // of the alphanumeric task id that follows it.
    $response = $this->actingAs($user)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        ['clickup_link' => 'https://app.clickup.com/t/9007725923/86eppvpp0']
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($bugreport->refresh()->clickup_task_id)->toBe('86eppvpp0');
});

test('link clickup extracts the task id from a real-world team/task url', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => null]);

    $response = $this->actingAs($user)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        ['clickup_link' => 'https://app.clickup.com/t/9003147722/869en83m9']
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($bugreport->refresh()->clickup_task_id)->toBe('869en83m9');
});

test('link clickup rejects a board/list url with no identifiable task', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => null]);

    // Board/list URLs share the "/t/{teamId}/..." shape with task
    // permalinks — the team id here must NOT be saved as a task id.
    $response = $this->actingAs($user)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        ['clickup_link' => 'https://app.clickup.com/t/9007725923/v/b/li/900701234567']
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($bugreport->refresh()->clickup_task_id)->toBeNull();
});

test('link clickup extracts the task id from a board url task query param', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => null]);

    $response = $this->actingAs($user)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        ['clickup_link' => 'https://app.clickup.com/9007725923/v/b/li/900701234567?task=86eppvpp0']
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($bugreport->refresh()->clickup_task_id)->toBe('86eppvpp0');
});

test('link clickup accepts a raw task id', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => null]);

    $response = $this->actingAs($user)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        ['clickup_link' => 'abc123']
    );

    $response->assertRedirect();
    expect($bugreport->refresh()->clickup_task_id)->toBe('abc123');
});

test('link clickup verifies the task exists when integration is configured', function () {
    Http::fake([
        'api.clickup.com/api/v2/task/abc123' => Http::response(
            ['err' => 'Task not found', 'ECODE' => 'ITEM_013'],
            404
        ),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => null]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
    ]);

    $response = $this->actingAs($user)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        ['clickup_link' => 'https://app.clickup.com/t/abc123']
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($bugreport->refresh()->clickup_task_id)->toBeNull();
});

test('link clickup still saves when verification fails for a reason other than not-found', function () {
    Http::fake([
        'api.clickup.com/api/v2/task/abc123' => Http::response(
            ['err' => 'Team not authorized', 'ECODE' => 'OAUTH_027'],
            401
        ),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => null]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
    ]);

    $response = $this->actingAs($user)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        ['clickup_link' => 'https://app.clickup.com/t/abc123']
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($bugreport->refresh()->clickup_task_id)->toBe('abc123');
});

test('link clickup can replace an already-linked task', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => 'old_task']);

    $response = $this->actingAs($user)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        ['clickup_link' => 'new_task']
    );

    $response->assertRedirect();
    expect($bugreport->refresh()->clickup_task_id)->toBe('new_task');
});

test('link clickup validates the link is required', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        []
    );

    $response->assertSessionHasErrors('clickup_link');
});

test('viewer cannot link clickup', function () {
    [$owner, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id]);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($viewer)->post(
        route('bugreports.link-clickup', [$project, $bugreport]),
        ['clickup_link' => 'abc123']
    );

    $response->assertForbidden();
});

test('unlink clickup clears the task id', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => 'abc123']);

    $response = $this->actingAs($user)->delete(
        route('bugreports.unlink-clickup', [$project, $bugreport])
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($bugreport->refresh()->clickup_task_id)->toBeNull();
});

test('viewer cannot unlink clickup', function () {
    [$owner, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => 'abc123']);

    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
    $viewer->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($viewer)->delete(
        route('bugreports.unlink-clickup', [$project, $bugreport])
    );

    $response->assertForbidden();
    expect($bugreport->refresh()->clickup_task_id)->toBe('abc123');
});

test('sync from clickup returns error when not linked', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'clickup_task_id' => null,
    ]);

    $response = $this->actingAs($user)->post(
        route('bugreports.sync-clickup', [$project, $bugreport])
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('sync from clickup updates status when changed', function () {
    Http::fake([
        'api.clickup.com/api/v2/task/abc123' => Http::response([
            'id' => 'abc123',
            'status' => ['status' => 'done'],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
        'status_mapping' => [
            'to_do' => 'to do',
            'done' => 'done',
        ],
    ]);

    $response = $this->actingAs($user)->post(
        route('bugreports.sync-clickup', [$project, $bugreport])
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $bugreport->refresh();
    expect($bugreport->status)->toBe('done');
});

test('sync from clickup returns info when already up to date', function () {
    Http::fake([
        'api.clickup.com/api/v2/task/abc123' => Http::response([
            'id' => 'abc123',
            'status' => ['status' => 'to do'],
        ]),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
        'status_mapping' => [
            'to_do' => 'to do',
            'done' => 'done',
        ],
    ]);

    $response = $this->actingAs($user)->post(
        route('bugreports.sync-clickup', [$project, $bugreport])
    );

    $response->assertRedirect();
    $response->assertSessionHas('info');
});

test('sync from clickup shows a friendly message when the task was deleted in clickup', function () {
    Http::fake([
        'api.clickup.com/api/v2/task/abc123' => Http::response(
            ['err' => 'Task not found, deleted', 'ECODE' => 'ITEM_013'],
            404
        ),
    ]);

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
    ]);

    $response = $this->actingAs($user)->post(
        route('bugreports.sync-clickup', [$project, $bugreport])
    );

    $response->assertRedirect();
    expect(session('error'))->toContain('no longer exists')
        ->and(session('error'))->not->toContain('ITEM_013');
});

test('sync all from clickup queues a job per linked bug report', function () {
    Queue::fake();

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    $linked1 = Bugreport::factory()->create([
        'project_id' => $project->id,
        'clickup_task_id' => 'abc123',
    ]);
    $linked2 = Bugreport::factory()->create([
        'project_id' => $project->id,
        'clickup_task_id' => 'def456',
    ]);
    Bugreport::factory()->create([
        'project_id' => $project->id,
        'clickup_task_id' => null,
    ]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
    ]);

    $response = $this->actingAs($user)->post(
        route('bugreports.sync-all-clickup', [$project])
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    Queue::assertPushed(SyncBugreportFromClickUp::class, 2);
    Queue::assertPushed(SyncBugreportFromClickUp::class, fn ($job) => $job->bugreport->id === $linked1->id);
    Queue::assertPushed(SyncBugreportFromClickUp::class, fn ($job) => $job->bugreport->id === $linked2->id);
});

test('sync all from clickup returns info when nothing is linked', function () {
    Queue::fake();

    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => null]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
    ]);

    $response = $this->actingAs($user)->post(
        route('bugreports.sync-all-clickup', [$project])
    );

    $response->assertRedirect();
    $response->assertSessionHas('info');

    Queue::assertNotPushed(SyncBugreportFromClickUp::class);
});

test('sync all from clickup returns error when not configured', function () {
    [$user, $workspace] = createUserWithWorkspace();
    $project = Project::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    Bugreport::factory()->create(['project_id' => $project->id, 'clickup_task_id' => 'abc123']);

    $response = $this->actingAs($user)->post(
        route('bugreports.sync-all-clickup', [$project])
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('sync bugreport from clickup job updates status when changed', function () {
    Http::fake([
        'api.clickup.com/api/v2/task/abc123' => Http::response([
            'id' => 'abc123',
            'status' => ['status' => 'done'],
        ]),
    ]);

    $workspace = Workspace::factory()->create();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
        'status_mapping' => [
            'to_do' => 'to do',
            'done' => 'done',
        ],
    ]);

    (new SyncBugreportFromClickUp($bugreport))->handle();

    $bugreport->refresh();
    expect($bugreport->status)->toBe('done');
});

test('export job uploads attachments to clickup task', function () {
    Storage::fake('public');
    Storage::disk('public')->put('attachments/bugreports/test.png', 'fake-image-content');

    Http::fake([
        'api.clickup.com/api/v2/list/123456/task' => Http::response(['id' => 'task_abc']),
        'api.clickup.com/api/v2/task/task_abc/attachment' => Http::response(['id' => 'att_1']),
    ]);

    $workspace = Workspace::factory()->create();
    $project = Project::factory()->create(['workspace_id' => $workspace->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id]);
    $bugreport->attachments()->create([
        'original_filename' => 'screenshot.png',
        'stored_path' => 'attachments/bugreports/test.png',
        'mime_type' => 'image/png',
        'size' => 100,
    ]);

    ClickupSetting::forWorkspace($workspace)->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
    ]);

    (new ExportBugreportToClickUp($bugreport))->handle();

    $bugreport->refresh();
    expect($bugreport->clickup_task_id)->toBe('task_abc');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/task/task_abc/attachment'));
});

test('two workspaces export bugs to their own clickup list independently', function () {
    Queue::fake();

    [$userA, $workspaceA] = createUserWithWorkspace();
    $projectA = Project::factory()->create(['user_id' => $userA->id, 'workspace_id' => $workspaceA->id]);
    ClickupSetting::forWorkspace($workspaceA)->update(['api_token' => 'token_a', 'list_id' => 'list_a']);

    [$userB, $workspaceB] = createUserWithWorkspace();
    $projectB = Project::factory()->create(['user_id' => $userB->id, 'workspace_id' => $workspaceB->id]);
    ClickupSetting::forWorkspace($workspaceB)->update(['api_token' => 'token_b', 'list_id' => 'list_b']);

    $bugA = Bugreport::factory()->create(['project_id' => $projectA->id]);
    $bugB = Bugreport::factory()->create(['project_id' => $projectB->id]);

    $this->actingAs($userA)->post(route('bugreports.export-clickup', [$projectA, $bugA]));
    $this->actingAs($userB)->post(route('bugreports.export-clickup', [$projectB, $bugB]));

    Queue::assertPushed(ExportBugreportToClickUp::class, 2);
});
