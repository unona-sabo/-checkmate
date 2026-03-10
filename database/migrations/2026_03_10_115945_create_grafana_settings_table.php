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
        Schema::create('grafana_settings', function (Blueprint $table) {
            $table->id();
            $table->text('api_token')->nullable();
            $table->string('base_url')->nullable();
            $table->string('datasource_id')->nullable();
            $table->string('log_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grafana_settings');
    }
};
