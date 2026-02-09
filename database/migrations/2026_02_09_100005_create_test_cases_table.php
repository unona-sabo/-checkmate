<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_suite_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('preconditions')->nullable();
            $table->json('steps')->nullable();
            $table->text('expected_result')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('severity', ['trivial', 'minor', 'major', 'critical', 'blocker'])->default('major');
            $table->enum('type', ['functional', 'smoke', 'regression', 'integration', 'acceptance', 'performance', 'security', 'usability', 'other'])->default('functional');
            $table->enum('automation_status', ['not_automated', 'to_be_automated', 'automated'])->default('not_automated');
            $table->json('tags')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
