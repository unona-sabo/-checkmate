<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestRunTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'environment_id',
        'tags',
        'tag_mode',
        'file_pattern',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'options' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(TestEnvironment::class, 'environment_id');
    }
}
