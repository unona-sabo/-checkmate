<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    /**
     * Get the user that owns the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * Get all notes for the project.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * Get all users associated with the project (for assignment).
     */
    public function users()
    {
        return User::query();
    }
}
