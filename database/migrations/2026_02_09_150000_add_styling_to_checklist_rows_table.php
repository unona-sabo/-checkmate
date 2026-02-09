<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_rows', function (Blueprint $table) {
            $table->enum('row_type', ['normal', 'section_header'])->default('normal')->after('order');
            $table->string('background_color', 7)->nullable()->after('row_type');
            $table->string('font_color', 7)->nullable()->after('background_color');
            $table->enum('font_weight', ['normal', 'medium', 'semibold', 'bold'])->default('normal')->after('font_color');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_rows', function (Blueprint $table) {
            $table->dropColumn(['row_type', 'background_color', 'font_color', 'font_weight']);
        });
    }
};
