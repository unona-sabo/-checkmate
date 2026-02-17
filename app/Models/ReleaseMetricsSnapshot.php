<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseMetricsSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_id',
        'test_completion_percentage',
        'test_pass_rate',
        'total_bugs',
        'critical_bugs',
        'high_bugs',
        'bug_closure_rate',
        'regression_pass_rate',
        'performance_score',
        'security_status',
        'snapshot_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_at' => 'datetime',
        ];
    }

    /**
     * Get the release that owns this metrics snapshot.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }
}
