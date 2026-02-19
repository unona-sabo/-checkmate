<?php

namespace App\Http\Requests\AIGenerator;

use Illuminate\Foundation\Http\FormRequest;

class ImportTestCasesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'test_suite_id' => ['nullable', 'integer', 'exists:test_suites,id'],
            'test_suite_name' => ['required_without:test_suite_id', 'nullable', 'string', 'max:255'],
            'ai_generation_id' => ['nullable', 'integer', 'exists:ai_generations,id'],
            'test_cases' => ['required', 'array', 'min:1'],
            'test_cases.*.title' => ['required', 'string', 'max:255'],
            'test_cases.*.description' => ['nullable', 'string'],
            'test_cases.*.preconditions' => ['nullable', 'string'],
            'test_cases.*.steps' => ['nullable', 'string'],
            'test_cases.*.expected_result' => ['nullable', 'string'],
            'test_cases.*.priority' => ['nullable', 'string', 'in:critical,high,medium,low'],
            'test_cases.*.severity' => ['nullable', 'string', 'in:blocker,critical,major,minor,trivial'],
            'test_cases.*.type' => ['nullable', 'string', 'in:functional,smoke,regression,integration,acceptance,performance,security,usability,exploratory,other'],
            'test_cases.*.automation_status' => ['nullable', 'string', 'in:not_automated,automated,in_progress,cannot_automate'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'test_suite_name.required_without' => 'Either select an existing test suite or provide a name for a new one.',
            'test_cases.required' => 'At least one test case must be selected for import.',
            'test_cases.min' => 'At least one test case must be selected for import.',
        ];
    }
}
