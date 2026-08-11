<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('last_active_date')->nullable()->after('current_workspace_id');
            $table->unsignedInteger('current_streak_days')->default(0)->after('last_active_date');
            $table->unsignedInteger('night_owl_days')->default(0)->after('current_streak_days');
            $table->unsignedInteger('early_bird_days')->default(0)->after('night_owl_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_active_date', 'current_streak_days', 'night_owl_days', 'early_bird_days']);
        });
    }
};
