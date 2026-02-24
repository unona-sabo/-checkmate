<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'category',
        'description',
        'url',
        'comment',
        'order',
        'created_by',
    ];

    /**
     * Get the project that owns the test link.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who created the test link.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
