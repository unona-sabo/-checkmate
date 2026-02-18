<?php

namespace App\Http\Requests\TestSuite;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTestSuiteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:functional,smoke,regression,integration,acceptance,performance,security,usability,other',
            'parent_id' => 'nullable|exists:test_suites,id',
            'order' => 'nullable|integer',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'exists:project_features,id',
        ];
    }
}
