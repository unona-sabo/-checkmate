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
        Schema::table('bugreports', function (Blueprint $table) {
            $table->json('fixed_on')->nullable()->after('environment');
        });
    }

    public function down(): void
    {
        Schema::table('bugreports', function (Blueprint $table) {
            $table->dropColumn('fixed_on');
        });
    }
};
