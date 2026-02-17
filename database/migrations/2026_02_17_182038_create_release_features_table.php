<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->nullable()->constrained('project_features')->nullOnDelete();
            $table->string('feature_name');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('planned');
            $table->integer('test_coverage_percentage')->default(0);
            $table->integer('tests_planned')->default(0);
            $table->integer('tests_executed')->default(0);
            $table->integer('tests_passed')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_features');
    }
};
