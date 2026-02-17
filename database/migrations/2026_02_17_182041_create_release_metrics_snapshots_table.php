<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_metrics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->integer('test_completion_percentage')->default(0);
            $table->integer('test_pass_rate')->default(0);
            $table->integer('total_bugs')->default(0);
            $table->integer('critical_bugs')->default(0);
            $table->integer('high_bugs')->default(0);
            $table->integer('bug_closure_rate')->default(0);
            $table->integer('regression_pass_rate')->default(0);
            $table->integer('performance_score')->default(0);
            $table->string('security_status', 20)->default('pending');
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamps();

            $table->index(['release_id', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_metrics_snapshots');
    }
};
