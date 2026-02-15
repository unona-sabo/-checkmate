<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_descriptions', function (Blueprint $table) {
            $table->id();
            $table->string('section_key');
            $table->unsignedInteger('feature_index');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('section_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_descriptions');
    }
};
