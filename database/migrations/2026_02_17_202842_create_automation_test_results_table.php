<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('test_file');
            $table->string('test_name');
            $table->string('status', 20)->default('passed');
            $table->integer('duration_ms')->default(0);
            $table->text('error_message')->nullable();
            $table->json('error_stack')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('video_path')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_test_results');
    }
};
