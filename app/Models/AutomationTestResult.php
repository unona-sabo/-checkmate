<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'test_case_id',
        'environment_id',
        'template_id',
        'test_file',
        'test_name',
        'status',
        'duration_ms',
        'error_message',
        'error_stack',
        'screenshot_path',
        'video_path',
        'tags',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'error_stack' => 'array',
            'tags' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(TestEnvironment::class, 'environment_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TestRunTemplate::class, 'template_id');
    }
}
