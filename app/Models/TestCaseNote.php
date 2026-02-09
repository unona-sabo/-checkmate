<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestCaseNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_case_id',
        'content',
    ];

    /**
     * Get the test case that owns the note.
     */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }
}
