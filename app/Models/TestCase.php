<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function testSuite(): BelongsTo
    {
        return $this->belongsTo(TestSuite::class);
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
}
