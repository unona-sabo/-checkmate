<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignFeatureTestCasesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'test_case_ids' => 'required|array|min:1',
            'test_case_ids.*' => 'exists:test_cases,id',
            'feature_ids' => 'required|array|min:1',
            'feature_ids.*' => 'exists:project_features,id',
        ];
    }
}
