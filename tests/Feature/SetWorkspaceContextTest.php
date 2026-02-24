<?php

use App\Models\Project;
use App\Models\Workspace;

test('workspace context matches project workspace when visiting a project route', function () {
    [$user, $workspaceA] = createUserWithWorkspace();

    $workspaceB = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspaceB->members()->attach($user->id, ['role' => 'owner']);

    $project = Project::factory()->create([
        'workspace_id' => $workspaceB->id,
        'user_id' => $user->id,
    ]);

    // User's current workspace is A, but we visit a project in workspace B
    $user->update(['current_workspace_id' => $workspaceA->id]);

    $response = $this->actingAs($user)
        ->get("/projects/{$project->id}/checklists");

    $response->assertOk();

    // The workspace context should have switched to B
    expect($user->fresh()->current_workspace_id)->toBe($workspaceB->id);
});

test('workspace context stays unchanged for personal projects without workspace', function () {
    [$user, $workspace] = createUserWithWorkspace();

    $project = Project::factory()->create([
        'workspace_id' => null,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get("/projects/{$project->id}/checklists");

    expect($user->fresh()->current_workspace_id)->toBe($workspace->id);
});

test('workspace context stays unchanged if user is not a member of the project workspace', function () {
    [$user, $workspaceA] = createUserWithWorkspace();

    $foreignWorkspace = Workspace::factory()->create();

    $project = Project::factory()->create([
        'workspace_id' => $foreignWorkspace->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get("/projects/{$project->id}/checklists");

    expect($user->fresh()->current_workspace_id)->toBe($workspaceA->id);
});
