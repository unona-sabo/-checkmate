<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClickupSetting extends Model
{
    protected $fillable = [
        'api_token',
        'list_id',
        'status_mapping',
        'webhook_id',
        'webhook_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'status_mapping' => 'array',
        ];
    }

    /**
     * Get or create the singleton settings record.
     */
    public static function current(): self
    {
        return self::firstOrCreate([]);
    }

    /**
     * Check if the integration is fully configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->api_token) && ! empty($this->list_id);
    }
}
