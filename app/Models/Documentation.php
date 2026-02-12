<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Documentation extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'content',
        'category',
        'order',
        'parent_id',
    ];

    /**
     * Get the project that owns the documentation.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the parent documentation.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Documentation::class, 'parent_id');
    }

    /**
     * Get child documentations.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Documentation::class, 'parent_id')->orderBy('order');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
