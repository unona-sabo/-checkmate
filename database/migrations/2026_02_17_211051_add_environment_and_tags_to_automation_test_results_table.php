<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_test_results', function (Blueprint $table) {
            $table->foreignId('environment_id')->nullable()->constrained('test_environments')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('test_run_templates')->nullOnDelete();
            $table->json('tags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('automation_test_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('environment_id');
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn('tags');
        });
    }
};
