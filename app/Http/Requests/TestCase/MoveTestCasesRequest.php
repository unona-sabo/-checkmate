<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class MoveTestCasesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'test_case_ids' => 'required|array|min:1',
            'test_case_ids.*' => 'exists:test_cases,id',
            'target_suite_id' => 'required|exists:test_suites,id',
            'target_project_id' => 'nullable|integer|exists:projects,id',
            'copy_attachments' => 'boolean',
            'copy_features' => 'boolean',
            'copy_notes' => 'boolean',
        ];
    }
}
