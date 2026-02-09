<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestRunCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_run_id',
        'test_case_id',
        'status',
        'actual_result',
        'time_spent',
        'clickup_link',
        'qase_link',
        'assigned_to',
        'tested_at',
    ];

    protected function casts(): array
    {
        return [
            'tested_at' => 'datetime',
        ];
    }

    /**
     * Get the test run that owns the test run case.
     */
    public function testRun(): BelongsTo
    {
        return $this->belongsTo(TestRun::class);
    }

    /**
     * Get the test case for the test run case.
     */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    /**
     * Get the user assigned to the test run case.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
