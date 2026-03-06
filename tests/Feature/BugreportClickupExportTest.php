<?php

use App\Jobs\ExportBugreportToClickUp;
use App\Models\Bugreport;
use App\Models\ClickupSetting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('export to clickup returns error when not configured', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->post(
        route('bugreports.export-clickup', [$project, $bugreport])
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('export to clickup dispatches job when configured', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id]);

    ClickupSetting::current()->update([
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

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'clickup_task_id' => 'existing_task_id',
    ]);

    ClickupSetting::current()->update([
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

test('sync from clickup returns error when not linked', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
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

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    ClickupSetting::current()->update([
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

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $bugreport = Bugreport::factory()->create([
        'project_id' => $project->id,
        'status' => 'to_do',
        'clickup_task_id' => 'abc123',
    ]);

    ClickupSetting::current()->update([
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

test('export job uploads attachments to clickup task', function () {
    Storage::fake('public');
    Storage::disk('public')->put('attachments/bugreports/test.png', 'fake-image-content');

    Http::fake([
        'api.clickup.com/api/v2/list/123456/task' => Http::response(['id' => 'task_abc']),
        'api.clickup.com/api/v2/task/task_abc/attachment' => Http::response(['id' => 'att_1']),
    ]);

    $project = Project::factory()->create();
    $bugreport = Bugreport::factory()->create(['project_id' => $project->id]);
    $bugreport->attachments()->create([
        'original_filename' => 'screenshot.png',
        'stored_path' => 'attachments/bugreports/test.png',
        'mime_type' => 'image/png',
        'size' => 100,
    ]);

    ClickupSetting::current()->update([
        'api_token' => 'pk_test_token',
        'list_id' => '123456',
    ]);

    (new ExportBugreportToClickUp($bugreport))->handle();

    $bugreport->refresh();
    expect($bugreport->clickup_task_id)->toBe('task_abc');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/task/task_abc/attachment'));
});
