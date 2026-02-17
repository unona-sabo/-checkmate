<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_cases', function (Blueprint $table) {
            $table->string('playwright_file')->nullable();
            $table->string('playwright_test_name')->nullable();
            $table->boolean('is_automated')->default(false);
            $table->timestamp('last_automated_run')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('test_cases', function (Blueprint $table) {
            $table->dropColumn(['playwright_file', 'playwright_test_name', 'is_automated', 'last_automated_run']);
        });
    }
};
