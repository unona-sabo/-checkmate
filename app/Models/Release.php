<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Release extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'version',
        'name',
        'description',
        'planned_date',
        'actual_date',
        'status',
        'health',
        'decision',
        'decision_notes',
        'metadata',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_date' => 'date',
            'actual_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the project that owns the release.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who created the release.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the features for this release.
     */
    public function features(): HasMany
    {
        return $this->hasMany(ReleaseFeature::class);
    }

    /**
     * Get the checklist items for this release.
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(ReleaseChecklistItem::class);
    }

    /**
     * Get the metrics snapshots for this release.
     */
    public function metricsSnapshots(): HasMany
    {
        return $this->hasMany(ReleaseMetricsSnapshot::class);
    }

    /**
     * Get the test runs linked to this release.
     */
    public function testRuns(): BelongsToMany
    {
        return $this->belongsToMany(TestRun::class, 'release_test_runs')
            ->withTimestamps();
    }

    /**
     * Get the latest metrics snapshot.
     */
    public function latestMetrics(): HasOne
    {
        return $this->hasOne(ReleaseMetricsSnapshot::class)->latestOfMany('snapshot_at');
    }
}
