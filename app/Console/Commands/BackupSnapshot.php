<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:snapshot {--keep=10 : Number of snapshots to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a timestamped snapshot of the SQLite database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDir = storage_path('app/private/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'checkmate_'.now()->format('Y-m-d_His').'.sqlite';
        $destination = $backupDir.'/'.$filename;

        copy(database_path('database.sqlite'), $destination);

        $this->info("Snapshot created: {$filename}");

        $this->rotate((int) $this->option('keep'));

        return self::SUCCESS;
    }

    /**
     * Rotate old snapshots, keeping only the last N.
     */
    private function rotate(int $keep): void
    {
        $backupDir = storage_path('app/private/backups');

        $files = glob($backupDir.'/checkmate_*.sqlite');

        if (count($files) <= $keep) {
            return;
        }

        // Sort by modification time descending (newest first)
        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        $toDelete = array_slice($files, $keep);

        foreach ($toDelete as $file) {
            unlink($file);
            $this->info('Deleted old snapshot: '.basename($file));
        }
    }
}
