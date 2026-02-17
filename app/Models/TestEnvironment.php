<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestEnvironment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'base_url',
        'variables',
        'workers',
        'retries',
        'browser',
        'headed',
        'timeout',
        'description',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'headed' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function automationTestResults(): HasMany
    {
        return $this->hasMany(AutomationTestResult::class, 'environment_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(TestRunTemplate::class, 'environment_id');
    }
}
