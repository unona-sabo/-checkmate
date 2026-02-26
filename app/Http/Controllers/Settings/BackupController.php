<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Show the backup settings page with list of existing snapshots.
     */
    public function show(): Response
    {
        return Inertia::render('settings/Backup', [
            'snapshots' => $this->getSnapshots(),
        ]);
    }

    /**
     * Download the current live SQLite database.
     */
    public function download(): StreamedResponse
    {
        $dbPath = database_path('database.sqlite');

        return response()->streamDownload(function () use ($dbPath) {
            readfile($dbPath);
        }, 'checkmate_'.now()->format('Y-m-d_His').'.sqlite', [
            'Content-Type' => 'application/x-sqlite3',
        ]);
    }

    /**
     * Create a timestamped snapshot of the database.
     */
    public function snapshot(): RedirectResponse
    {
        $this->ensureBackupDirectory();

        $filename = 'checkmate_'.now()->format('Y-m-d_His').'.sqlite';

        copy(
            database_path('database.sqlite'),
            storage_path('app/private/backups/'.$filename)
        );

        return back()->with('success', 'Snapshot created: '.$filename);
    }

    /**
     * Download a specific snapshot file.
     */
    public function downloadSnapshot(string $filename): StreamedResponse
    {
        $this->validateFilename($filename);

        $path = storage_path('app/private/backups/'.$filename);

        abort_unless(file_exists($path), 404);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, $filename, [
            'Content-Type' => 'application/x-sqlite3',
        ]);
    }

    /**
     * Delete a specific snapshot.
     */
    public function destroySnapshot(string $filename): RedirectResponse
    {
        $this->validateFilename($filename);

        $path = 'backups/'.$filename;

        abort_unless(Storage::exists($path), 404);

        Storage::delete($path);

        return back()->with('success', 'Snapshot deleted: '.$filename);
    }

    /**
     * Restore a snapshot by copying it over the live database.
     */
    public function restore(string $filename): RedirectResponse
    {
        $this->validateFilename($filename);

        $snapshotPath = storage_path('app/private/backups/'.$filename);

        abort_unless(file_exists($snapshotPath), 404);

        copy($snapshotPath, database_path('database.sqlite'));

        return back()->with('success', 'Database restored from: '.$filename);
    }

    /**
     * Validate the filename to prevent path traversal attacks.
     */
    private function validateFilename(string $filename): void
    {
        abort_unless(
            preg_match('/^checkmate_\d{4}-\d{2}-\d{2}_\d{6}\.sqlite$/', $filename),
            400,
            'Invalid snapshot filename.'
        );
    }

    /**
     * Ensure the backups directory exists.
     */
    private function ensureBackupDirectory(): void
    {
        $dir = storage_path('app/private/backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Get list of existing snapshots with metadata.
     *
     * @return array<int, array{name: string, size: int, date: string}>
     */
    private function getSnapshots(): array
    {
        $dir = storage_path('app/private/backups');

        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/checkmate_*.sqlite');

        $snapshots = array_map(function ($file) {
            return [
                'name' => basename($file),
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }, $files);

        usort($snapshots, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return array_values($snapshots);
    }
}
