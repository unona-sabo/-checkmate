<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Checklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'columns_config',
        'order',
        'category',
        'module',
    ];

    protected function casts(): array
    {
        return [
            'columns_config' => 'array',
            'module' => 'array',
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
     * Get only section header rows for the checklist.
     */
    public function sectionHeaders(): HasMany
    {
        return $this->hasMany(ChecklistRow::class)
            ->where('row_type', 'section_header')
            ->orderBy('order');
    }

    /**
     * Get the note for the checklist.
     */
    public function note(): HasOne
    {
        return $this->hasOne(ChecklistNote::class);
    }

    /**
     * Get the project features linked to this checklist.
     */
    public function projectFeatures(): BelongsToMany
    {
        return $this->belongsToMany(ProjectFeature::class, 'feature_checklist', 'checklist_id', 'feature_id')
            ->withTimestamps();
    }
}
