<?php

namespace App\Http\Requests\TestCoverage;

use Illuminate\Foundation\Http\FormRequest;

class ApproveGeneratedTestCasesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:ai_generated_test_cases,id'],
            'test_suite_id' => ['nullable', 'integer', 'exists:test_suites,id'],
            'test_suite_name' => ['required_without:test_suite_id', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one test case to save.',
            'ids.min' => 'Select at least one test case to save.',
            'test_suite_name.required_without' => 'Either select an existing test suite or provide a name for a new one.',
        ];
    }
}
