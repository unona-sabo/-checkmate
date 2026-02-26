<?php

use App\Models\User;

beforeEach(function () {
    $this->backupDir = storage_path('app/private/backups');

    if (! is_dir($this->backupDir)) {
        mkdir($this->backupDir, 0755, true);
    }

    // Clean up any test snapshots
    foreach (glob($this->backupDir.'/checkmate_*.sqlite') as $file) {
        unlink($file);
    }
});

afterEach(function () {
    // Clean up test snapshots
    foreach (glob($this->backupDir.'/checkmate_*.sqlite') as $file) {
        unlink($file);
    }
});

test('backup page requires authentication', function () {
    $this->get(route('backup.show'))->assertRedirect(route('login'));
});

test('backup page shows for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('backup.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/Backup'));
});

test('download returns sqlite file', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('backup.download'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/x-sqlite3');
});

test('snapshot creates file in storage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('backup.snapshot'))
        ->assertRedirect();

    $files = glob($this->backupDir.'/checkmate_*.sqlite');
    expect($files)->toHaveCount(1);
});

test('snapshot list shows on page', function () {
    $user = User::factory()->create();

    // Create a snapshot file manually
    $filename = 'checkmate_2026-01-15_120000.sqlite';
    copy(database_path('database.sqlite'), $this->backupDir.'/'.$filename);

    $this->actingAs($user)
        ->get(route('backup.show'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/Backup')
            ->has('snapshots', 1)
            ->where('snapshots.0.name', $filename)
        );
});

test('download snapshot works', function () {
    $user = User::factory()->create();

    $filename = 'checkmate_2026-01-15_120000.sqlite';
    copy(database_path('database.sqlite'), $this->backupDir.'/'.$filename);

    $response = $this->actingAs($user)
        ->post(route('backup.download-snapshot', ['filename' => $filename]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/x-sqlite3');
});

test('delete snapshot removes file', function () {
    $user = User::factory()->create();

    $filename = 'checkmate_2026-01-15_120000.sqlite';
    copy(database_path('database.sqlite'), $this->backupDir.'/'.$filename);

    $this->actingAs($user)
        ->delete(route('backup.destroy-snapshot', ['filename' => $filename]))
        ->assertRedirect();

    expect(file_exists($this->backupDir.'/'.$filename))->toBeFalse();
});

test('restore copies snapshot over database', function () {
    $user = User::factory()->create();

    $filename = 'checkmate_2026-01-15_120000.sqlite';
    copy(database_path('database.sqlite'), $this->backupDir.'/'.$filename);

    $this->actingAs($user)
        ->post(route('backup.restore', ['filename' => $filename]))
        ->assertRedirect();
});

test('download requires authentication', function () {
    $this->post(route('backup.download'))->assertRedirect(route('login'));
});

test('snapshot requires authentication', function () {
    $this->post(route('backup.snapshot'))->assertRedirect(route('login'));
});

test('filenames without proper prefix are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('backup.download-snapshot', ['filename' => 'not_a_valid_backup.sqlite']))
        ->assertStatus(400);
});

test('filenames with wrong extension are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('backup.destroy-snapshot', ['filename' => 'checkmate_2026-01-15_120000.php']))
        ->assertStatus(400);
});

test('filenames with extra characters are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('backup.restore', ['filename' => 'checkmate_2026-01-15_120000.sqlite.bak']))
        ->assertStatus(400);
});

test('invalid filename format is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('backup.download-snapshot', ['filename' => 'malicious.php']))
        ->assertStatus(400);
});
