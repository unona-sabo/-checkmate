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
            $table->foreignId('duplicated_from_id')->nullable()->after('checklist_id')
                ->constrained('test_runs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicated_from_id');
        });
    }
};
