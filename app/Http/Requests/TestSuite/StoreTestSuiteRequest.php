<?php

namespace App\Http\Requests\TestSuite;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestSuiteRequest extends FormRequest
{
    public function rules(): array
    {
        $projectId = $this->route('project')?->id;

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:functional,smoke,regression,integration,acceptance,performance,security,usability,other',
            'parent_id' => [
                'nullable',
                Rule::exists('test_suites', 'id')->where('project_id', $projectId),
            ],
            'module' => 'nullable|array',
            'module.*' => 'string|in:UI,API,Backend,Database,Integration',
            'order' => 'nullable|integer',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => [
                Rule::exists('project_features', 'id')->where('project_id', $projectId),
            ],
            'test_case_ids' => 'nullable|array',
            'test_case_ids.*' => 'exists:test_cases,id',
        ];
    }
}
