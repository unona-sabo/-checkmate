<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:make-admin {email} {--revoke : Remove admin access instead of granting it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant or revoke a user\'s site-wide admin access';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email \"{$this->argument('email')}\".");

            return self::FAILURE;
        }

        $isAdmin = ! $this->option('revoke');

        $user->update(['is_admin' => $isAdmin]);

        $this->info($isAdmin
            ? "{$user->name} ({$user->email}) is now an admin."
            : "{$user->name} ({$user->email}) is no longer an admin.");

        return self::SUCCESS;
    }
}
