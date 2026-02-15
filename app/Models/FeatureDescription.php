<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeatureDescription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'section_key',
        'feature_index',
        'title',
        'description',
        'is_custom',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'feature_index' => 'integer',
            'is_custom' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
