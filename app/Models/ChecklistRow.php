<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_id',
        'data',
        'order',
        'row_type',
        'background_color',
        'font_color',
        'font_weight',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    /**
     * Get the checklist that owns the row.
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
