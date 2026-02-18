<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Bugreport extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'steps_to_reproduce',
        'expected_result',
        'actual_result',
        'severity',
        'priority',
        'status',
        'environment',
        'assigned_to',
        'reported_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the project features linked to this bug report.
     */
    public function projectFeatures(): BelongsToMany
    {
        return $this->belongsToMany(ProjectFeature::class, 'feature_bugreport', 'bugreport_id', 'feature_id')
            ->withTimestamps();
    }
}
