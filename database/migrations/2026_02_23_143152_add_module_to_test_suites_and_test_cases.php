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
            $table->json('module')->nullable()->after('type');
        });

        Schema::table('test_cases', function (Blueprint $table) {
            $table->json('module')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_suites', function (Blueprint $table) {
            $table->dropColumn('module');
        });

        Schema::table('test_cases', function (Blueprint $table) {
            $table->dropColumn('module');
        });
    }
};
