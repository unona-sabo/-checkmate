<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_runs', function (Blueprint $table) {
            $table->string('source')->nullable()->after('status');
            $table->foreignId('checklist_id')->nullable()->after('source')->constrained()->nullOnDelete();
        });

        Schema::table('test_run_cases', function (Blueprint $table) {
            $table->string('title')->nullable()->after('test_case_id');
        });

        // Make test_case_id nullable for checklist-sourced cases
        Schema::table('test_run_cases', function (Blueprint $table) {
            $table->unsignedBigInteger('test_case_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('test_run_cases', function (Blueprint $table) {
            $table->unsignedBigInteger('test_case_id')->nullable(false)->change();
            $table->dropColumn('title');
        });

        Schema::table('test_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checklist_id');
            $table->dropColumn('source');
        });
    }
};
