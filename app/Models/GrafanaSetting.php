<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrafanaSetting extends Model
{
    protected $fillable = [
        'workspace_id',
        'api_token',
        'base_url',
        'datasource_id',
        'log_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
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

    /**
     * Check if the integration is fully configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->api_token) && ! empty($this->base_url) && ! empty($this->datasource_id);
    }
}
