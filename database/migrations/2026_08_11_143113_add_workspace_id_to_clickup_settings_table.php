<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clickup_settings', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $firstWorkspaceId = DB::table('workspaces')->orderBy('id')->value('id');

        if ($firstWorkspaceId) {
            DB::table('clickup_settings')
                ->whereNull('workspace_id')
                ->update(['workspace_id' => $firstWorkspaceId]);
        }

        Schema::table('clickup_settings', function (Blueprint $table) {
            $table->unique('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('clickup_settings', function (Blueprint $table) {
            $table->dropUnique(['workspace_id']);
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};
