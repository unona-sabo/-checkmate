<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneratedTestCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'feature_id',
        'title',
        'preconditions',
        'test_steps',
        'expected_result',
        'priority',
        'type',
        'is_approved',
        'approved_by',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'test_steps' => 'array',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the project that owns the AI-generated test case.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the feature associated with this test case.
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(ProjectFeature::class, 'feature_id');
    }

    /**
     * Get the user who approved this test case.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
