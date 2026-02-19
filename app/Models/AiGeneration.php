<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'provider',
        'model',
        'input_type',
        'test_cases_generated',
        'test_cases_approved',
        'test_cases_imported',
        'test_suite_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function testSuite(): BelongsTo
    {
        return $this->belongsTo(TestSuite::class);
    }
}
