<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('gemini_api_key')->nullable();
            $table->string('gemini_model')->nullable();
            $table->text('anthropic_api_key')->nullable();
            $table->text('openai_api_key')->nullable();
            $table->string('openai_model')->nullable();
            $table->string('default_provider')->nullable();
            $table->timestamps();
        });

        // Preserve whatever is currently working via .env by seeding the
        // earliest workspace with it, so the cutover to per-workspace keys
        // doesn't immediately break the one setup already relying on them.
        $geminiKey = config('services.gemini.api_key');
        $anthropicKey = config('services.anthropic.api_key');
        $openaiKey = config('services.openai.api_key');

        if (empty($geminiKey) && empty($anthropicKey) && empty($openaiKey)) {
            return;
        }

        $firstWorkspaceId = DB::table('workspaces')->orderBy('id')->value('id');

        if (! $firstWorkspaceId) {
            return;
        }

        DB::table('ai_settings')->insert([
            'workspace_id' => $firstWorkspaceId,
            'gemini_api_key' => $geminiKey ? Crypt::encryptString($geminiKey) : null,
            'gemini_model' => config('services.gemini.model'),
            'anthropic_api_key' => $anthropicKey ? Crypt::encryptString($anthropicKey) : null,
            'openai_api_key' => $openaiKey ? Crypt::encryptString($openaiKey) : null,
            'openai_model' => config('services.openai.model'),
            'default_provider' => config('services.ai.default_provider'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
