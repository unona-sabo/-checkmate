<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('checklists_created_count')->default(0)->after('early_bird_days');
            $table->unsignedInteger('documents_created_count')->default(0)->after('checklists_created_count');
            $table->unsignedInteger('notes_created_count')->default(0)->after('documents_created_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['checklists_created_count', 'documents_created_count', 'notes_created_count']);
        });
    }
};
