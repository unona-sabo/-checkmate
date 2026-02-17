<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_id',
        'category',
        'title',
        'description',
        'status',
        'priority',
        'is_blocker',
        'assigned_to',
        'completed_at',
        'notes',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_blocker' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the release that owns this checklist item.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Get the user assigned to this checklist item.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
