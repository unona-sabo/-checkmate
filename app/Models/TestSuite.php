<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSuite extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'parent_id',
        'name',
        'description',
        'order',
    ];

    /**
     * Get the project that owns the test suite.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the parent test suite.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TestSuite::class, 'parent_id');
    }

    /**
     * Get the child test suites.
     */
    public function children(): HasMany
    {
        return $this->hasMany(TestSuite::class, 'parent_id')->orderBy('order');
    }

    /**
     * Get all test cases for the test suite.
     */
    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class)->orderBy('order');
    }
}
