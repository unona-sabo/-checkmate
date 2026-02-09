<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'automation_status',
        'tags',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'tags' => 'array',
        ];
    }

    /**
     * Get the test suite that owns the test case.
     */
    public function testSuite(): BelongsTo
    {
        return $this->belongsTo(TestSuite::class);
    }

    /**
     * Get the note for the test case.
     */
    public function note(): HasOne
    {
        return $this->hasOne(TestCaseNote::class);
    }

    /**
     * Get all test run cases for this test case.
     */
    public function testRunCases(): HasMany
    {
        return $this->hasMany(TestRunCase::class);
    }
}
