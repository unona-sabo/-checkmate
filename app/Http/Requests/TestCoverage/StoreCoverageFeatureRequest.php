<?php

namespace App\Http\Requests\TestCoverage;

use Illuminate\Foundation\Http\FormRequest;

class StoreCoverageFeatureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module' => 'nullable|array',
            'module.*' => 'string|max:100',
            'category' => 'nullable|string|max:100',
            'priority' => 'required|in:critical,high,medium,low',
        ];
    }
}
