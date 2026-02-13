<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'environment',
        'milestone',
        'status',
        'progress',
        'stats',
        'started_at',
        'completed_at',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the project that owns the test run.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who completed the test run.
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get all test run cases for the test run.
     */
    public function testRunCases(): HasMany
    {
        return $this->hasMany(TestRunCase::class);
    }

    /**
     * Calculate and update progress based on test run cases.
     */
    public function updateProgress(): void
    {
        $total = $this->testRunCases()->count();
        if ($total === 0) {
            $this->update(['progress' => 0]);

            return;
        }

        $completed = $this->testRunCases()
            ->whereIn('status', ['passed', 'failed', 'blocked', 'skipped'])
            ->count();

        $this->update(['progress' => (int) round(($completed / $total) * 100)]);
    }

    /**
     * Calculate and update stats based on test run cases.
     */
    public function updateStats(): void
    {
        $stats = $this->testRunCases()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->update(['stats' => $stats]);
    }
}
