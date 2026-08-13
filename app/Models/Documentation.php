<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Mews\Purifier\Facades\Purifier;

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
     * Sanitize HTML on write so stored content can never carry a script/event-handler
     * payload, regardless of which code path wrote it (form, import, note publish).
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Purifier::clean($value),
        );
    }

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

    /**
     * Get the project features linked to this documentation page.
     */
    public function projectFeatures(): BelongsToMany
    {
        return $this->belongsToMany(ProjectFeature::class, 'feature_documentation', 'documentation_id', 'feature_id')
            ->withTimestamps();
    }
}
