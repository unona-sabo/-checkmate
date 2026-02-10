<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    public function children()
    {
        return $this->hasMany(Documentation::class, 'parent_id')->orderBy('order');
    }
}
