<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_id',
        'content',
    ];

    /**
     * Get the checklist that owns the note.
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
