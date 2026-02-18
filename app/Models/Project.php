<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'name',
        'order',
        'automation_tests_path',
        'automation_config',
    ];

    protected function casts(): array
    {
        return [
            'automation_config' => 'array',
        ];
    }

    /**
     * Get the user that owns the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the workspace that the project belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get all checklists for the project.
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    /**
     * Get all test suites for the project.
     */
    public function testSuites(): HasMany
    {
        return $this->hasMany(TestSuite::class);
    }

    /**
     * Get all test runs for the project.
     */
    public function testRuns(): HasMany
    {
        return $this->hasMany(TestRun::class);
    }

    /**
     * Get all bugreports for the project.
     */
    public function bugreports(): HasMany
    {
        return $this->hasMany(Bugreport::class);
    }

    /**
     * Get all documentations for the project.
     */
    public function documentations(): HasMany
    {
        return $this->hasMany(Documentation::class);
    }

    /**
     * Get all design links for the project.
     */
    public function designLinks(): HasMany
    {
        return $this->hasMany(DesignLink::class);
    }

    /**
     * Get all test users for the project.
     */
    public function testUsers(): HasMany
    {
        return $this->hasMany(TestUser::class);
    }

    /**
     * Get all test payment methods for the project.
     */
    public function testPaymentMethods(): HasMany
    {
        return $this->hasMany(TestPaymentMethod::class);
    }

    /**
     * Get all notes for the project.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * Get all project features.
     */
    public function features(): HasMany
    {
        return $this->hasMany(ProjectFeature::class);
    }

    /**
     * Get all coverage analyses for the project.
     */
    public function coverageAnalyses(): HasMany
    {
        return $this->hasMany(CoverageAnalysis::class);
    }

    /**
     * Get the latest coverage analysis.
     */
    public function latestCoverageAnalysis(): HasOne
    {
        return $this->hasOne(CoverageAnalysis::class)->latestOfMany();
    }

    /**
     * Get all AI-generated test cases.
     */
    public function aiGeneratedTestCases(): HasMany
    {
        return $this->hasMany(AiGeneratedTestCase::class);
    }

    /**
     * Get all releases for the project.
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /**
     * Get all automation test results for the project.
     */
    public function automationTestResults(): HasMany
    {
        return $this->hasMany(AutomationTestResult::class);
    }

    /**
     * Get all test environments for the project.
     */
    public function testEnvironments(): HasMany
    {
        return $this->hasMany(TestEnvironment::class);
    }

    /**
     * Get all test run templates for the project.
     */
    public function testRunTemplates(): HasMany
    {
        return $this->hasMany(TestRunTemplate::class);
    }

    /**
     * Get all users associated with the project (for assignment).
     */
    public function users()
    {
        if ($this->workspace_id) {
            return $this->workspace->members();
        }

        return User::where('id', $this->user_id);
    }
}
