<?php

namespace App\Http\Requests\TestCoverage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoverageFeatureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'module' => 'nullable|array',
            'module.*' => 'string|max:100',
            'category' => 'nullable|string|max:100',
            'priority' => 'sometimes|in:critical,high,medium,low',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
