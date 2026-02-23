<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TestCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_suite_id',
        'title',
        'description',
        'preconditions',
        'steps',
        'expected_result',
        'priority',
        'severity',
        'type',
        'module',
        'automation_status',
        'tags',
        'order',
        'created_by',
        'playwright_file',
        'playwright_test_name',
        'is_automated',
        'last_automated_run',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'tags' => 'array',
            'module' => 'array',
            'is_automated' => 'boolean',
            'last_automated_run' => 'datetime',
        ];
    }

    public function testSuite(): BelongsTo
    {
        return $this->belongsTo(TestSuite::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function note(): HasOne
    {
        return $this->hasOne(TestCaseNote::class);
    }

    public function testRunCases(): HasMany
    {
        return $this->hasMany(TestRunCase::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the project features linked to this test case.
     */
    public function projectFeatures(): BelongsToMany
    {
        return $this->belongsToMany(ProjectFeature::class, 'feature_test_case', 'test_case_id', 'feature_id')
            ->withTimestamps();
    }

    /**
     * Get all automation test results for this test case.
     */
    public function automationResults(): HasMany
    {
        return $this->hasMany(AutomationTestResult::class);
    }

    /**
     * Get the latest automation test result.
     */
    public function latestAutomationResult(): HasOne
    {
        return $this->hasOne(AutomationTestResult::class)->latestOfMany('executed_at');
    }
}
