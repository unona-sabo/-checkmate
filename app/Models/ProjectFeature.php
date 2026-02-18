<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'module',
        'category',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'module' => 'array',
        ];
    }

    /**
     * Get the project that owns the feature.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the test cases linked to this feature.
     */
    public function testCases(): BelongsToMany
    {
        return $this->belongsToMany(TestCase::class, 'feature_test_case', 'feature_id', 'test_case_id')
            ->withTimestamps();
    }

    /**
     * Get the checklists linked to this feature.
     */
    public function checklists(): BelongsToMany
    {
        return $this->belongsToMany(Checklist::class, 'feature_checklist', 'feature_id', 'checklist_id')
            ->withTimestamps();
    }

    /**
     * Get the bug reports linked to this feature.
     */
    public function bugreports(): BelongsToMany
    {
        return $this->belongsToMany(Bugreport::class, 'feature_bugreport', 'feature_id', 'bugreport_id')
            ->withTimestamps();
    }

    /**
     * Get the test suites linked to this feature.
     */
    public function testSuites(): BelongsToMany
    {
        return $this->belongsToMany(TestSuite::class, 'feature_test_suite', 'feature_id', 'test_suite_id')
            ->withTimestamps();
    }
}
