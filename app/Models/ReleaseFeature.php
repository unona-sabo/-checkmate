<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_id',
        'feature_id',
        'feature_name',
        'description',
        'status',
        'test_coverage_percentage',
        'tests_planned',
        'tests_executed',
        'tests_passed',
    ];

    /**
     * Get the release that owns this feature.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Get the project feature linked to this release feature.
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(ProjectFeature::class, 'feature_id');
    }
}
