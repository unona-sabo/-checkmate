<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'hidden_sidebar_categories',
    ];

    protected function casts(): array
    {
        return [
            'hidden_sidebar_categories' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the owner of the workspace.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all members of the workspace.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get all projects in the workspace.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the ClickUp integration settings for the workspace, if any.
     */
    public function clickupSetting(): HasOne
    {
        return $this->hasOne(ClickupSetting::class);
    }

    /**
     * Get the Grafana integration settings for the workspace, if any.
     */
    public function grafanaSetting(): HasOne
    {
        return $this->hasOne(GrafanaSetting::class);
    }

    /**
     * Get the AI provider settings for the workspace, if any.
     */
    public function aiSetting(): HasOne
    {
        return $this->hasOne(AiSetting::class);
    }
}
