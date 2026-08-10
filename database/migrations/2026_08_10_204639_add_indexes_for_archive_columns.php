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
        Schema::table('test_suites', function (Blueprint $table) {
            $table->index(['project_id', 'is_archived']);
        });

        Schema::table('test_cases', function (Blueprint $table) {
            $table->index('archived_from_suite_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_suites', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'is_archived']);
        });

        Schema::table('test_cases', function (Blueprint $table) {
            $table->dropIndex(['archived_from_suite_id']);
        });
    }
};
