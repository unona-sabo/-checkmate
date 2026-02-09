<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Checklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'columns_config',
    ];

    protected function casts(): array
    {
        return [
            'columns_config' => 'array',
        ];
    }

    /**
     * Get the project that owns the checklist.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get all rows for the checklist.
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ChecklistRow::class)->orderBy('order');
    }

    /**
     * Get the note for the checklist.
     */
    public function note(): HasOne
    {
        return $this->hasOne(ChecklistNote::class);
    }
}
