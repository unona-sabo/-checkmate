<?php

namespace App\Http\Requests\Release;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReleaseFeatureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'feature_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:30'],
            'test_coverage_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'tests_planned' => ['sometimes', 'integer', 'min:0'],
            'tests_executed' => ['sometimes', 'integer', 'min:0'],
            'tests_passed' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
