<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverageAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'analysis_data',
        'overall_coverage',
        'total_features',
        'covered_features',
        'total_test_cases',
        'gaps_count',
        'analyzed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'analysis_data' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    /**
     * Get the project that owns the coverage analysis.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
