<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrafanaSetting extends Model
{
    protected $fillable = [
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
        return ! empty($this->api_token) && ! empty($this->base_url) && ! empty($this->datasource_id);
    }
}
