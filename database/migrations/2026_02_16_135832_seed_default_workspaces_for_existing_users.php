<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = DB::table('users')->get();

        foreach ($users as $user) {
            $slug = Str::slug($user->name.'-workspace');
            $baseSlug = $slug;
            $counter = 1;
            while (DB::table('workspaces')->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }

            $now = now()->format('Y-m-d H:i:s');

            $workspaceId = DB::table('workspaces')->insertGetId([
                'name' => $user->name."'s Workspace",
                'slug' => $slug,
                'owner_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('workspace_members')->insert([
                'workspace_id' => $workspaceId,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('projects')
                ->where('user_id', $user->id)
                ->whereNull('workspace_id')
                ->update(['workspace_id' => $workspaceId]);

            DB::table('users')
                ->where('id', $user->id)
                ->update(['current_workspace_id' => $workspaceId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('projects')->update(['workspace_id' => null]);
        DB::table('users')->update(['current_workspace_id' => null]);
        DB::table('workspace_members')->truncate();
        DB::table('workspaces')->truncate();
    }
};
