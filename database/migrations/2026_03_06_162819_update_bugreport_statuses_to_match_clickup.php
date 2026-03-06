<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop index that references the status column (SQLite can't drop column with index referencing it)
        Schema::table('bugreports', function ($table) {
            $table->dropIndex('bugreports_project_id_status_index');
        });

        Schema::table('bugreports', function ($table) {
            $table->renameColumn('status', 'status_old');
        });

        Schema::table('bugreports', function ($table) {
            $table->string('status')->default('to_do');
        });

        // Map old statuses to new statuses
        DB::table('bugreports')->where('status_old', 'new')->update(['status' => 'to_do']);
        DB::table('bugreports')->where('status_old', 'open')->update(['status' => 'to_do']);
        DB::table('bugreports')->where('status_old', 'in_progress')->update(['status' => 'in_progress']);
        DB::table('bugreports')->where('status_old', 'resolved')->update(['status' => 'done']);
        DB::table('bugreports')->where('status_old', 'closed')->update(['status' => 'done']);
        DB::table('bugreports')->where('status_old', 'reopened')->update(['status' => 'to_do']);

        Schema::table('bugreports', function ($table) {
            $table->dropColumn('status_old');
        });

        // Re-create the index on the new status column
        Schema::table('bugreports', function ($table) {
            $table->index(['project_id', 'status'], 'bugreports_project_id_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bugreports', function ($table) {
            $table->dropIndex('bugreports_project_id_status_index');
        });

        Schema::table('bugreports', function ($table) {
            $table->renameColumn('status', 'status_new');
        });

        Schema::table('bugreports', function ($table) {
            $table->string('status')->default('new');
        });

        DB::table('bugreports')->where('status_new', 'to_do')->update(['status' => 'new']);
        DB::table('bugreports')->where('status_new', 'in_progress')->update(['status' => 'in_progress']);
        DB::table('bugreports')->where('status_new', 'in_review')->update(['status' => 'in_progress']);
        DB::table('bugreports')->where('status_new', 'needs_changes')->update(['status' => 'open']);
        DB::table('bugreports')->where('status_new', 'cancelled')->update(['status' => 'closed']);
        DB::table('bugreports')->where('status_new', 'done')->update(['status' => 'resolved']);

        Schema::table('bugreports', function ($table) {
            $table->dropColumn('status_new');
        });

        Schema::table('bugreports', function ($table) {
            $table->index(['project_id', 'status'], 'bugreports_project_id_status_index');
        });
    }
};
