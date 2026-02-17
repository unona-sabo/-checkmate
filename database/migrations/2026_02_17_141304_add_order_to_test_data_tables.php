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
        Schema::table('test_users', function (Blueprint $table) {
            $table->unsignedInteger('order')->default(0);
        });

        Schema::table('test_payment_methods', function (Blueprint $table) {
            $table->unsignedInteger('order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_users', function (Blueprint $table) {
            $table->dropColumn('order');
        });

        Schema::table('test_payment_methods', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
