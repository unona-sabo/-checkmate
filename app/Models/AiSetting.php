<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSetting extends Model
{
    protected $fillable = [
        'workspace_id',
        'gemini_api_key',
        'gemini_model',
        'anthropic_api_key',
        'openai_api_key',
        'openai_model',
        'default_provider',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gemini_api_key' => 'encrypted',
            'anthropic_api_key' => 'encrypted',
            'openai_api_key' => 'encrypted',
        ];
    }

    /**
     * Get the workspace that owns these settings.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get or create the settings record for the given workspace.
     */
    public static function forWorkspace(Workspace $workspace): self
    {
        return self::firstOrCreate(['workspace_id' => $workspace->id]);
    }

    public function apiKeyFor(string $provider): ?string
    {
        return match ($provider) {
            'gemini' => $this->gemini_api_key,
            'claude' => $this->anthropic_api_key,
            'openai' => $this->openai_api_key,
            default => null,
        } ?: null;
    }

    /**
     * Model to use for the given provider — the workspace's own override if
     * set, otherwise the app-level default (not a secret, safe to share).
     */
    public function modelFor(string $provider): ?string
    {
        return match ($provider) {
            'gemini' => $this->gemini_model ?: config('services.gemini.model'),
            'openai' => $this->openai_model ?: config('services.openai.model'),
            default => null,
        };
    }

    public function isConfigured(): bool
    {
        return $this->apiKeyFor('gemini') !== null
            || $this->apiKeyFor('claude') !== null
            || $this->apiKeyFor('openai') !== null;
    }
}
