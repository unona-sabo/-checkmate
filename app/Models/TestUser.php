<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'email',
        'password',
        'role',
        'environment',
        'description',
        'is_valid',
        'additional_info',
        'tags',
        'created_by',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_valid' => 'boolean',
            'additional_info' => 'array',
            'tags' => 'array',
            'password' => 'encrypted',
        ];
    }

    /**
     * Get the project that owns the test user.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who created the test user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
