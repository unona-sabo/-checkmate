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
        Schema::table('test_runs', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('total_paused_seconds')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('test_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['paused_at', 'total_paused_seconds']);
        });
    }
};
